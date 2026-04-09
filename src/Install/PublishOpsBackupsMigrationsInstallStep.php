<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Install;

use YezzMedia\Foundation\Data\InstallContext;
use YezzMedia\Foundation\Install\InstallStep;
use YezzMedia\OpsBackups\Support\OpsBackupsStoreSetup;

final class PublishOpsBackupsMigrationsInstallStep implements InstallStep
{
    public function __construct(private readonly OpsBackupsStoreSetup $storeSetup) {}

    public function key(): string
    {
        return 'publish_ops_backups_migrations';
    }

    public function package(): string
    {
        return 'yezzmedia/laravel-ops-backups';
    }

    public function priority(): int
    {
        return 210;
    }

    public function shouldRun(InstallContext $context): bool
    {
        return ! $this->storeSetup->storeReady();
    }

    public function handle(InstallContext $context): void
    {
        // Package migrations are registered through package-tools.
    }
}
