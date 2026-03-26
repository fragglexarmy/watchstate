<?php

declare(strict_types=1);

namespace App\Backends\Plex\Action;

use App\Backends\Common\CommonTrait;
use App\Backends\Common\Context;
use App\Backends\Common\Error;
use App\Backends\Common\Levels;
use App\Backends\Common\Response;
use App\Libs\Container;
use App\Libs\Enums\Http\Status;
use App\Libs\Exceptions\Backends\InvalidArgumentException;
use App\Libs\Extends\RetryableHttpClient;
use App\Libs\Options;
use Closure;
use DateInterval;
use JsonException;
use Psr\Http\Message\UriInterface as iUri;
use Psr\Log\LoggerInterface as iLogger;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as iException;
use Symfony\Contracts\HttpClient\HttpClientInterface as iHttp;
use Symfony\Contracts\HttpClient\ResponseInterface as iResponse;
use Throwable;

final class GetUsersList
{
    use CommonTrait;

    private int $maxRetry = 3;
    private string $action = 'plex.getUsersList';
    private iHttp $http;

    private bool $logRequests = false;

    private array $rawRequests = [];

    public function __construct(
        iHttp $http,
        protected iLogger $logger,
    ) {
        $this->http = new RetryableHttpClient(client: $http, maxRetries: $this->maxRetry, logger: $this->logger);
    }

    /**
     * Get Users list.
     *
     * @param Context $context
     * @param array $opts optional options.
     *
     * @return Response
     */
    public function __invoke(Context $context, array $opts = []): Response
    {
        return $this->tryResponse(
            context: $context,
            fn: fn() => $this->action($context, $opts),
            action: $this->action,
        );
    }

    /**
     * Get Users list.
     *
     * @throws iException
     * @throws JsonException
     */
    private function action(Context $context, array $opts = []): Response
    {
        $callback = ag($opts, Options::RAW_RESPONSE_CALLBACK, null);
        $this->logRequests = $callback && ag($opts, Options::RAW_RESPONSE, false);

        $opts[Options::LOG_TO_WRITER] = ag($opts, Options::LOG_TO_WRITER, static fn() => static function (string $log) {});

        $cls = fn() => $this->getHomeUsers($context, $opts);

        $opts[Options::LOG_TO_WRITER](r('Reading data from cache? {state}', [
            'state' => true === (bool) ag($opts, Options::NO_CACHE) ? 'no' : 'yes',
        ]));

        $data = true === (bool) ag($opts, Options::NO_CACHE)
            ? $cls()
            : $this->tryCache(
                $context,
                $context->backendName . '_' . $context->backendId . '_users_' . md5((string) json_encode($opts)),
                $cls,
                new DateInterval('PT5M'),
                $this->logger,
            );

        if (count($this->rawRequests) > 0 && $callback instanceof Closure) {
            $callback($this->rawRequests);
        }

        return $data;
    }

    /**
     * Get Home Users.
     *
     * @param Context $context The context.
     * @param array $opts The options.
     *
     * @return Response Return the response.
     * @throws iException if an error occurs during the request.
     * @throws JsonException if an error occurs during the JSON parsing.
     */
    private function getHomeUsers(Context $context, array $opts = []): Response
    {
        $url = Container::getNew(iUri::class)
            ->withPort(443)
            ->withScheme('https')
            ->withHost('plex.tv')
            ->withPath('/api/v2/home/users/');

        if (null !== ($pin = ag($context->options, Options::PLEX_USER_PIN))) {
            $url = $url->withQuery(http_build_query(['pin' => $pin]));
        }

        $this->logger->debug("Requesting '{user}@{backend}' users list.", [
            'user' => $context->userContext->name,
            'backend' => $context->backendName,
            'url' => (string) $url,
        ]);

        try {
            $response = $this->request($url, $context, opts: [
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (InvalidArgumentException|iException $e) {
            return new Response(
                status: false,
                error: new Error(
                    message: $e->getMessage(),
                    context: [
                        'user' => $context->userContext->name,
                        'backend' => $context->backendName,
                    ],
                    level: Levels::ERROR,
                    previous: $e,
                ),
            );
        }

        return $this->processHomeUsers($context, $url, $response, $opts);
    }

    /**
     * Process home-users response.
     *
     * @param Context $context The context.
     * @param iUri $url The URL.
     * @param iResponse $response The response.
     * @param array $opts The options.
     *
     * @return Response Return processed response.
     * @throws iException if an error occurs during the request.
     * @throws JsonException if an error occurs during the JSON parsing.
     */
    private function processHomeUsers(
        Context $context,
        iUri $url,
        iResponse $response,
        array $opts,
    ): Response {
        $json = json_decode(
            json: $response->getContent(false),
            associative: true,
            flags: JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE,
        );

        if ($this->logRequests) {
            $this->rawRequests[] = [
                'url' => (string) $url,
                'headers' => $response->getHeaders(false),
                'body' => $json,
            ];
        }

        if ($context->trace) {
            $this->logger->debug("Parsing '{user}@{backend}' home users list payload.", [
                'user' => $context->userContext->name,
                'backend' => $context->backendName,
                'url' => (string) $url,
                'trace' => $json,
            ]);
        }

        if (null !== ($targetUser = ag($opts, Options::TARGET_USER, null))) {
            if ('' === ($targetUser = (string) $targetUser)) {
                $targetUser = null;
            }
        }

        $list = [];

        foreach (ag($json, 'users', []) as $data) {
            $data = [
                'id' => ag($data, 'id'),
                'type' => 'H',
                'uuid' => ag($data, 'uuid'),
                'name' => normalize_name(ag($data, ['friendlyName', 'username', 'title', 'email', 'id'], '??')),
                'admin' => (bool) ag($data, 'admin'),
                'guest' => (bool) ag($data, 'guest'),
                'restricted' => (bool) ag($data, 'restricted'),
                'protected' => (bool) ag($data, 'protected'),
                'updatedAt' => isset($data['updatedAt']) ? make_date($data['updatedAt']) : 'Unknown',
                'pinRequired' => null !== ag($data, 'pin', null) ? true : false,
            ];

            if (true === (bool) ag($opts, Options::GET_TOKENS)) {
                $matchesTarget =
                    null === $targetUser
                    || (string) $targetUser === (string) ag($data, 'id')
                    || (string) $targetUser === (string) ag($data, 'uuid');

                if (true === $matchesTarget) {
                    $tokenRequest = Container::getNew(GetUserToken::class)(
                        context: $context,
                        userId: ag($data, 'uuid'),
                        username: ag($data, 'name'),
                    );

                    if ($tokenRequest->hasError() && $tokenRequest->error) {
                        $this->logger->log(
                            $tokenRequest->error->level(),
                            $tokenRequest->error->message,
                            $tokenRequest->error->context,
                        );
                    }

                    $data['token'] = $tokenRequest->isSuccessful() ? $tokenRequest->response : null;
                    if (true === $tokenRequest->hasError() && $tokenRequest->error) {
                        $data['token_error'] = ag($tokenRequest->error->extra, 'error', $tokenRequest->error->format());
                    }
                } elseif (null !== $targetUser) {
                    $data['token'] = null;
                }
            }

            $list[] = $data;
        }

        $opts[Options::LOG_TO_WRITER](r("Total '{count}' home users processed. {users}", [
            'users' => array_to_json($list),
            'count' => count($list),
        ]));

        return new Response(status: true, response: $list);
    }

    /**
     * Do the actual API request.
     *
     * @param iUri $url The URL.
     * @param Context $context The context.
     * @param array $opts The options.
     *
     * @return iResponse Return the response.
     * @throws iException if an error occurs during the request.
     * @throws InvalidArgumentException if the request returns an unexpected status code.
     */
    private function request(iUri $url, Context $context, array $opts = []): iResponse
    {
        if (null !== ($adminToken = ag($context->options, Options::ADMIN_TOKEN))) {
            if (null !== ($adminPin = ag($context->options, Options::ADMIN_PLEX_USER_PIN))) {
                parse_str($url->getQuery(), $query);
                $url = $url->withQuery(http_build_query(['pin' => $adminPin, ...$query]));
            }
            $response = $this->http->request(ag($opts, 'method', 'GET'), (string) $url, [
                'headers' => array_replace_recursive(
                    [
                        'X-Plex-Token' => $adminToken,
                        'X-Plex-Client-Identifier' => $context->backendId,
                    ],
                    ag($opts, 'headers', []),
                ),
            ]);
            if (Status::OK === Status::from($response->getStatusCode())) {
                return $response;
            }
        }

        if (null !== ($pin = ag($context->options, Options::PLEX_USER_PIN))) {
            parse_str($url->getQuery(), $query);
            $url = $url->withQuery(http_build_query(['pin' => $pin, ...$query]));
        }

        $response = $this->http->request(ag($opts, 'method', 'GET'), (string) $url, [
            'headers' => array_replace_recursive(
                [
                    'X-Plex-Token' => $context->backendToken,
                    'X-Plex-Client-Identifier' => $context->backendId,
                ],
                ag($opts, 'headers', []),
            ),
        ]);

        if (Status::OK === Status::from($response->getStatusCode())) {
            return $response;
        }

        $extra_msg = '';

        try {
            $extra_msg = ag($response->toArray(false), 'errors.0.message', '?');
        } catch (Throwable) {
        }

        throw new InvalidArgumentException(
            r(
                "Request for '{user}@{backend}' users list returned with unexpected '{status_code}' status code. {tokenType}{extra_msg}",
                [
                    'user' => $context->userContext->name,
                    'backend' => $context->backendName,
                    'status_code' => $response->getStatusCode(),
                    'body' => $response->getContent(false),
                    'extra_msg' => !$extra_msg ? '' : ". {$extra_msg}",
                    'tokenType' => ag_exists(
                        $context->options,
                        Options::ADMIN_TOKEN,
                    )
                        ? 'user & admin token'
                        : 'user token',
                ],
            ),
        );
    }
}
