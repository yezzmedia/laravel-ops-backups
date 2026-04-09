<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Data;

use Carbon\CarbonImmutable;

final readonly class BackupArtifactRecord
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $targetKey,
        public string $runReference,
        public string $artifactKey,
        public ?CarbonImmutable $retentionUntil,
        public ?CarbonImmutable $createdAtBackup,
        public ?int $sizeBytes,
        public bool $checksumPresent,
        public bool $isEncrypted,
        public bool $isRestoreReady,
        public ?array $metadata,
    ) {}
}
