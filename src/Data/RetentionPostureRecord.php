<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Data;

use Carbon\CarbonImmutable;
use YezzMedia\OpsBackups\Support\BackupPostureStatus;

final readonly class RetentionPostureRecord
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(
        public string $targetKey,
        public BackupPostureStatus $status,
        public int $artifactCountInRetentionWindow,
        public ?CarbonImmutable $nextRetentionExpiryAt,
        public ?int $retentionCoverageDays,
        public string $summary,
        public array $issues,
    ) {}
}
