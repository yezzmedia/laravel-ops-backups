<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Install;

use RuntimeException;
use YezzMedia\Foundation\Data\InstallContext;
use YezzMedia\Foundation\Install\AuditInstallStep;
use YezzMedia\Foundation\Install\OptionalInstallStep;

final class ConfigureOpsBackupsAuditInstallStep implements AuditInstallStep, OptionalInstallStep
{
    public function key(): string
    {
        return 'configure_ops_backups_audit';
    }

    public function package(): string
    {
        return 'yezzmedia/laravel-ops-backups';
    }

    public function priority(): int
    {
        return 230;
    }

    public function shouldRun(InstallContext $context): bool
    {
        return $context->shouldConfigureAuditFor('yezzmedia/laravel-ops-backups');
    }

    public function handle(InstallContext $context): void
    {
        $configPath = config_path('ops-backups.php');

        if (! is_file($configPath)) {
            throw new RuntimeException('Unable to configure ops backups audit because config/ops-backups.php is missing.');
        }

        $contents = file_get_contents($configPath);

        if ($contents === false) {
            throw new RuntimeException('Unable to read config/ops-backups.php while configuring ops backups audit.');
        }

        $updated = str_replace("'driver' => env('OPS_BACKUPS_AUDIT_DRIVER'),", "'driver' => env('OPS_BACKUPS_AUDIT_DRIVER', 'activitylog'),", $contents, $count);

        if ($count === 0) {
            throw new RuntimeException('Unable to locate ops backups audit driver configuration while configuring audit support.');
        }

        file_put_contents($configPath, $updated);
    }

    public function isOptional(): bool
    {
        return true;
    }
}
