<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use UnitEnum;
use YezzMedia\OpsBackups\Actions\RefreshBackupPostureAction;
use YezzMedia\OpsBackups\Support\OpsBackupsManager;

class OpsBackupsPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Backups';

    protected static ?string $navigationLabel = 'Backups';

    protected static ?int $navigationSort = 80;

    protected static ?string $title = 'Backup Posture';

    protected static ?string $slug = 'ops-backups';

    public static function canAccess(): bool
    {
        return Gate::check('ops.backups.view');
    }

    public static function getNavigationBadge(): ?string
    {
        return app(OpsBackupsManager::class)->overallStatus()->label();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return app(OpsBackupsManager::class)->overallStatus()->color();
    }

    public function content(Schema $schema): Schema
    {
        $summary = app(OpsBackupsManager::class)->summary();

        return $schema->components([
            $this->overviewSection($summary),
            $this->inventorySection($summary),
            $this->failuresSection(),
            $this->actionsSection(),
        ]);
    }

    private function overviewSection($summary): Section
    {
        return Section::make('Overview')
            ->schema([
                Grid::make(5)->schema([
                    ...$this->labeledText('Overall Status', $summary->overallStatus->label(), color: $summary->overallStatus->color(), icon: $summary->overallStatus->icon(), badge: true),
                    ...$this->labeledText('Targets', (string) count($summary->targets), color: 'gray', badge: true),
                    ...$this->labeledText('Healthy', (string) $summary->healthyCount, color: 'success', badge: true),
                    ...$this->labeledText('Warnings', (string) $summary->warningCount, color: $summary->warningCount > 0 ? 'warning' : 'gray', badge: true),
                    ...$this->labeledText('Failures', (string) $summary->failingCount, color: $summary->failingCount > 0 ? 'danger' : 'gray', badge: true),
                ]),
                ...$this->labeledText('Last checked', ($summary->checkedAt ?? now())->format('Y-m-d H:i:s T'), color: 'gray'),
            ]);
    }

    private function inventorySection($summary): Section
    {
        if ($summary->targets === []) {
            return Section::make('Backup Inventory')
                ->schema([
                    Text::make('No backup targets are currently registered.')
                        ->color('gray'),
                ]);
        }

        return Section::make('Backup Inventory')
            ->schema(
                array_merge(...array_map(function ($target): array {
                    return $this->labeledText(
                        $target->name,
                        sprintf('%s | %s | %s', $target->scopeType->label(), $target->backupDriver ?? 'No driver', $target->summary),
                        color: $target->postureStatus->color(),
                        icon: $target->postureStatus->icon(),
                    );
                }, $summary->targets)),
            );
    }

    private function failuresSection(): Section
    {
        $failures = app(OpsBackupsManager::class)->failures();

        if ($failures === []) {
            return Section::make('Recent Failures')
                ->schema([
                    Text::make('No failed backup runs are currently tracked.')
                        ->color('success'),
                ]);
        }

        return Section::make('Recent Failures')
            ->schema(
                array_merge(...array_map(
                    fn ($record): array => $this->labeledText($record->targetKey, $record->summary, color: 'danger', icon: 'heroicon-o-x-circle'),
                    $failures,
                )),
            );
    }

    /**
     * @return array{Text, Text}
     */
    private function labeledText(
        string $label,
        string $value,
        ?string $color = null,
        ?string $icon = null,
        bool $badge = false,
    ): array {
        $valueText = Text::make($value);

        if ($badge) {
            $valueText = $valueText->badge();
        }

        if ($color !== null) {
            $valueText = $valueText->color($color);
        }

        if ($icon !== null) {
            $valueText = $valueText->icon($icon);
        }

        return [
            Text::make($label)
                ->badge()
                ->color('gray'),
            $valueText,
        ];
    }

    private function actionsSection(): Actions
    {
        return Actions::make([
            Action::make('refresh')
                ->label('Refresh Backup Posture')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Refresh Backup Posture')
                ->modalDescription('This will rebuild the current backup target, run, retention, restore readiness, and failure visibility snapshot.')
                ->visible(fn (): bool => Gate::check('ops.backups.manage'))
                ->action(function (): void {
                    app(RefreshBackupPostureAction::class)->execute('filament');

                    Notification::make()
                        ->success()
                        ->title('Backup posture refreshed')
                        ->send();
                }),
        ]);
    }
}
