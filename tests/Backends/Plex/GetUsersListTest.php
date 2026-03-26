<?php

declare(strict_types=1);

namespace Tests\Backends\Plex;

use App\Backends\Plex\Action\GetUsersList;
use App\Libs\Container;
use App\Libs\Options;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface as iHttp;

class GetUsersListTest extends PlexTestCase
{
    public function test_get_users_list_home_users(): void
    {
        $homeUsersJson = json_encode([
            'users' => [
                [
                    'id' => 1,
                    'uuid' => 'uuid-1',
                    'friendlyName' => 'Test User',
                    'admin' => true,
                    'guest' => false,
                    'restricted' => false,
                    'protected' => false,
                    'updatedAt' => '2024-01-01T00:00:00Z',
                ],
            ],
        ]);

        $http = new \App\Libs\Extends\MockHttpClient(function (string $method, string $url) use ($homeUsersJson) {
            if (str_contains($url, '/api/v2/home/users/')) {
                return new MockResponse($homeUsersJson, ['http_code' => 200]);
            }

            return new MockResponse('not-found', ['http_code' => 404]);
        });

        $context = $this->makeContext();
        $action = new GetUsersList($http, $this->logger);
        $result = $action($context);

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('test_user', $result->response[0]['name'], 'Name normalization failed');
        $this->assertTrue($result->response[0]['admin']);
    }

    public function test_get_users_list_target_token(): void
    {
        $homeUsersJson = json_encode([
            'users' => [
                [
                    'id' => 1,
                    'uuid' => 'uuid-1',
                    'friendlyName' => 'Test User 1',
                    'admin' => true,
                    'guest' => false,
                    'restricted' => false,
                    'protected' => false,
                    'updatedAt' => '2024-01-01T00:00:00Z',
                ],
                [
                    'id' => 2,
                    'uuid' => 'uuid-2',
                    'friendlyName' => 'Test User 2',
                    'admin' => false,
                    'guest' => false,
                    'restricted' => false,
                    'protected' => false,
                    'updatedAt' => '2024-01-02T00:00:00Z',
                ],
            ],
        ]);
        $switchJson = json_encode(['authToken' => 'temp-token']);
        $resourcesJson = json_encode([
            [
                'clientIdentifier' => 'plex-server-1',
                'accessToken' => 'token-uuid-2',
                'provides' => 'server',
                'name' => 'Plex Server',
            ],
        ]);

        $requests = [];
        $http = new \App\Libs\Extends\MockHttpClient(function (string $method, string $url) use (&$requests, $homeUsersJson, $switchJson, $resourcesJson) {
            $requests[] = $url;
            if (str_contains($url, '/api/v2/home/users/') && str_contains($url, '/switch')) {
                if (str_contains($url, '/api/v2/home/users/uuid-2/switch')) {
                    return new MockResponse($switchJson, ['http_code' => 201]);
                }

                return new MockResponse('denied', ['http_code' => 403]);
            }

            if (str_contains($url, '/api/v2/resources')) {
                return new MockResponse($resourcesJson, ['http_code' => 200]);
            }

            if (str_contains($url, '/api/v2/home/users/')) {
                return new MockResponse($homeUsersJson, ['http_code' => 200]);
            }

            return new MockResponse('not-found', ['http_code' => 404]);
        });

        Container::add(iHttp::class, fn() => $http);

        $context = $this->makeContext();
        $action = new GetUsersList($http, $this->logger);
        $result = $action($context, [Options::GET_TOKENS => true, Options::TARGET_USER => 'uuid-2']);

        $this->assertTrue($result->isSuccessful());
        $this->assertNull($result->response[0]['token'] ?? null);
        $this->assertSame('token-uuid-2', $result->response[1]['token'] ?? null);
        $this->assertFalse(
            array_reduce(
                $requests,
                static fn(bool $found, string $url) => $found || str_contains($url, '/api/v2/home/users/uuid-1/switch'),
                false,
            ),
            'Unexpected token request for non-target user.',
        );
    }

    public function test_get_users_list_error_status(): void
    {
        $http = new \App\Libs\Extends\MockHttpClient([
            new MockResponse('error', ['http_code' => 500]),
        ]);

        $context = $this->makeContext();
        $action = new GetUsersList($http, $this->logger);
        $result = $action($context, [Options::NO_CACHE => true]);

        $this->assertFalse($result->isSuccessful());
        $this->assertNotNull($result->error);
    }
}
