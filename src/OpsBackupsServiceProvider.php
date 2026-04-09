<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher;
use InvalidArgumentException;
use Spatie\Activitylog\Support\ActivityLogger;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use YezzMedia\Foundation\Support\PlatformPackageRegistrar;
use YezzMedia\OpsBackups\Actions\RecordBackupArtifactAction;
use YezzMedia\OpsBackups\Actions\RecordBackupRunAction;
use YezzMedia\OpsBackups\Actions\RefreshBackupPostureAction;
use YezzMedia\OpsBackups\Actions\UpsertBackupTargetAction;
use YezzMedia\OpsBackups\Contracts\OpsBackupsAuditWriter;
use YezzMedia\OpsBackups\Doctor\BackupsStoreReadyCheck;
use YezzMedia\OpsBackups\Doctor\RecentSuccessfulBackupCheck;
use YezzMedia\OpsBackups\Doctor\RestoreArtifactsAvailableCheck;
use YezzMedia\OpsBackups\Doctor\RetentionCoverageCheck;
use YezzMedia\OpsBackups\Events\BackupArtifactRecorded;
use YezzMedia\OpsBackups\Events\BackupPostureRefreshed;
use YezzMedia\OpsBackups\Events\BackupRunRecorded;
use YezzMedia\OpsBackups\Events\BackupTargetUpdated;
use YezzMedia\OpsBackups\Install\ConfigureOpsBackupsAuditInstallStep;
use YezzMedia\OpsBackups\Install\EnsureOpsBackupsStoreReadyInstallStep;
use YezzMedia\OpsBackups\Install\PublishOpsBackupsMigrationsInstallStep;
use YezzMedia\OpsBackups\Listeners\OpsBackupsAuditListener;
use YezzMedia\OpsBackups\Support\ActivityLogOpsBackupsAuditWriter;
use YezzMedia\OpsBackups\Support\BackupFailureResolver;
use YezzMedia\OpsBackups\Support\BackupInventoryResolver;
use YezzMedia\OpsBackups\Support\BackupJobPostureResolver;
use YezzMedia\OpsBackups\Support\NullOpsBackupsAuditWriter;
use YezzMedia\OpsBackups\Support\OpsBackupsManager;
use YezzMedia\OpsBackups\Support\OpsBackupsStoreSetup;
use YezzMedia\OpsBackups\Support\RestoreReadinessResolver;
use YezzMedia\OpsBackups\Support\RetentionPostureResolver;

class OpsBackupsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-ops-backups')
            ->hasConfigFile('ops-backups')
            ->hasMigrations(['0001_create_ops_backups_tables']);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(OpsBackupsAuditWriter::class, fn (): OpsBackupsAuditWriter => $this->makeAuditWriter());

        $this->app->singleton(OpsBackupsStoreSetup::class);

        $this->app->singleton(PublishOpsBackupsMigrationsInstallStep::class, fn (): PublishOpsBackupsMigrationsInstallStep => new PublishOpsBackupsMigrationsInstallStep($this->app->make(OpsBackupsStoreSetup::class)));
        $this->app->singleton(EnsureOpsBackupsStoreReadyInstallStep::class, fn (): EnsureOpsBackupsStoreReadyInstallStep => new EnsureOpsBackupsStoreReadyInstallStep($this->app->make(OpsBackupsStoreSetup::class)));
        $this->app->singleton(ConfigureOpsBackupsAuditInstallStep::class);

        $this->app->singleton(BackupInventoryResolver::class);
        $this->app->singleton(BackupJobPostureResolver::class);
        $this->app->singleton(RetentionPostureResolver::class, fn (): RetentionPostureResolver => new RetentionPostureResolver(
            warningDays: (int) config('ops-backups.retention.warning_days', 7),
        ));
        $this->app->singleton(RestoreReadinessResolver::class, fn (): RestoreReadinessResolver => new RestoreReadinessResolver(
            requireChecksum: (bool) config('ops-backups.restore.require_checksum', true),
            requireEncryption: (bool) config('ops-backups.restore.require_encryption', true),
        ));
        $this->app->singleton(BackupFailureResolver::class);

        $this->app->singleton(OpsBackupsManager::class, function (): OpsBackupsManager {
            return new OpsBackupsManager(
                inventoryResolver: $this->app->make(BackupInventoryResolver::class),
                jobResolver: $this->app->make(BackupJobPostureResolver::class),
                retentionResolver: $this->app->make(RetentionPostureResolver::class),
                restoreResolver: $this->app->make(RestoreReadinessResolver::class),
                failureResolver: $this->app->make(BackupFailureResolver::class),
                cacheFactory: $this->app->make(CacheFactory::class),
                cacheEnabled: (bool) config('ops-backups.cache.enabled', true),
                cacheStore: config('ops-backups.cache.store'),
                cacheTtl: (int) config('ops-backups.cache.ttl', 300),
                excludeUnsupportedFromAggregation: (bool) config('ops-backups.unsupported.exclude_from_aggregation', false),
            );
        });

        $this->app->singleton(BackupsStoreReadyCheck::class, fn (): BackupsStoreReadyCheck => new BackupsStoreReadyCheck($this->app->make(OpsBackupsStoreSetup::class)));
        $this->app->singleton(RecentSuccessfulBackupCheck::class, fn (): RecentSuccessfulBackupCheck => new RecentSuccessfulBackupCheck($this->app->make(OpsBackupsManager::class)));
        $this->app->singleton(RetentionCoverageCheck::class, fn (): RetentionCoverageCheck => new RetentionCoverageCheck($this->app->make(OpsBackupsManager::class)));
        $this->app->singleton(RestoreArtifactsAvailableCheck::class, fn (): RestoreArtifactsAvailableCheck => new RestoreArtifactsAvailableCheck($this->app->make(OpsBackupsManager::class)));

        $this->app->singleton(RefreshBackupPostureAction::class, fn (): RefreshBackupPostureAction => new RefreshBackupPostureAction(
            manager: $this->app->make(OpsBackupsManager::class),
            events: $this->app->make(Dispatcher::class),
        ));
        $this->app->singleton(UpsertBackupTargetAction::class, fn (): UpsertBackupTargetAction => new UpsertBackupTargetAction(
            manager: $this->app->make(OpsBackupsManager::class),
            events: $this->app->make(Dispatcher::class),
        ));
        $this->app->singleton(RecordBackupRunAction::class, fn (): RecordBackupRunAction => new RecordBackupRunAction(
            manager: $this->app->make(OpsBackupsManager::class),
            events: $this->app->make(Dispatcher::class),
        ));
        $this->app->singleton(RecordBackupArtifactAction::class, fn (): RecordBackupArtifactAction => new RecordBackupArtifactAction(
            manager: $this->app->make(OpsBackupsManager::class),
            events: $this->app->make(Dispatcher::class),
        ));
    }

    public function packageBooted(): void
    {
        $this->app->make(PlatformPackageRegistrar::class)->register(new OpsBackupsPlatformPackage);
        $this->registerAuditListeners($this->app->make(Dispatcher::class));
    }

    private function registerAuditListeners(Dispatcher $events): void
    {
        $events->listen(BackupPostureRefreshed::class, [OpsBackupsAuditListener::class, 'handleBackupPostureRefreshed']);
        $events->listen(BackupTargetUpdated::class, [OpsBackupsAuditListener::class, 'handleBackupTargetUpdated']);
        $events->listen(BackupRunRecorded::class, [OpsBackupsAuditListener::class, 'handleBackupRunRecorded']);
        $events->listen(BackupArtifactRecorded::class, [OpsBackupsAuditListener::class, 'handleBackupArtifactRecorded']);
    }

    private function makeAuditWriter(): OpsBackupsAuditWriter
    {
        $driver = config('ops-backups.audit.driver');

        if ($driver === null) {
            return new NullOpsBackupsAuditWriter;
        }

        if ($driver !== 'activitylog') {
            throw new InvalidArgumentException(sprintf('Unsupported ops backups audit driver [%s].', $driver));
        }

        if (! class_exists('Spatie\\Activitylog\\ActivitylogServiceProvider') || ! class_exists(ActivityLogger::class)) {
            throw new InvalidArgumentException('Ops backups audit driver [activitylog] requires spatie/laravel-activitylog.');
        }

        return new ActivityLogOpsBackupsAuditWriter($this->app->make(ActivityLogger::class));
    }
}
