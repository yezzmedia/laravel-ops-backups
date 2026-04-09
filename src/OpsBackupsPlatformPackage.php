<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups;

use YezzMedia\Foundation\Contracts\DefinesAuditEvents;
use YezzMedia\Foundation\Contracts\DefinesInstallSteps;
use YezzMedia\Foundation\Contracts\DefinesPermissions;
use YezzMedia\Foundation\Contracts\PlatformPackage;
use YezzMedia\Foundation\Contracts\ProvidesDoctorChecks;
use YezzMedia\Foundation\Contracts\ProvidesOpsModules;
use YezzMedia\Foundation\Contracts\RegistersFeatures;
use YezzMedia\Foundation\Data\AuditEventDefinition;
use YezzMedia\Foundation\Data\FeatureDefinition;
use YezzMedia\Foundation\Data\OpsModuleDefinition;
use YezzMedia\Foundation\Data\PackageMetadata;
use YezzMedia\Foundation\Data\PermissionDefinition;
use YezzMedia\Foundation\Doctor\DoctorCheck;
use YezzMedia\Foundation\Install\InstallStep;
use YezzMedia\OpsBackups\Doctor\BackupsStoreReadyCheck;
use YezzMedia\OpsBackups\Doctor\RecentSuccessfulBackupCheck;
use YezzMedia\OpsBackups\Doctor\RestoreArtifactsAvailableCheck;
use YezzMedia\OpsBackups\Doctor\RetentionCoverageCheck;
use YezzMedia\OpsBackups\Install\ConfigureOpsBackupsAuditInstallStep;
use YezzMedia\OpsBackups\Install\EnsureOpsBackupsStoreReadyInstallStep;
use YezzMedia\OpsBackups\Install\PublishOpsBackupsMigrationsInstallStep;

final class OpsBackupsPlatformPackage implements DefinesAuditEvents, DefinesInstallSteps, DefinesPermissions, PlatformPackage, ProvidesDoctorChecks, ProvidesOpsModules, RegistersFeatures
{
    public function metadata(): PackageMetadata
    {
        return new PackageMetadata(
            name: 'yezzmedia/laravel-ops-backups',
            vendor: 'yezzmedia',
            description: 'Ops-facing backup posture, retention visibility, and restore readiness package for the Yezz Media Laravel platform.',
            packageClass: self::class,
        );
    }

    /**
     * @return array<int, PermissionDefinition>
     */
    public function permissionDefinitions(): array
    {
        return [
            new PermissionDefinition(
                name: 'ops.backups.view',
                package: 'yezzmedia/laravel-ops-backups',
                label: 'View ops backups',
                description: 'Allows viewing backup posture, retention visibility, restore readiness, and recent backup failures.',
                defaultRoleHints: ['super-admin'],
            ),
            new PermissionDefinition(
                name: 'ops.backups.manage',
                package: 'yezzmedia/laravel-ops-backups',
                label: 'Manage ops backups',
                description: 'Allows refreshing backup posture and running package-owned metadata recording actions.',
                defaultRoleHints: ['super-admin'],
            ),
        ];
    }

    /**
     * @return array<int, FeatureDefinition>
     */
    public function featureDefinitions(): array
    {
        return [
            new FeatureDefinition('backups.inventory', 'yezzmedia/laravel-ops-backups', 'Backup inventory', 'Provides an ops-facing inventory of backup targets and their latest posture.'),
            new FeatureDefinition('backups.retention', 'yezzmedia/laravel-ops-backups', 'Retention visibility', 'Reports backup retention coverage and approaching retention deadlines.'),
            new FeatureDefinition('backups.restore_readiness', 'yezzmedia/laravel-ops-backups', 'Restore readiness', 'Shows whether recorded backup artifacts satisfy restore-readiness expectations.'),
            new FeatureDefinition('backups.failures', 'yezzmedia/laravel-ops-backups', 'Failure visibility', 'Surfaces recent backup failures and stale or missing successful runs.'),
        ];
    }

    /**
     * @return array<int, AuditEventDefinition>
     */
    public function auditEventDefinitions(): array
    {
        return [
            new AuditEventDefinition(
                key: 'ops.backups.posture_refreshed',
                package: 'yezzmedia/laravel-ops-backups',
                action: 'refreshed',
                subjectType: 'backup_posture_snapshot',
                description: 'Ops backups posture snapshot was refreshed.',
                severity: 'info',
                contextKeys: ['overall_status', 'healthy_count', 'warning_count', 'failing_count', 'unsupported_count', 'actor_id', 'source', 'completed_at'],
            ),
            new AuditEventDefinition(
                key: 'ops.backups.target_updated',
                package: 'yezzmedia/laravel-ops-backups',
                action: 'updated',
                subjectType: 'backup_target',
                description: 'A backup target metadata record was updated.',
                severity: 'info',
                contextKeys: ['target_key', 'scope_type', 'scope_key', 'lifecycle_status', 'actor_id', 'source'],
            ),
            new AuditEventDefinition(
                key: 'ops.backups.run_recorded',
                package: 'yezzmedia/laravel-ops-backups',
                action: 'recorded',
                subjectType: 'backup_run',
                description: 'A backup run metadata record was recorded.',
                severity: 'info',
                contextKeys: ['target_key', 'run_key', 'status', 'artifact_count', 'completed_at', 'actor_id', 'source'],
            ),
            new AuditEventDefinition(
                key: 'ops.backups.artifact_recorded',
                package: 'yezzmedia/laravel-ops-backups',
                action: 'recorded',
                subjectType: 'backup_artifact',
                description: 'A backup artifact metadata record was recorded.',
                severity: 'info',
                contextKeys: ['target_key', 'run_key', 'artifact_key', 'retention_until', 'is_restore_ready', 'actor_id', 'source'],
            ),
        ];
    }

    /**
     * @return array<int, InstallStep>
     */
    public function installSteps(): array
    {
        return [
            app(PublishOpsBackupsMigrationsInstallStep::class),
            app(EnsureOpsBackupsStoreReadyInstallStep::class),
            app(ConfigureOpsBackupsAuditInstallStep::class),
        ];
    }

    /**
     * @return array<int, DoctorCheck>
     */
    public function doctorChecks(): array
    {
        return [
            app(BackupsStoreReadyCheck::class),
            app(RecentSuccessfulBackupCheck::class),
            app(RetentionCoverageCheck::class),
            app(RestoreArtifactsAvailableCheck::class),
        ];
    }

    /**
     * @return array<int, OpsModuleDefinition>
     */
    public function opsModuleDefinitions(): array
    {
        return [
            new OpsModuleDefinition(
                key: 'diagnostics.backups.overview',
                package: 'yezzmedia/laravel-ops-backups',
                label: 'Backups Overview',
                type: 'page',
                permissionHint: 'ops.backups.view',
            ),
            new OpsModuleDefinition(
                key: 'diagnostics.backups.detail',
                package: 'yezzmedia/laravel-ops-backups',
                label: 'Backup Target Detail',
                type: 'page',
                permissionHint: 'ops.backups.view',
            ),
        ];
    }
}
