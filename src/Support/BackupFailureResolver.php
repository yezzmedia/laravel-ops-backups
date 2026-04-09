<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

use Carbon\CarbonImmutable;
use YezzMedia\OpsBackups\Data\BackupFailureRecord;
use YezzMedia\OpsBackups\Models\OpsBackupRun;

final class BackupFailureResolver
{
    /**
     * @return array<int, BackupFailureRecord>
     */
    public function resolve(int $limit = 10): array
    {
        return OpsBackupRun::query()
            ->with('target')
            ->where('status', BackupPostureStatus::Failed->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (OpsBackupRun $run): BackupFailureRecord => new BackupFailureRecord(
                targetKey: (string) $run->target->getAttribute('target_key'),
                runKey: (string) $run->getAttribute('run_reference'),
                occurredAt: $this->asImmutable($run->getAttribute('completed_at') ?? $run->getAttribute('started_at')),
                summary: (string) ($run->getAttribute('error_summary') ?: 'Backup run failed.'),
                details: [
                    'status' => (string) $run->getAttribute('status'),
                    'artifact_count' => (int) $run->getAttribute('artifact_count'),
                ],
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, BackupFailureRecord>
     */
    public function resolveForTarget(string $targetKey, int $limit = 10): array
    {
        return OpsBackupRun::query()
            ->whereHas('target', fn ($query) => $query->where('target_key', $targetKey))
            ->where('status', BackupPostureStatus::Failed->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get()
            ->map(fn (OpsBackupRun $run): BackupFailureRecord => new BackupFailureRecord(
                targetKey: $targetKey,
                runKey: (string) $run->getAttribute('run_reference'),
                occurredAt: $this->asImmutable($run->getAttribute('completed_at') ?? $run->getAttribute('started_at')),
                summary: (string) ($run->getAttribute('error_summary') ?: 'Backup run failed.'),
                details: [
                    'status' => (string) $run->getAttribute('status'),
                    'artifact_count' => (int) $run->getAttribute('artifact_count'),
                ],
            ))
            ->values()
            ->all();
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
