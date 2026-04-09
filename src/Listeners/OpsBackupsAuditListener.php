<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Listeners;

use YezzMedia\OpsBackups\Contracts\OpsBackupsAuditWriter;
use YezzMedia\OpsBackups\Events\BackupArtifactRecorded;
use YezzMedia\OpsBackups\Events\BackupPostureRefreshed;
use YezzMedia\OpsBackups\Events\BackupRunRecorded;
use YezzMedia\OpsBackups\Events\BackupTargetUpdated;

final class OpsBackupsAuditListener
{
    public function __construct(private readonly OpsBackupsAuditWriter $writer) {}

    public function handleBackupPostureRefreshed(BackupPostureRefreshed $event): void
    {
        $this->writer->record($event);
    }

    public function handleBackupTargetUpdated(BackupTargetUpdated $event): void
    {
        $this->writer->record($event);
    }

    public function handleBackupRunRecorded(BackupRunRecorded $event): void
    {
        $this->writer->record($event);
    }

    public function handleBackupArtifactRecorded(BackupArtifactRecorded $event): void
    {
        $this->writer->record($event);
    }
}
