<?php

declare(strict_types=1);

return [
    'cache' => [
        'enabled' => env('OPS_BACKUPS_CACHE_ENABLED', true),
        'ttl' => (int) env('OPS_BACKUPS_CACHE_TTL', 300),
        'store' => env('OPS_BACKUPS_CACHE_STORE'),
    ],

    'audit' => [
        'enabled' => env('OPS_BACKUPS_AUDIT_ENABLED', true),
        'driver' => env('OPS_BACKUPS_AUDIT_DRIVER'),
        'log_name' => env('OPS_BACKUPS_AUDIT_LOG_NAME', 'ops-backups'),
    ],

    'retention' => [
        'warning_days' => (int) env('OPS_BACKUPS_RETENTION_WARNING_DAYS', 7),
    ],

    'freshness' => [
        'warning_minutes' => (int) env('OPS_BACKUPS_FRESHNESS_WARNING_MINUTES', 1440),
        'failed_minutes' => (int) env('OPS_BACKUPS_FRESHNESS_FAILED_MINUTES', 2880),
    ],

    'restore' => [
        'require_checksum' => env('OPS_BACKUPS_RESTORE_REQUIRE_CHECKSUM', true),
        'require_encryption' => env('OPS_BACKUPS_RESTORE_REQUIRE_ENCRYPTION', true),
    ],

    'unsupported' => [
        'exclude_from_aggregation' => env('OPS_BACKUPS_UNSUPPORTED_EXCLUDE_FROM_AGGREGATION', false),
    ],
];
