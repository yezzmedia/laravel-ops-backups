<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

use Carbon\CarbonImmutable;
use YezzMedia\OpsBackups\Data\BackupTargetRecord;
use YezzMedia\OpsBackups\Models\OpsBackupRun;
use YezzMedia\OpsBackups\Models\OpsBackupTarget;

final class BackupInventoryResolver
{
    /**
     * @return array<int, BackupTargetRecord>
     */
    public function resolve(): array
    {
        return OpsBackupTarget::query()
            ->with(['runs.artifacts'])
            ->orderBy('name')
            ->get()
            ->map(fn (OpsBackupTarget $target): BackupTargetRecord => $this->mapTarget($target))
            ->values()
            ->all();
    }

    private function mapTarget(OpsBackupTarget $target): BackupTargetRecord
    {
        $runs = $target->runs->sortByDesc(fn ($run) => $run->getAttribute('completed_at') ?? $run->getAttribute('started_at'));
        $latestRun = $runs
            ->sortByDesc(fn ($run) => $run->getAttribute('completed_at') ?? $run->getAttribute('started_at'))
            ->first();
        $lastSuccessfulRun = $runs->first(fn ($run) => (string) $run->getAttribute('status') === BackupPostureStatus::Healthy->value);
        $lastFailedRun = $runs->first(fn ($run) => (string) $run->getAttribute('status') === BackupPostureStatus::Failed->value);

        $postureStatus = $this->resolvePostureStatus($target, $latestRun);
        $restoreStatus = $this->resolveRestoreStatus($target, $latestRun);
        $retentionSummary = $this->retentionSummary($latestRun);
        $issues = $this->issuesFor($postureStatus, $restoreStatus, $latestRun, $retentionSummary);

        return new BackupTargetRecord(
            targetKey: (string) $target->getAttribute('target_key'),
            scopeType: BackupScopeType::tryFrom((string) $target->getAttribute('scope_type')) ?? BackupScopeType::Resource,
            scopeKey: (string) $target->getAttribute('scope_key'),
            name: (string) $target->getAttribute('name'),
            lifecycleStatus: (string) $target->getAttribute('lifecycle_status'),
            backupDriver: $this->nullableString($target->getAttribute('backup_driver')),
            backupDestination: $this->nullableString($target->getAttribute('backup_destination')),
            postureStatus: $postureStatus,
            restoreReadinessStatus: $restoreStatus,
            lastSuccessfulBackupAt: $this->runMoment($lastSuccessfulRun),
            lastFailedBackupAt: $this->runMoment($lastFailedRun),
            retentionSummary: $retentionSummary,
            summary: $this->resolveSummary($postureStatus, $restoreStatus, $target, $latestRun),
            issues: $issues,
            metadata: $target->getAttribute('metadata'),
        );
    }

    private function resolvePostureStatus(OpsBackupTarget $target, mixed $latestRun): BackupPostureStatus
    {
        if ($this->nullableString($target->getAttribute('backup_driver')) === null) {
            return BackupPostureStatus::Unsupported;
        }

        if ($latestRun === null) {
            return BackupPostureStatus::Warning;
        }

        return match ((string) $latestRun->getAttribute('status')) {
            'healthy' => BackupPostureStatus::Healthy,
            'warning' => BackupPostureStatus::Warning,
            'failed' => BackupPostureStatus::Failed,
            default => BackupPostureStatus::Unsupported,
        };
    }

    private function resolveRestoreStatus(OpsBackupTarget $target, mixed $latestRun): RestoreReadinessStatus
    {
        if ($latestRun === null) {
            return RestoreReadinessStatus::NotReady;
        }

        $artifacts = $latestRun->artifacts ?? collect();

        if ($artifacts->isEmpty()) {
            return RestoreReadinessStatus::NotReady;
        }

        if ((bool) $target->getAttribute('is_restore_tested')) {
            return RestoreReadinessStatus::Ready;
        }

        $readyArtifacts = $artifacts->every(fn ($artifact): bool => (bool) $artifact->getAttribute('is_restore_ready'));

        return $readyArtifacts ? RestoreReadinessStatus::Warning : RestoreReadinessStatus::NotReady;
    }

    private function resolveSummary(BackupPostureStatus $status, RestoreReadinessStatus $restoreStatus, OpsBackupTarget $target, mixed $latestRun): string
    {
        if ($status === BackupPostureStatus::Failed) {
            return (string) ($latestRun?->getAttribute('error_summary') ?: 'Recent backup run failed.');
        }

        if ($status === BackupPostureStatus::Unsupported) {
            return 'Backup posture is not configured for this target.';
        }

        if ($restoreStatus === RestoreReadinessStatus::NotReady) {
            return 'Backup metadata exists, but restore readiness is not satisfied.';
        }

        if ($restoreStatus === RestoreReadinessStatus::Warning) {
            return (bool) $target->getAttribute('is_restore_tested')
                ? 'Backup posture is available but restore readiness still needs review.'
                : 'Backup posture is available, but no restore test has been recorded.';
        }

        return match ($status) {
            BackupPostureStatus::Healthy => 'Recent backup run completed successfully.',
            BackupPostureStatus::Warning => $latestRun === null
                ? 'No backup runs have been recorded yet.'
                : 'Recent backup run needs operator review.',
            BackupPostureStatus::Failed, BackupPostureStatus::Unsupported => 'Backup posture needs operator review.',
        };
    }

    private function retentionSummary(mixed $latestRun): ?string
    {
        if ($latestRun === null) {
            return null;
        }

        $artifactCount = (int) $latestRun->getAttribute('artifact_count');

        return $artifactCount === 0
            ? 'No retained artifacts are recorded for the latest run.'
            : sprintf('%d retained artifact(s) are recorded for the latest run.', $artifactCount);
    }

    /**
     * @return array<int, string>
     */
    private function issuesFor(BackupPostureStatus $postureStatus, RestoreReadinessStatus $restoreStatus, mixed $latestRun, ?string $retentionSummary): array
    {
        $issues = [];

        if ($postureStatus === BackupPostureStatus::Warning) {
            $issues[] = $latestRun === null
                ? 'No backup runs have been recorded yet.'
                : 'Recent backup run needs operator review.';
        }

        if ($postureStatus === BackupPostureStatus::Failed) {
            $issues[] = (string) ($latestRun?->getAttribute('error_summary') ?: 'Recent backup run failed.');
        }

        if ($postureStatus === BackupPostureStatus::Unsupported) {
            $issues[] = 'Backup posture is not configured for this target.';
        }

        if ($restoreStatus === RestoreReadinessStatus::Warning) {
            $issues[] = 'Restore readiness evidence is partial.';
        }

        if ($restoreStatus === RestoreReadinessStatus::NotReady) {
            $issues[] = 'Restore readiness is currently not satisfied.';
        }

        if ($retentionSummary !== null && str_contains($retentionSummary, 'No retained artifacts')) {
            $issues[] = $retentionSummary;
        }

        return array_values(array_unique($issues));
    }

    private function runMoment(?OpsBackupRun $run): ?CarbonImmutable
    {
        if ($run === null) {
            return null;
        }

        return $this->asImmutable($run->getAttribute('completed_at') ?? $run->getAttribute('started_at'));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
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
