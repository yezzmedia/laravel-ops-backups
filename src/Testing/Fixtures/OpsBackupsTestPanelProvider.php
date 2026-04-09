<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Testing\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use YezzMedia\OpsBackups\Filament\OpsBackupsFilamentPlugin;

final class OpsBackupsTestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('ops-backups-test')
            ->path('ops-backups-test')
            ->authGuard('web')
            ->plugin(OpsBackupsFilamentPlugin::make());
    }
}
