<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Doctor;

use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorCheck;
use YezzMedia\OpsBackups\Support\BackupPostureStatus;
use YezzMedia\OpsBackups\Support\OpsBackupsManager;

final class RetentionCoverageCheck implements DoctorCheck
{
    private const KEY = 'retention_coverage';

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
        $records = $this->manager->retentionRecords();

        if ($records === []) {
            return $this->result('warning', 'No retention coverage records are available yet.', false);
        }

        $failed = array_values(array_map(
            fn ($record): string => $record->targetKey,
            array_filter($records, fn ($record): bool => $record->status === BackupPostureStatus::Failed),
        ));

        if ($failed !== []) {
            return $this->result('failed', 'Some backup targets have expired retention coverage.', false, ['target_keys' => $failed]);
        }

        $warning = array_values(array_map(
            fn ($record): string => $record->targetKey,
            array_filter($records, fn ($record): bool => in_array($record->status, [BackupPostureStatus::Warning, BackupPostureStatus::Unsupported], true)),
        ));

        if ($warning !== []) {
            return $this->result('warning', 'Some backup targets need retention coverage review.', false, ['target_keys' => $warning]);
        }

        return $this->result('passed', 'Retention coverage looks healthy for all backup targets.', false);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function result(string $status, string $message, bool $blocking, array $context = []): DoctorResult
    {
        return new DoctorResult(self::KEY, self::PACKAGE, $status, $message, $blocking, $context);
    }
}
