<?php

declare(strict_types=1);

use Filament\Panel;
use YezzMedia\OpsBackups\Filament\OpsBackupsFilamentPlugin;
use YezzMedia\OpsBackups\Filament\Pages\BackupTargetDetailsPage;
use YezzMedia\OpsBackups\Filament\Pages\OpsBackupsPage;

it('registers the ops backups plugin id', function (): void {
    expect(OpsBackupsFilamentPlugin::make()->getId())->toBe('ops-backups');
});

it('registers ops backups pages on a panel', function (): void {
    $registeredPages = [];
    $panelMock = Mockery::mock(Panel::class);
    $panelMock->shouldReceive('pages')
        ->once()
        ->withArgs(function (array $pages) use (&$registeredPages): bool {
            $registeredPages = $pages;

            return true;
        })
        ->andReturnSelf();

    OpsBackupsFilamentPlugin::make()->register($panelMock);

    expect($registeredPages)->toBe([
        OpsBackupsPage::class,
        BackupTargetDetailsPage::class,
    ]);
});
