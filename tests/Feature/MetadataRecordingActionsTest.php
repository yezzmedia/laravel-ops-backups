<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use YezzMedia\OpsBackups\Actions\RecordBackupArtifactAction;
use YezzMedia\OpsBackups\Actions\RecordBackupRunAction;
use YezzMedia\OpsBackups\Actions\UpsertBackupTargetAction;
use YezzMedia\OpsBackups\Events\BackupArtifactRecorded;
use YezzMedia\OpsBackups\Events\BackupRunRecorded;
use YezzMedia\OpsBackups\Events\BackupTargetUpdated;
use YezzMedia\OpsBackups\Models\OpsBackupArtifact;
use YezzMedia\OpsBackups\Models\OpsBackupRun;
use YezzMedia\OpsBackups\Models\OpsBackupTarget;

it('upserts a backup target and dispatches an update event', function (): void {
    Event::fake([BackupTargetUpdated::class]);

    $target = app(UpsertBackupTargetAction::class)->execute([
        'target_key' => 'gamma',
        'scope_type' => 'site',
        'scope_key' => 'gamma-site',
        'name' => 'Gamma Backup',
        'lifecycle_status' => 'active',
        'backup_driver' => 's3',
        'backup_destination' => 'gamma-store',
        'is_restore_tested' => true,
        'metadata' => ['tier' => 'gold'],
    ], 'test');

    expect($target)->toBeInstanceOf(OpsBackupTarget::class)
        ->and($target->getAttribute('target_key'))->toBe('gamma');

    Event::assertDispatched(BackupTargetUpdated::class, fn (BackupTargetUpdated $event): bool => $event->targetKey === 'gamma' && $event->source === 'test');
});

it('records a backup run and dispatches a run event', function (): void {
    Event::fake([BackupRunRecorded::class]);

    $target = OpsBackupTarget::query()->create([
        'target_key' => 'gamma',
        'scope_type' => 'site',
        'scope_key' => 'gamma-site',
        'name' => 'Gamma Backup',
        'lifecycle_status' => 'active',
        'backup_driver' => 's3',
        'backup_destination' => 'gamma-store',
    ]);

    $run = app(RecordBackupRunAction::class)->execute($target, [
        'run_key' => 'run-gamma-001',
        'status' => 'healthy',
        'artifact_count' => 1,
        'summary' => 'Backup completed successfully.',
        'completed_at' => now()->toIso8601String(),
    ], 'test');

    expect($run)->toBeInstanceOf(OpsBackupRun::class)
        ->and($run->getAttribute('run_reference'))->toBe('run-gamma-001');

    Event::assertDispatched(BackupRunRecorded::class, fn (BackupRunRecorded $event): bool => $event->targetKey === 'gamma' && $event->runKey === 'run-gamma-001');
});

it('records an artifact and dispatches an artifact event', function (): void {
    Event::fake([BackupArtifactRecorded::class]);

    $target = OpsBackupTarget::query()->create([
        'target_key' => 'gamma',
        'scope_type' => 'site',
        'scope_key' => 'gamma-site',
        'name' => 'Gamma Backup',
        'lifecycle_status' => 'active',
        'backup_driver' => 's3',
        'backup_destination' => 'gamma-store',
    ]);

    OpsBackupRun::query()->create([
        'target_id' => $target->getKey(),
        'run_reference' => 'run-gamma-001',
        'status' => 'healthy',
        'artifact_count' => 0,
    ]);

    $artifact = app(RecordBackupArtifactAction::class)->execute($target, 'run-gamma-001', [
        'artifact_key' => 'artifact-gamma-001',
        'retention_until' => now()->addDays(14)->toIso8601String(),
        'created_at_backup' => now()->toIso8601String(),
        'checksum_present' => true,
        'is_encrypted' => true,
        'is_restore_ready' => true,
    ], 'test');

    expect($artifact)->toBeInstanceOf(OpsBackupArtifact::class)
        ->and($artifact->getAttribute('artifact_key'))->toBe('artifact-gamma-001')
        ->and(OpsBackupRun::query()->where('run_reference', 'run-gamma-001')->value('artifact_count'))->toBe(1);

    Event::assertDispatched(BackupArtifactRecorded::class, fn (BackupArtifactRecorded $event): bool => $event->targetKey === 'gamma' && $event->runKey === 'run-gamma-001' && $event->artifactKey === 'artifact-gamma-001');
});
