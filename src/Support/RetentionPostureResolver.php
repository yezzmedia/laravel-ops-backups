<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use YezzMedia\OpsBackups\Data\RetentionPostureRecord;
use YezzMedia\OpsBackups\Models\OpsBackupArtifact;

final class RetentionPostureResolver
{
    public function __construct(
        private readonly int $warningDays = 7,
    ) {}

    /**
     * @return array<int, RetentionPostureRecord>
     */
    public function resolve(): array
    {
        return OpsBackupArtifact::query()
            ->with(['run.target'])
            ->get()
            ->groupBy(fn (OpsBackupArtifact $artifact): string => (string) $artifact->run->target->getAttribute('target_key'))
            ->map(fn (Collection $artifacts, string $targetKey): RetentionPostureRecord => $this->mapTargetArtifacts($targetKey, $artifacts))
            ->values()
            ->all();
    }

    public function resolveForTarget(string $targetKey): ?RetentionPostureRecord
    {
        $artifacts = OpsBackupArtifact::query()
            ->with(['run.target'])
            ->whereHas('run.target', fn ($query) => $query->where('target_key', $targetKey))
            ->get();

        if ($artifacts->isEmpty()) {
            return null;
        }

        return $this->mapTargetArtifacts($targetKey, $artifacts);
    }

    /**
     * @param  Collection<int, OpsBackupArtifact>  $artifacts
     */
    private function mapTargetArtifacts(string $targetKey, Collection $artifacts): RetentionPostureRecord
    {
        $now = CarbonImmutable::now();
        $warningThreshold = $now->addDays($this->warningDays);
        $retentionDates = $artifacts
            ->map(fn (OpsBackupArtifact $artifact): ?CarbonImmutable => $this->asImmutable($artifact->getAttribute('retention_until')))
            ->filter();

        $expiredCount = $retentionDates->filter(fn (CarbonImmutable $date): bool => $date->lt($now))->count();
        $expiringSoonCount = $retentionDates->filter(fn (CarbonImmutable $date): bool => $date->gte($now) && $date->lte($warningThreshold))->count();
        $nextRetentionExpiryAt = $retentionDates->sort()->first();
        $retentionCoverageDays = $nextRetentionExpiryAt instanceof CarbonImmutable
            ? (int) max($now->diffInDays($nextRetentionExpiryAt, false), 0)
            : null;

        $status = match (true) {
            $expiredCount > 0 => BackupPostureStatus::Failed,
            $expiringSoonCount > 0 => BackupPostureStatus::Warning,
            $retentionDates->isEmpty() => BackupPostureStatus::Unsupported,
            default => BackupPostureStatus::Healthy,
        };

        $summary = match ($status) {
            BackupPostureStatus::Failed => 'One or more artifacts already passed their retention deadline.',
            BackupPostureStatus::Warning => 'One or more artifacts will expire soon.',
            BackupPostureStatus::Healthy => 'Artifact retention coverage is healthy.',
            BackupPostureStatus::Unsupported => 'No retention metadata is available for this target.',
        };

        $issues = match ($status) {
            BackupPostureStatus::Failed => ['One or more artifacts already passed their retention deadline.'],
            BackupPostureStatus::Warning => ['One or more artifacts will expire soon.'],
            BackupPostureStatus::Healthy => [],
            BackupPostureStatus::Unsupported => ['No retention metadata is available for this target.'],
        };

        return new RetentionPostureRecord(
            targetKey: $targetKey,
            status: $status,
            artifactCountInRetentionWindow: $artifacts->count() - $expiredCount,
            nextRetentionExpiryAt: $nextRetentionExpiryAt,
            retentionCoverageDays: $retentionCoverageDays,
            summary: $summary,
            issues: $issues,
        );
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
