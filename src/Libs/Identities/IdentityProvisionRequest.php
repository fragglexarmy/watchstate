<?php

declare(strict_types=1);

namespace App\Libs\Identities;

final class IdentityProvisionRequest
{
    public function __construct(
        public readonly string $mode,
        public readonly bool $dryRun = false,
        public readonly bool $regenerateTokens = false,
        public readonly bool $generateBackup = false,
        public readonly bool $allowSingleBackendIdentities = false,
        public readonly bool $persistMapping = true,
        public readonly string $mappingVersion = '1.6',
        public readonly array $mapping = [],
    ) {}

    /**
     * Determine if the request should replace existing identities.
     */
    public function shouldRecreate(): bool
    {
        return 'recreate' === $this->mode;
    }

    /**
     * Determine if the request should update existing identities in place.
     */
    public function shouldUpdate(): bool
    {
        return 'update' === $this->mode;
    }
}
