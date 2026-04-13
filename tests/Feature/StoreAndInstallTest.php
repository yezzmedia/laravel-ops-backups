<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use YezzMedia\Foundation\Data\InstallContext;
use YezzMedia\OpsBackups\Doctor\BackupsStoreReadyCheck;
use YezzMedia\OpsBackups\Install\ConfigureOpsBackupsAuditInstallStep;
use YezzMedia\OpsBackups\Install\EnsureOpsBackupsStoreReadyInstallStep;
use YezzMedia\OpsBackups\Support\OpsBackupsStoreSetup;

it('exposes the real package migration path', function (): void {
    $storeSetup = app(OpsBackupsStoreSetup::class);

    expect($storeSetup->migrationPath())->toBe('/home/yezz/Developement/packages/laravel-ops-backups/database/migrations');
});

it('reports the store as not ready when a required table is missing', function (): void {
    Schema::drop('ops_backup_artifacts');

    $result = app(BackupsStoreReadyCheck::class)->run();

    expect($result->status)->toBe('failed')
        ->and($result->context['missing_tables'])->toBe(['ops_backup_artifacts']);
});

it('refuses to run store migrations when migrations are disabled', function (): void {
    Schema::drop('ops_backup_artifacts');

    $step = app(EnsureOpsBackupsStoreReadyInstallStep::class);

    expect(fn () => $step->handle(new InstallContext(allowMigrations: false)))->toThrow(
        RuntimeException::class,
        'Ops backups store has a partial table set. Resolve the partial state before continuing.',
    );
});

it('reports a partial store when only some required tables exist', function (): void {
    Schema::drop('ops_backup_artifacts');

    $storeSetup = app(OpsBackupsStoreSetup::class);

    expect($storeSetup->hasPartialTables())->toBeTrue()
        ->and($storeSetup->storeReady())->toBeFalse();
});

it('publishes the host config before configuring the backups audit driver', function (): void {
    $path = config_path('ops-backups.php');

    @unlink($path);
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    $step = app(ConfigureOpsBackupsAuditInstallStep::class);

    $step->handle(new InstallContext(auditPackages: ['yezzmedia/laravel-ops-backups']));

    expect($path)->toBeFile()
        ->and(file_get_contents($path))->toContain("'driver' => env('OPS_BACKUPS_AUDIT_DRIVER', 'activitylog'),");
});

it('accepts an already configured backups audit driver in the published host config', function (): void {
    $path = config_path('ops-backups.php');

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }

    file_put_contents($path, <<<'PHP'
<?php

return [
    'audit' => [
        'driver' => env('OPS_BACKUPS_AUDIT_DRIVER', 'activitylog'),
    ],
];
PHP);

    $step = app(ConfigureOpsBackupsAuditInstallStep::class);

    $step->handle(new InstallContext(auditPackages: ['yezzmedia/laravel-ops-backups']));

    expect(file_get_contents($path))->toContain("'driver' => env('OPS_BACKUPS_AUDIT_DRIVER', 'activitylog'),");
});
