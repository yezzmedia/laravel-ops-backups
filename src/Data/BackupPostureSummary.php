<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Data;

use Carbon\CarbonImmutable;
use YezzMedia\OpsBackups\Support\BackupPostureStatus;

final readonly class BackupPostureSummary
{
    /**
     * @param  array<int, BackupTargetRecord>  $targets
     */
    public function __construct(
        public BackupPostureStatus $overallStatus,
        public array $targets,
        public int $failingCount,
        public int $warningCount,
        public int $unsupportedCount,
        public int $healthyCount,
        public int $restoreNotReadyCount,
        public ?CarbonImmutable $checkedAt,
    ) {}
}
