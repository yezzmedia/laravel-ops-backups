<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use YezzMedia\OpsBackups\Data\BackupArtifactRecord;
use YezzMedia\OpsBackups\Data\BackupFailureRecord;
use YezzMedia\OpsBackups\Data\BackupJobPostureRecord;
use YezzMedia\OpsBackups\Data\BackupPostureSummary;
use YezzMedia\OpsBackups\Data\BackupTargetRecord;
use YezzMedia\OpsBackups\Data\RestoreReadinessRecord;
use YezzMedia\OpsBackups\Data\RetentionPostureRecord;
use YezzMedia\OpsBackups\Models\OpsBackupArtifact;

final class OpsBackupsManager
{
    private CacheRepository $cache;

    private ?BackupPostureSummary $summaryMemo = null;

    public function __construct(
        private readonly BackupInventoryResolver $inventoryResolver,
        private readonly BackupJobPostureResolver $jobResolver,
        private readonly RetentionPostureResolver $retentionResolver,
        private readonly RestoreReadinessResolver $restoreResolver,
        private readonly BackupFailureResolver $failureResolver,
        CacheFactory $cacheFactory,
        private readonly bool $cacheEnabled,
        private readonly ?string $cacheStore,
        private readonly int $cacheTtl,
        private readonly bool $excludeUnsupportedFromAggregation = false,
    ) {
        $this->cache = $cacheFactory->store($cacheStore);
    }

    public function summary(): BackupPostureSummary
    {
        if ($this->summaryMemo instanceof BackupPostureSummary) {
            return $this->summaryMemo;
        }

        if ($this->cacheEnabled) {
            /** @var BackupPostureSummary|null $cached */
            $cached = $this->cache->get($this->cacheKey());

            if ($cached instanceof BackupPostureSummary) {
                return $this->summaryMemo = $cached;
            }
        }

        $summary = $this->computeSummary();

        if ($this->cacheEnabled) {
            $this->cache->put($this->cacheKey(), $summary, $this->cacheTtl);
        }

        return $this->summaryMemo = $summary;
    }

    /**
     * @return array<int, BackupTargetRecord>
     */
    public function targets(): array
    {
        return $this->summary()->targets;
    }

    public function target(string $targetKey): ?BackupTargetRecord
    {
        foreach ($this->targets() as $target) {
            if ($target->targetKey === $targetKey) {
                return $target;
            }
        }

        return null;
    }

    /**
     * @return array<int, BackupJobPostureRecord>
     */
    public function jobsFor(string $targetKey): array
    {
        return $this->jobResolver->resolveForTarget($targetKey);
    }

    /**
     * @return array<int, RetentionPostureRecord>
     */
    public function retentionRecords(): array
    {
        $records = [];

        foreach ($this->targets() as $target) {
            $records[] = $this->retentionFor($target->targetKey);
        }

        return array_values(array_filter($records));
    }

    public function retentionFor(string $targetKey): ?RetentionPostureRecord
    {
        $record = $this->retentionResolver->resolveForTarget($targetKey);

        if ($record instanceof RetentionPostureRecord) {
            return $record;
        }

        if ($this->target($targetKey) === null) {
            return null;
        }

        return new RetentionPostureRecord(
            targetKey: $targetKey,
            status: BackupPostureStatus::Unsupported,
            artifactCountInRetentionWindow: 0,
            nextRetentionExpiryAt: null,
            retentionCoverageDays: null,
            summary: 'No retention metadata is available for this target.',
            issues: ['No retention metadata is available for this target.'],
        );
    }

    /**
     * @return array<int, RestoreReadinessRecord>
     */
    public function restoreReadinessRecords(): array
    {
        return $this->restoreResolver->resolve();
    }

    public function restoreReadinessFor(string $targetKey): ?RestoreReadinessRecord
    {
        return $this->restoreResolver->resolveForTarget($targetKey);
    }

    /**
     * @return array<int, BackupFailureRecord>
     */
    public function failures(int $limit = 10): array
    {
        return $this->failureResolver->resolve($limit);
    }

    /**
     * @return array<int, BackupFailureRecord>
     */
    public function failuresFor(string $targetKey, int $limit = 10): array
    {
        return $this->failureResolver->resolveForTarget($targetKey, $limit);
    }

    /**
     * @return array<int, BackupArtifactRecord>
     */
    public function artifactsFor(string $targetKey): array
    {
        return OpsBackupArtifact::query()
            ->with(['run.target'])
            ->whereHas('run.target', fn ($query) => $query->where('target_key', $targetKey))
            ->orderByDesc('created_at_backup')
            ->get()
            ->map(fn (OpsBackupArtifact $artifact): BackupArtifactRecord => new BackupArtifactRecord(
                targetKey: $targetKey,
                runReference: (string) $artifact->run->getAttribute('run_reference'),
                artifactKey: (string) $artifact->getAttribute('artifact_key'),
                retentionUntil: $this->asImmutable($artifact->getAttribute('retention_until')),
                createdAtBackup: $this->asImmutable($artifact->getAttribute('created_at_backup')),
                sizeBytes: $artifact->getAttribute('size_bytes'),
                checksumPresent: (bool) $artifact->getAttribute('checksum_present'),
                isEncrypted: (bool) $artifact->getAttribute('is_encrypted'),
                isRestoreReady: (bool) $artifact->getAttribute('is_restore_ready'),
                metadata: $artifact->getAttribute('metadata'),
            ))
            ->values()
            ->all();
    }

    public function overallStatus(): BackupPostureStatus
    {
        return $this->summary()->overallStatus;
    }

    public function refresh(): BackupPostureSummary
    {
        $this->summaryMemo = null;
        $this->cache->forget($this->cacheKey());

        return $this->summary();
    }

    private function computeSummary(): BackupPostureSummary
    {
        $targets = $this->inventoryResolver->resolve();
        $statuses = array_map(fn (BackupTargetRecord $target): BackupPostureStatus => $target->postureStatus, $targets);
        $aggregationStatuses = $this->aggregationStatuses($statuses);
        $restoreNotReadyCount = count(array_filter(
            $targets,
            fn (BackupTargetRecord $target): bool => $target->restoreReadinessStatus === RestoreReadinessStatus::NotReady,
        ));

        return new BackupPostureSummary(
            overallStatus: BackupPostureStatus::worst($aggregationStatuses === [] ? [BackupPostureStatus::Unsupported] : $aggregationStatuses),
            targets: $targets,
            failingCount: count(array_filter($statuses, fn (BackupPostureStatus $status): bool => $status === BackupPostureStatus::Failed)),
            warningCount: count(array_filter($statuses, fn (BackupPostureStatus $status): bool => $status === BackupPostureStatus::Warning)),
            unsupportedCount: count(array_filter($statuses, fn (BackupPostureStatus $status): bool => $status === BackupPostureStatus::Unsupported)),
            healthyCount: count(array_filter($statuses, fn (BackupPostureStatus $status): bool => $status === BackupPostureStatus::Healthy)),
            restoreNotReadyCount: $restoreNotReadyCount,
            checkedAt: CarbonImmutable::now(),
        );
    }

    /**
     * @param  array<int, BackupPostureStatus>  $statuses
     * @return array<int, BackupPostureStatus>
     */
    private function aggregationStatuses(array $statuses): array
    {
        if (! $this->excludeUnsupportedFromAggregation) {
            return $statuses;
        }

        return array_values(array_filter($statuses, fn (BackupPostureStatus $status): bool => $status !== BackupPostureStatus::Unsupported));
    }

    private function cacheKey(): string
    {
        return 'ops_backups.summary';
    }

    private function asImmutable(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }
}
