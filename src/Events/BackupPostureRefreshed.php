<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Events;

final readonly class BackupPostureRefreshed
{
    public function __construct(
        public string $overallStatus,
        public int $healthyCount,
        public int $warningCount,
        public int $failingCount,
        public int $unsupportedCount,
        public ?int $actorId,
        public string $source,
        public string $completedAt,
    ) {}
}
