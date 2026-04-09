<?php

declare(strict_types=1);

use YezzMedia\Foundation\Registry\FeatureRegistry;
use YezzMedia\Foundation\Registry\OpsModuleRegistry;
use YezzMedia\Foundation\Registry\PackageRegistry;
use YezzMedia\Foundation\Registry\PermissionRegistry;
use YezzMedia\OpsBackups\OpsBackupsPlatformPackage;

it('registers the ops backups package surface', function (): void {
    expect(app(PackageRegistry::class)->has('yezzmedia/laravel-ops-backups'))->toBeTrue()
        ->and(app(PermissionRegistry::class)->forPackage('yezzmedia/laravel-ops-backups')->pluck('name')->all())->toBe([
            'ops.backups.view',
            'ops.backups.manage',
        ])
        ->and(app(FeatureRegistry::class)->forPackage('yezzmedia/laravel-ops-backups')->pluck('name')->all())->toBe([
            'backups.inventory',
            'backups.retention',
            'backups.restore_readiness',
            'backups.failures',
        ])
        ->and(app(OpsModuleRegistry::class)->forPackage('yezzmedia/laravel-ops-backups')->pluck('key')->all())->toBe([
            'diagnostics.backups.overview',
            'diagnostics.backups.detail',
        ]);
});

it('describes the approved ops backups package surface', function (): void {
    $package = new OpsBackupsPlatformPackage;

    expect($package->metadata()->name)->toBe('yezzmedia/laravel-ops-backups')
        ->and($package->permissionDefinitions())->toHaveCount(2)
        ->and($package->featureDefinitions())->toHaveCount(4)
        ->and($package->auditEventDefinitions())->toHaveCount(4)
        ->and($package->installSteps())->toHaveCount(3)
        ->and($package->doctorChecks())->toHaveCount(4)
        ->and($package->opsModuleDefinitions())->toHaveCount(2);
});
