<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Data;

use Carbon\CarbonImmutable;
use YezzMedia\OpsBackups\Support\BackupPostureStatus;
use YezzMedia\OpsBackups\Support\BackupScopeType;
use YezzMedia\OpsBackups\Support\RestoreReadinessStatus;

final readonly class BackupTargetRecord
{
    /**
     * @param  array<int, string>  $issues
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $targetKey,
        public BackupScopeType $scopeType,
        public string $scopeKey,
        public string $name,
        public string $lifecycleStatus,
        public ?string $backupDriver,
        public ?string $backupDestination,
        public BackupPostureStatus $postureStatus,
        public RestoreReadinessStatus $restoreReadinessStatus,
        public ?CarbonImmutable $lastSuccessfulBackupAt,
        public ?CarbonImmutable $lastFailedBackupAt,
        public ?string $retentionSummary,
        public string $summary,
        public array $issues,
        public ?array $metadata,
    ) {}
}
