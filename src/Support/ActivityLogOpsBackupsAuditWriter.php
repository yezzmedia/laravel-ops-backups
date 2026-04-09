<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

use InvalidArgumentException;
use Spatie\Activitylog\Support\ActivityLogger;
use YezzMedia\OpsBackups\Contracts\OpsBackupsAuditWriter;
use YezzMedia\OpsBackups\Events\BackupArtifactRecorded;
use YezzMedia\OpsBackups\Events\BackupPostureRefreshed;
use YezzMedia\OpsBackups\Events\BackupRunRecorded;
use YezzMedia\OpsBackups\Events\BackupTargetUpdated;

final class ActivityLogOpsBackupsAuditWriter implements OpsBackupsAuditWriter
{
    public function __construct(private readonly ActivityLogger $activity) {}

    public function record(object $event): void
    {
        if ($event instanceof BackupPostureRefreshed) {
            $this->recordBackupPostureRefreshed($event);

            return;
        }

        if ($event instanceof BackupTargetUpdated) {
            $this->recordBackupTargetUpdated($event);

            return;
        }

        if ($event instanceof BackupRunRecorded) {
            $this->recordBackupRunRecorded($event);

            return;
        }

        if ($event instanceof BackupArtifactRecorded) {
            $this->recordBackupArtifactRecorded($event);

            return;
        }

        throw new InvalidArgumentException(sprintf('Unsupported ops backups audit event [%s].', $event::class));
    }

    private function recordBackupPostureRefreshed(BackupPostureRefreshed $event): void
    {
        $this->activity
            ->useLog(config('ops-backups.audit.log_name', 'ops-backups'))
            ->event('refreshed')
            ->withProperties([
                'overall_status' => $event->overallStatus,
                'healthy_count' => $event->healthyCount,
                'warning_count' => $event->warningCount,
                'failing_count' => $event->failingCount,
                'unsupported_count' => $event->unsupportedCount,
                'actor_id' => $event->actorId,
                'source' => $event->source,
                'completed_at' => $event->completedAt,
            ])
            ->log('Ops backups posture snapshot was refreshed.');
    }

    private function recordBackupTargetUpdated(BackupTargetUpdated $event): void
    {
        $this->activity
            ->useLog(config('ops-backups.audit.log_name', 'ops-backups'))
            ->event('updated')
            ->withProperties([
                'target_key' => $event->targetKey,
                'scope_type' => $event->scopeType,
                'scope_key' => $event->scopeKey,
                'lifecycle_status' => $event->lifecycleStatus,
                'actor_id' => $event->actorId,
                'source' => $event->source,
                'completed_at' => $event->completedAt,
            ])
            ->log('A backup target metadata record was updated.');
    }

    private function recordBackupRunRecorded(BackupRunRecorded $event): void
    {
        $this->activity
            ->useLog(config('ops-backups.audit.log_name', 'ops-backups'))
            ->event('recorded')
            ->withProperties([
                'target_key' => $event->targetKey,
                'run_key' => $event->runKey,
                'status' => $event->status,
                'artifact_count' => $event->artifactCount,
                'completed_at' => $event->completedAt,
                'actor_id' => $event->actorId,
                'source' => $event->source,
            ])
            ->log('A backup run metadata record was recorded.');
    }

    private function recordBackupArtifactRecorded(BackupArtifactRecorded $event): void
    {
        $this->activity
            ->useLog(config('ops-backups.audit.log_name', 'ops-backups'))
            ->event('recorded')
            ->withProperties([
                'target_key' => $event->targetKey,
                'run_key' => $event->runKey,
                'artifact_key' => $event->artifactKey,
                'retention_until' => $event->retentionUntil,
                'is_restore_ready' => $event->isRestoreReady,
                'actor_id' => $event->actorId,
                'source' => $event->source,
                'completed_at' => $event->completedAt,
            ])
            ->log('A backup artifact metadata record was recorded.');
    }
}
