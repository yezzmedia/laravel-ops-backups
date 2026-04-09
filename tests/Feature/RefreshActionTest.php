<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use YezzMedia\OpsBackups\Actions\RefreshBackupPostureAction;
use YezzMedia\OpsBackups\Events\BackupPostureRefreshed;
use YezzMedia\OpsBackups\Models\OpsBackupArtifact;
use YezzMedia\OpsBackups\Models\OpsBackupRun;
use YezzMedia\OpsBackups\Models\OpsBackupTarget;

it('dispatches a backup posture refreshed event on refresh', function (): void {
    Event::fake([BackupPostureRefreshed::class]);

    $target = OpsBackupTarget::query()->create([
        'target_key' => 'alpha',
        'scope_type' => 'site',
        'scope_key' => 'alpha',
        'name' => 'Alpha Backup',
        'lifecycle_status' => 'active',
        'backup_driver' => 's3',
        'backup_destination' => 'primary-store',
        'is_restore_tested' => true,
    ]);

    $run = OpsBackupRun::query()->create([
        'target_id' => $target->getKey(),
        'run_reference' => 'run-001',
        'status' => 'healthy',
        'started_at' => CarbonImmutable::now()->subMinutes(10),
        'completed_at' => CarbonImmutable::now()->subMinutes(5),
        'duration_seconds' => 300,
        'artifact_count' => 1,
    ]);

    OpsBackupArtifact::query()->create([
        'run_id' => $run->getKey(),
        'artifact_key' => 'artifact-001',
        'retention_until' => CarbonImmutable::now()->addDays(30),
        'created_at_backup' => CarbonImmutable::now()->subMinutes(5),
        'checksum_present' => true,
        'is_encrypted' => true,
        'is_restore_ready' => true,
    ]);

    app(RefreshBackupPostureAction::class)->execute('test');

    Event::assertDispatched(BackupPostureRefreshed::class, function (BackupPostureRefreshed $event): bool {
        return $event->overallStatus === 'healthy'
            && $event->healthyCount === 1
            && $event->warningCount === 0
            && $event->failingCount === 0
            && $event->unsupportedCount === 0
            && $event->source === 'test'
            && $event->completedAt !== '';
    });
});
