<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use YezzMedia\OpsBackups\Filament\Pages\BackupTargetDetailsPage;
use YezzMedia\OpsBackups\Filament\Pages\OpsBackupsPage;

final class OpsBackupsFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public function getId(): string
    {
        return 'ops-backups';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            OpsBackupsPage::class,
            BackupTargetDetailsPage::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
