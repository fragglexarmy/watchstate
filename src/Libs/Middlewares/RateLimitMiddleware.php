<?php

declare(strict_types=1);

namespace App\Libs\Middlewares;

use App\Libs\Config;
use App\Libs\Enums\Http\Status;
use Psr\Http\Message\ResponseInterface as iResponse;
use Psr\Http\Message\ServerRequestInterface as iRequest;
use Psr\Http\Server\MiddlewareInterface as iMiddleware;
use Psr\Http\Server\RequestHandlerInterface as iHandler;
use Psr\SimpleCache\CacheInterface as iCache;

final readonly class RateLimitMiddleware implements iMiddleware
{
    private const string CACHE_PREFIX = 'rate_limit';

    public function __construct(
        private iCache $cache,
    ) {}

    /**
     * Rate limit repeated failures for routes that opt in via route middleware.
     */
    public function process(iRequest $request, iHandler $handler): iResponse
    {
        if (true === (bool) $request->getAttribute('INTERNAL_REQUEST')) {
            return $handler->handle($request);
        }

        if (true !== (bool) Config::get('rate_limit.enabled', true)) {
            return $handler->handle($request);
        }

        $attemptLimit = max(1, (int) Config::get('rate_limit.max_attempts', 5));
        $window = max(1, (int) Config::get('rate_limit.window', 900));
        $banFor = max(1, (int) Config::get('rate_limit.ban', 900));

        if (null !== ($retryAfter = $this->getRetryAfter($request))) {
            return $this->failResponse($request, $retryAfter);
        }

        $response = $handler->handle($request);
        $statusCode = $response->getStatusCode();

        if ($statusCode >= Status::OK->value && $statusCode < 300) {
            $this->cache->delete($this->getKey('attempts', $request));
            $this->cache->delete($this->getKey('ban', $request));
            return $response;
        }

        if (false === $this->countFailure($statusCode)) {
            return $response;
        }

        if ($this->increment($request, $window) < $attemptLimit) {
            return $response;
        }

        $this->ban($request, $banFor);

        return $this->failResponse($request, $banFor);
    }

    private function countFailure(int $statusCode): bool
    {
        if (Status::TOO_MANY_REQUESTS === Status::from($statusCode)) {
            return false;
        }

        return $statusCode >= Status::BAD_REQUEST->value && $statusCode < Status::INTERNAL_SERVER_ERROR->value;
    }

    private function getRetryAfter(iRequest $request): ?int
    {
        $state = $this->cache->get($this->getKey('ban', $request), null);
        if (!is_array($state)) {
            return null;
        }

        $until = (int) ag($state, 'until', 0);
        $retryAfter = $until - time();

        if ($retryAfter > 0) {
            return $retryAfter;
        }

        $this->cache->delete($this->getKey('ban', $request));

        return null;
    }

    private function increment(iRequest $request, int $window): int
    {
        $key = $this->getKey('attempts', $request);
        $state = $this->cache->get($key, []);
        $count = (int) ag($state, 'count', 0) + 1;

        $this->cache->set($key, ['count' => $count, 'updated_at' => time()], $window);

        return $count;
    }

    private function ban(iRequest $request, int $banFor): void
    {
        $this->cache->set(
            $this->getKey('ban', $request),
            [
                'until' => time() + $banFor,
                'created_at' => time(),
            ],
            $banFor,
        );

        $this->cache->delete($this->getKey('attempts', $request));
    }

    private function failResponse(iRequest $request, int $retryAfter): iResponse
    {
        return api_error(
            message: 'Too many requests. Please wait and try again.',
            body: [
                'ip' => get_client_ip($request),
            ],
            httpCode: Status::TOO_MANY_REQUESTS,
            headers: [
                'Retry-After' => (string) max(1, $retryAfter),
            ],
        );
    }

    private function getKey(string $type, iRequest $request): string
    {
        $path = rtrim($request->getUri()->getPath(), '/');

        return r('{prefix}.{type}.{key}', [
            'prefix' => self::CACHE_PREFIX,
            'type' => $type,
            'key' => hash('sha256', r('{ip}:{request}', [
                'ip' => get_client_ip($request),
                'request' => r('{method} {path}', [
                    'method' => $request->getMethod(),
                    'path' => '' === $path ? '/' : $path,
                ]),
            ])),
        ]);
    }
}
