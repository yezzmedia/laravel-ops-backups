<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Events;

final readonly class BackupRunRecorded
{
    public function __construct(
        public string $targetKey,
        public string $runKey,
        public string $status,
        public int $artifactCount,
        public string $completedAt,
        public ?int $actorId,
        public string $source,
    ) {}
}
