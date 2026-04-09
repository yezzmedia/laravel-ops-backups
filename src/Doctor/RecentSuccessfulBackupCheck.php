<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Doctor;

use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorCheck;
use YezzMedia\OpsBackups\Support\BackupPostureStatus;
use YezzMedia\OpsBackups\Support\OpsBackupsManager;

final class RecentSuccessfulBackupCheck implements DoctorCheck
{
    private const KEY = 'recent_successful_backup';

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
        $targets = $this->manager->targets();

        if ($targets === []) {
            return $this->result('warning', 'No backup targets have been recorded yet.', false);
        }

        $unhealthy = array_values(array_map(
            fn ($target): string => $target->targetKey,
            array_filter($targets, fn ($target): bool => $target->postureStatus !== BackupPostureStatus::Healthy),
        ));

        if ($unhealthy === []) {
            return $this->result('passed', 'Every backup target has a recent successful backup posture.', false);
        }

        $status = count($unhealthy) === count($targets) ? 'failed' : 'warning';
        $message = $status === 'failed'
            ? 'No backup target currently reports a recent successful backup posture.'
            : 'Some backup targets are missing a recent successful backup posture.';

        return $this->result($status, $message, false, ['target_keys' => $unhealthy]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function result(string $status, string $message, bool $blocking, array $context = []): DoctorResult
    {
        return new DoctorResult(self::KEY, self::PACKAGE, $status, $message, $blocking, $context);
    }
}
