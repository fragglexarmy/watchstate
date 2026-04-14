<?php

declare(strict_types=1);

namespace App\Libs;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class StreamedBody implements StreamInterface
{
    private mixed $func;
    private bool $hasRun = false;
    private string $buffer = '';

    public function __construct(
        callable $func,
        private bool $isReadable = true,
        private bool $runOnce = false,
    ) {
        $this->func = $func;
    }

    public static function create(
        callable $func,
        bool $isReadable = true,
        bool $runOnce = false,
    ): StreamInterface {
        return new self($func, isReadable: $isReadable, runOnce: $runOnce);
    }

    public function __destruct() {}

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function close(): void {}

    public function detach(): null
    {
        return null;
    }

    public function getSize(): ?int
    {
        return null;
    }

    public function tell(): int
    {
        return 0;
    }

    public function eof(): bool
    {
        if (true === $this->runOnce) {
            return $this->hasRun;
        }

        return false;
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek($offset, $whence = \SEEK_SET): void {}

    public function rewind(): void {}

    public function isWritable(): bool
    {
        return false;
    }

    public function write($string): int
    {
        throw new RuntimeException('Unable to write to a non-writable stream.');
    }

    public function isReadable(): bool
    {
        return $this->isReadable;
    }

    public function read($length): string
    {
        if (true === $this->runOnce && true === $this->hasRun) {
            return '';
        }

        return $this->getContents();
    }

    public function getContents(): string
    {
        if (true === $this->runOnce && true === $this->hasRun) {
            return '';
        }

        if (true === $this->runOnce) {
            $this->hasRun = true;
            $result = ($this->func)();
            $this->buffer = is_string($result) ? $result : '';
            return $this->buffer;
        }

        return ($this->func)();
    }

    public function getMetadata($key = null): null
    {
        return null;
    }
}
