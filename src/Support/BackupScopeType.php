<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

enum BackupScopeType: string
{
    case Platform = 'platform';
    case Site = 'site';
    case Resource = 'resource';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Platform',
            self::Site => 'Site',
            self::Resource => 'Resource',
        };
    }
}
