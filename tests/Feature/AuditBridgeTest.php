<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use YezzMedia\OpsBackups\Contracts\OpsBackupsAuditWriter;
use YezzMedia\OpsBackups\Events\BackupPostureRefreshed;
use YezzMedia\OpsBackups\Support\ActivityLogOpsBackupsAuditWriter;
use YezzMedia\OpsBackups\Support\NullOpsBackupsAuditWriter;

it('binds the null audit writer by default', function (): void {
    expect(app(OpsBackupsAuditWriter::class))->toBeInstanceOf(NullOpsBackupsAuditWriter::class);
});

it('null audit writer accepts backup posture events', function (): void {
    $writer = new NullOpsBackupsAuditWriter;

    $writer->record(new BackupPostureRefreshed(
        overallStatus: 'failed',
        healthyCount: 1,
        warningCount: 0,
        failingCount: 1,
        unsupportedCount: 0,
        actorId: 7,
        source: 'test',
        completedAt: '2026-04-09T12:00:00+00:00',
    ));

    expect(true)->toBeTrue();
});

it('binds the activitylog audit writer when configured', function (): void {
    if (! class_exists(Activity::class)) {
        $this->markTestSkipped('spatie/laravel-activitylog is not installed in the package environment.');
    }

    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', static function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->string('event')->nullable();
            $table->json('attribute_changes')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    config()->set('ops-backups.audit.driver', 'activitylog');
    app()->forgetInstance(OpsBackupsAuditWriter::class);

    $writer = app(OpsBackupsAuditWriter::class);

    expect($writer)->toBeInstanceOf(ActivityLogOpsBackupsAuditWriter::class);

    $writer->record(new BackupPostureRefreshed(
        overallStatus: 'failed',
        healthyCount: 1,
        warningCount: 1,
        failingCount: 1,
        unsupportedCount: 1,
        actorId: 7,
        source: 'ops_panel',
        completedAt: '2026-04-09T12:30:00+00:00',
    ));

    $activity = Activity::query()->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity?->log_name)->toBe('ops-backups')
        ->and($activity?->event)->toBe('refreshed')
        ->and($activity?->description)->toBe('Ops backups posture snapshot was refreshed.')
        ->and($activity?->getProperty('overall_status'))->toBe('failed')
        ->and($activity?->getProperty('failing_count'))->toBe(1)
        ->and($activity?->getProperty('unsupported_count'))->toBe(1)
        ->and($activity?->getProperty('source'))->toBe('ops_panel');
});
