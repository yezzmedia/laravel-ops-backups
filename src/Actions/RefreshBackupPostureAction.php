<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use YezzMedia\OpsBackups\Events\BackupPostureRefreshed;
use YezzMedia\OpsBackups\Support\OpsBackupsManager;

final class RefreshBackupPostureAction
{
    public function __construct(
        private readonly OpsBackupsManager $manager,
        private readonly Dispatcher $events,
    ) {}

    public function execute(string $source = 'manual'): void
    {
        $summary = $this->manager->refresh();

        $this->events->dispatch(new BackupPostureRefreshed(
            overallStatus: $summary->overallStatus->value,
            healthyCount: $summary->healthyCount,
            warningCount: $summary->warningCount,
            failingCount: $summary->failingCount,
            unsupportedCount: $summary->unsupportedCount,
            actorId: Auth::id(),
            source: $source,
            completedAt: ($summary->checkedAt ?? now())->toIso8601String(),
        ));
    }
}
