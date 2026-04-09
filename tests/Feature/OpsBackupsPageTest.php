<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Schemas\Components\Actions as ActionsComponent;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use YezzMedia\OpsBackups\Doctor\BackupsStoreReadyCheck;
use YezzMedia\OpsBackups\Doctor\RecentSuccessfulBackupCheck;
use YezzMedia\OpsBackups\Doctor\RestoreArtifactsAvailableCheck;
use YezzMedia\OpsBackups\Doctor\RetentionCoverageCheck;
use YezzMedia\OpsBackups\Filament\Pages\BackupTargetDetailsPage;
use YezzMedia\OpsBackups\Filament\Pages\OpsBackupsPage;
use YezzMedia\OpsBackups\Models\OpsBackupArtifact;
use YezzMedia\OpsBackups\Models\OpsBackupRun;
use YezzMedia\OpsBackups\Models\OpsBackupTarget;
use YezzMedia\OpsBackups\Support\BackupPostureStatus;
use YezzMedia\OpsBackups\Support\OpsBackupsManager;
use YezzMedia\OpsBackups\Support\RestoreReadinessStatus;
use YezzMedia\OpsBackups\Testing\Fixtures\TestOpsBackupsUser;

beforeEach(function (): void {
    auth()->guard('web')->login(TestOpsBackupsUser::fixture([
        'ops.backups.view',
        'ops.backups.manage',
    ]));

    $alpha = OpsBackupTarget::query()->create([
        'target_key' => 'alpha',
        'scope_type' => 'site',
        'scope_key' => 'alpha-site',
        'name' => 'Alpha Backup',
        'lifecycle_status' => 'active',
        'backup_driver' => 's3',
        'backup_destination' => 'primary-store',
        'is_restore_tested' => true,
    ]);

    $alphaRun = OpsBackupRun::query()->create([
        'target_id' => $alpha->getKey(),
        'run_reference' => 'run-alpha-001',
        'status' => 'healthy',
        'started_at' => CarbonImmutable::now()->subMinutes(12),
        'completed_at' => CarbonImmutable::now()->subMinutes(10),
        'duration_seconds' => 120,
        'artifact_count' => 1,
        'total_bytes' => 2048,
    ]);

    OpsBackupArtifact::query()->create([
        'run_id' => $alphaRun->getKey(),
        'artifact_key' => 'artifact-alpha-001',
        'retention_until' => CarbonImmutable::now()->addDays(30),
        'created_at_backup' => CarbonImmutable::now()->subMinutes(10),
        'size_bytes' => 2048,
        'checksum_present' => true,
        'is_encrypted' => true,
        'is_restore_ready' => true,
    ]);

    $beta = OpsBackupTarget::query()->create([
        'target_key' => 'beta',
        'scope_type' => 'resource',
        'scope_key' => 'beta-db',
        'name' => 'Beta Backup',
        'lifecycle_status' => 'active',
        'backup_driver' => 's3',
        'backup_destination' => 'secondary-store',
        'is_restore_tested' => false,
    ]);

    OpsBackupRun::query()->create([
        'target_id' => $beta->getKey(),
        'run_reference' => 'run-beta-001',
        'status' => 'failed',
        'started_at' => CarbonImmutable::now()->subMinutes(20),
        'completed_at' => CarbonImmutable::now()->subMinutes(18),
        'duration_seconds' => 120,
        'artifact_count' => 0,
        'error_summary' => 'Snapshot upload failed.',
    ]);
});

it('builds the ops backups page schema', function (): void {
    $page = app(OpsBackupsPage::class);
    $schema = $page->content(Schema::make($page));
    $components = $schema->getComponents(withActions: false, withHidden: true);

    expect($components)->toHaveCount(4)
        ->and($components[0])->toBeInstanceOf(Section::class)
        ->and($components[0]->getHeading())->toBe('Overview')
        ->and($components[1])->toBeInstanceOf(Section::class)
        ->and($components[1]->getHeading())->toBe('Backup Inventory')
        ->and($components[3])->toBeInstanceOf(ActionsComponent::class);
});

it('returns the expected backup summary and detail records', function (): void {
    $manager = app(OpsBackupsManager::class);
    $summary = $manager->summary();
    $target = $manager->target('alpha');

    expect(count($summary->targets))->toBe(2)
        ->and($summary->healthyCount)->toBe(1)
        ->and($summary->warningCount)->toBe(0)
        ->and($summary->failingCount)->toBe(1)
        ->and($summary->restoreNotReadyCount)->toBe(1)
        ->and($target)->not->toBeNull()
        ->and($target?->name)->toBe('Alpha Backup')
        ->and($target?->postureStatus)->toBe(BackupPostureStatus::Healthy)
        ->and($target?->restoreReadinessStatus)->toBe(RestoreReadinessStatus::Ready)
        ->and($target?->summary)->toBe('Recent backup run completed successfully.')
        ->and($manager->jobsFor('alpha'))->toHaveCount(1)
        ->and($manager->artifactsFor('alpha'))->toHaveCount(1)
        ->and($manager->retentionFor('alpha')?->status)->toBe(BackupPostureStatus::Healthy)
        ->and($manager->restoreReadinessFor('alpha')?->status)->toBe(RestoreReadinessStatus::Ready)
        ->and($manager->failures())->toHaveCount(1);
});

it('builds the backup target details page schema for a tracked target', function (): void {
    $page = app(BackupTargetDetailsPage::class);
    $page->target = 'alpha';

    $schema = $page->content(Schema::make($page));
    $components = $schema->getComponents();

    expect($page->getTitle())->toBe('Backup Target Detail: Alpha Backup')
        ->and($components)->toHaveCount(6)
        ->and($components[0]->getHeading())->toBe('Backup Target Summary')
        ->and($components[5]->getHeading())->toBe('Failure History');
});

it('shows a fallback detail message for an unknown backup target', function (): void {
    $page = app(BackupTargetDetailsPage::class);
    $page->target = 'missing-target';

    $schema = $page->content(Schema::make($page));
    $components = $schema->getComponents();

    expect($page->getTitle())->toBe('Backup Target Detail')
        ->and($components)->toHaveCount(1)
        ->and($components[0]->getHeading())->toBe('Backup Target Summary');
});

it('reports doctor results from the seeded backup state', function (): void {
    $storeCheck = app(BackupsStoreReadyCheck::class)->run();
    $recentCheck = app(RecentSuccessfulBackupCheck::class)->run();
    $retentionCheck = app(RetentionCoverageCheck::class)->run();
    $restoreCheck = app(RestoreArtifactsAvailableCheck::class)->run();

    expect($storeCheck->status)->toBe('passed')
        ->and($recentCheck->status)->toBe('warning')
        ->and($recentCheck->context['target_keys'])->toBe(['beta'])
        ->and($retentionCheck->status)->toBe('warning')
        ->and($restoreCheck->status)->toBe('failed')
        ->and($restoreCheck->context['target_keys'])->toBe(['beta']);
});
