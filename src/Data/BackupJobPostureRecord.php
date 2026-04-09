<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Data;

use Carbon\CarbonImmutable;
use YezzMedia\OpsBackups\Support\BackupPostureStatus;

final readonly class BackupJobPostureRecord
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(
        public string $targetKey,
        public BackupPostureStatus $status,
        public ?CarbonImmutable $lastRunAt,
        public ?CarbonImmutable $lastSuccessfulRunAt,
        public ?CarbonImmutable $lastFailedRunAt,
        public ?int $durationSeconds,
        public int $artifactCount,
        public ?int $totalBytes,
        public string $summary,
        public array $issues,
    ) {}
}
