<?php

declare(strict_types=1);

namespace Tests\API\System;

use App\API\System\Command;
use App\Libs\Config;
use App\Libs\Enums\Http\Status;
use App\Libs\TestCase;
use DirectoryIterator;
use Tests\Support\RequestResponseTrait;

final class CommandTest extends TestCase
{
    use RequestResponseTrait;

    private string $tmpDir;

    private mixed $previousTmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousTmpDir = Config::get('tmpDir', null);
        $this->tmpDir = sys_get_temp_dir() . '/watchstate_command_test_' . uniqid('', true);

        mkdir($this->tmpDir, 0o755, true);
        mkdir($this->tmpDir . '/console', 0o755, true);
        Config::save('tmpDir', $this->tmpDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);

        if (null === $this->previousTmpDir) {
            Config::remove('tmpDir');
        } else {
            Config::save('tmpDir', $this->previousTmpDir);
        }

        parent::tearDown();
    }

    public function test_queue_creates_session_files(): void
    {
        $handler = new Command();
        $response = $handler->queue($this->getRequest(post: ['command' => 'system:tasks']));

        $this->assertSame(Status::CREATED->value, $response->getStatusCode());

        $payload = json_decode((string) $response->getBody(), true);
        $token = ag($payload, 'token');
        $sessionPath = $this->tmpDir . '/console/' . $token;

        $this->assertIsString($token);
        $this->assertNotSame('', $token);
        $this->assertFileExists($sessionPath . '/request.json');
        $this->assertFileExists($sessionPath . '/state.json');
        $this->assertFileExists($sessionPath . '/stream.log');

        $state = json_decode((string) file_get_contents($sessionPath . '/state.json'), true);

        $this->assertSame('queued', ag($state, 'status'));
        $this->assertSame('system:tasks', ag($state, 'command'));
        $this->assertSame(0, ag($state, 'connections'));
    }

    public function test_stream_rejects_completed_session_and_cleans_it_up(): void
    {
        $handler = new Command();
        $response = $handler->queue($this->getRequest(post: ['command' => 'system:tasks']));

        $payload = json_decode((string) $response->getBody(), true);
        $token = (string) ag($payload, 'token');
        $sessionPath = $this->tmpDir . '/console/' . $token;
        $statePath = $sessionPath . '/state.json';

        $state = json_decode((string) file_get_contents($statePath), true);
        $state['status'] = 'completed';
        $state['connections'] = 0;

        file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_IGNORE));

        $streamResponse = $handler->stream($this->getRequest(), $token);

        $this->assertSame(Status::BAD_REQUEST->value, $streamResponse->getStatusCode());
        $this->assertFalse(is_dir($sessionPath));
    }

    private function removeDirectory(string $path): void
    {
        if (false === is_dir($path)) {
            return;
        }

        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot()) {
                continue;
            }

            $itemPath = $item->getRealPath();
            if (false === $itemPath) {
                continue;
            }

            if ($item->isDir()) {
                $this->removeDirectory($itemPath);
                continue;
            }

            unlink($itemPath);
        }

        rmdir($path);
    }
}
