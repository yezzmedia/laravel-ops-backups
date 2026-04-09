<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

enum RestoreReadinessStatus: string
{
    case Ready = 'ready';
    case Warning = 'warning';
    case NotReady = 'not_ready';
    case Unsupported = 'unsupported';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready',
            self::Warning => 'Warning',
            self::NotReady => 'Not Ready',
            self::Unsupported => 'Unsupported',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ready => 'success',
            self::Warning => 'warning',
            self::NotReady => 'danger',
            self::Unsupported => 'gray',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Ready => 'heroicon-o-shield-check',
            self::Warning => 'heroicon-o-exclamation-triangle',
            self::NotReady => 'heroicon-o-x-circle',
            self::Unsupported => 'heroicon-o-question-mark-circle',
        };
    }
}
