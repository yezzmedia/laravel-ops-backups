<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Events;

final readonly class BackupArtifactRecorded
{
    public function __construct(
        public string $targetKey,
        public string $runKey,
        public string $artifactKey,
        public ?string $retentionUntil,
        public bool $isRestoreReady,
        public ?int $actorId,
        public string $source,
        public string $completedAt,
    ) {}
}
