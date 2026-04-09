<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

enum BackupPostureStatus: string
{
    case Healthy = 'healthy';
    case Warning = 'warning';
    case Failed = 'failed';
    case Unsupported = 'unsupported';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Warning => 'Warning',
            self::Failed => 'Failed',
            self::Unsupported => 'Unsupported',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Warning => 'warning',
            self::Failed => 'danger',
            self::Unsupported => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Healthy => 'heroicon-o-check-circle',
            self::Warning => 'heroicon-o-exclamation-triangle',
            self::Failed => 'heroicon-o-x-circle',
            self::Unsupported => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * @param  array<int, self>  $statuses
     */
    public static function worst(array $statuses): self
    {
        if (in_array(self::Failed, $statuses, true)) {
            return self::Failed;
        }

        if (in_array(self::Warning, $statuses, true)) {
            return self::Warning;
        }

        if (in_array(self::Healthy, $statuses, true)) {
            return self::Healthy;
        }

        return self::Unsupported;
    }
}
