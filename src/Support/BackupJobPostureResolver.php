<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

use Carbon\CarbonImmutable;
use YezzMedia\OpsBackups\Data\BackupJobPostureRecord;
use YezzMedia\OpsBackups\Models\OpsBackupRun;

final class BackupJobPostureResolver
{
    /**
     * @return array<int, BackupJobPostureRecord>
     */
    public function resolveForTarget(string $targetKey): array
    {
        $runs = OpsBackupRun::query()
            ->whereHas('target', fn ($query) => $query->where('target_key', $targetKey))
            ->orderByDesc('completed_at')
            ->orderByDesc('started_at')
            ->get();

        $lastRun = $runs->first();
        $lastSuccessfulRun = $runs->first(fn (OpsBackupRun $run): bool => (string) $run->getAttribute('status') === BackupPostureStatus::Healthy->value);
        $lastFailedRun = $runs->first(fn (OpsBackupRun $run): bool => (string) $run->getAttribute('status') === BackupPostureStatus::Failed->value);

        return $runs
            ->map(fn (OpsBackupRun $run): BackupJobPostureRecord => new BackupJobPostureRecord(
                targetKey: $targetKey,
                status: BackupPostureStatus::tryFrom((string) $run->getAttribute('status')) ?? BackupPostureStatus::Unsupported,
                lastRunAt: $this->runMoment($lastRun),
                lastSuccessfulRunAt: $this->runMoment($lastSuccessfulRun),
                lastFailedRunAt: $this->runMoment($lastFailedRun),
                durationSeconds: $run->getAttribute('duration_seconds'),
                artifactCount: (int) $run->getAttribute('artifact_count'),
                totalBytes: $run->getAttribute('total_bytes'),
                summary: $this->summaryFor($run),
                issues: $this->issuesFor($run),
            ))
            ->values()
            ->all();
    }

    private function summaryFor(OpsBackupRun $run): string
    {
        $status = BackupPostureStatus::tryFrom((string) $run->getAttribute('status')) ?? BackupPostureStatus::Unsupported;

        return match ($status) {
            BackupPostureStatus::Healthy => 'Backup run completed successfully.',
            BackupPostureStatus::Warning => 'Backup run completed with warnings.',
            BackupPostureStatus::Failed => (string) ($run->getAttribute('error_summary') ?: 'Backup run failed.'),
            BackupPostureStatus::Unsupported => 'Backup run posture is unsupported.',
        };
    }

    /**
     * @return array<int, string>
     */
    private function issuesFor(OpsBackupRun $run): array
    {
        $status = BackupPostureStatus::tryFrom((string) $run->getAttribute('status')) ?? BackupPostureStatus::Unsupported;

        return match ($status) {
            BackupPostureStatus::Healthy => [],
            BackupPostureStatus::Warning => ['Backup run completed with warnings.'],
            BackupPostureStatus::Failed => [(string) ($run->getAttribute('error_summary') ?: 'Backup run failed.')],
            BackupPostureStatus::Unsupported => ['Backup run posture is unsupported.'],
        };
    }

    private function runMoment(?OpsBackupRun $run): ?CarbonImmutable
    {
        if ($run === null) {
            return null;
        }

        return $this->asImmutable($run->getAttribute('completed_at') ?? $run->getAttribute('started_at'));
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
