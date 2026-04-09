<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Doctor;

use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorCheck;
use YezzMedia\OpsBackups\Support\OpsBackupsManager;
use YezzMedia\OpsBackups\Support\RestoreReadinessStatus;

final class RestoreArtifactsAvailableCheck implements DoctorCheck
{
    private const KEY = 'restore_artifacts_available';

    private const PACKAGE = 'yezzmedia/laravel-ops-backups';

    public function __construct(private readonly OpsBackupsManager $manager) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function package(): string
    {
        return self::PACKAGE;
    }

    public function run(): DoctorResult
    {
        $records = $this->manager->restoreReadinessRecords();

        if ($records === []) {
            return $this->result('warning', 'No restore readiness records are available yet.', false);
        }

        $notReady = array_values(array_map(
            fn ($record): string => $record->targetKey,
            array_filter($records, fn ($record): bool => $record->status === RestoreReadinessStatus::NotReady),
        ));

        if ($notReady !== []) {
            return $this->result('failed', 'Some backup targets do not have restore-ready artifacts.', false, ['target_keys' => $notReady]);
        }

        $warning = array_values(array_map(
            fn ($record): string => $record->targetKey,
            array_filter($records, fn ($record): bool => $record->status === RestoreReadinessStatus::Warning),
        ));

        if ($warning !== []) {
            return $this->result('warning', 'Some backup targets need restore readiness review.', false, ['target_keys' => $warning]);
        }

        return $this->result('passed', 'Restore-ready artifacts are available for all backup targets.', false);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function result(string $status, string $message, bool $blocking, array $context = []): DoctorResult
    {
        return new DoctorResult(self::KEY, self::PACKAGE, $status, $message, $blocking, $context);
    }
}
