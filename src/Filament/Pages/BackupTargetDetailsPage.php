<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use UnitEnum;
use YezzMedia\OpsBackups\Support\OpsBackupsManager;

class BackupTargetDetailsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $slug = 'ops-backups/detail';

    protected static string|UnitEnum|null $navigationGroup = 'Backups';

    #[Url]
    public string $target = '';

    public static function canAccess(): bool
    {
        return Gate::check('ops.backups.view');
    }

    public function getTitle(): string
    {
        $target = app(OpsBackupsManager::class)->target($this->target);

        return $target === null ? 'Backup Target Detail' : sprintf('Backup Target Detail: %s', $target->name);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Backups')
                ->icon('heroicon-o-arrow-left')
                ->url(OpsBackupsPage::getUrl()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $manager = app(OpsBackupsManager::class);
        $target = $manager->target($this->target);

        if ($target === null) {
            return $schema->components([
                Section::make('Backup Target Summary')
                    ->schema([
                        Text::make('The requested backup target could not be found.')
                            ->color('danger'),
                    ]),
            ]);
        }

        $jobs = $manager->jobsFor($target->targetKey);
        $retention = $manager->retentionFor($target->targetKey);
        $restore = $manager->restoreReadinessFor($target->targetKey);
        $artifacts = $manager->artifactsFor($target->targetKey);
        $failures = $manager->failuresFor($target->targetKey);

        return $schema->components([
            Section::make('Backup Target Summary')
                ->schema([
                    Grid::make(5)->schema([
                        Text::make($target->name)->badge()->color('primary'),
                        Text::make($target->scopeType->label())->badge()->color('gray'),
                        Text::make($target->lifecycleStatus)->badge()->color('gray'),
                        Text::make($target->postureStatus->label())->badge()->color($target->postureStatus->color()),
                        Text::make($target->restoreReadinessStatus->label())->badge()->color($target->restoreReadinessStatus->color()),
                    ]),
                    Text::make($target->summary)->color($target->postureStatus->color()),
                    ...array_map(
                        fn (string $issue): Text => Text::make($issue)->color('gray'),
                        $target->issues,
                    ),
                ]),
            Section::make('Recent Runs')
                ->schema($jobs === []
                    ? [Text::make('No backup runs are currently recorded.')->color('gray')]
                    : array_merge(...array_map(
                        fn ($record): array => [
                            Text::make($record->lastRunAt?->format('Y-m-d H:i:s T') ?? 'No run timestamp')->badge()->color($record->status->color()),
                            Text::make($record->summary)->color($record->status->color()),
                        ],
                        $jobs,
                    ))),
            Section::make('Retention')
                ->schema($retention === null
                    ? [Text::make('No retention posture is currently available.')->color('gray')]
                    : [
                        Text::make($retention->status->label())->badge()->color($retention->status->color()),
                        Text::make($retention->summary)->color($retention->status->color()),
                    ]),
            Section::make('Restore Readiness')
                ->schema($restore === null
                    ? [Text::make('No restore readiness posture is currently available.')->color('gray')]
                    : [
                        Text::make($restore->status->label())->badge()->color($restore->status->color()),
                        Text::make($restore->summary)->color($restore->status->color()),
                    ]),
            Section::make('Artifacts')
                ->schema($artifacts === []
                    ? [Text::make('No artifacts are currently recorded.')->color('gray')]
                    : array_merge(...array_map(
                        fn ($record): array => [
                            Text::make($record->artifactKey)->badge()->color($record->isRestoreReady ? 'success' : 'gray'),
                            Text::make(sprintf('Run %s | checksum: %s | encrypted: %s', $record->runReference, $record->checksumPresent ? 'yes' : 'no', $record->isEncrypted ? 'yes' : 'no'))->color('gray'),
                        ],
                        $artifacts,
                    ))),
            Section::make('Failure History')
                ->schema($failures === []
                    ? [Text::make('No failed backup runs are currently recorded.')->color('success')]
                    : array_merge(...array_map(
                        fn ($record): array => [
                            Text::make($record->runKey ?? 'Unknown run')->badge()->color('danger'),
                            Text::make($record->summary)->color('danger'),
                        ],
                        $failures,
                    ))),
        ]);
    }
}
