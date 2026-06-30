<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/yezzmedia/.github/main/profile/yezzmedia-dark.svg">
    <img src="https://raw.githubusercontent.com/yezzmedia/.github/main/profile/yezzmedia-light.svg" alt="Yezz Media" height="40">
  </picture>
</p>

<p align="center">
  <a href="https://packagist.org/packages/yezzmedia/laravel-ops-backups"><img src="https://img.shields.io/packagist/v/yezzmedia/laravel-ops-backups?style=flat-square" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/yezzmedia/laravel-ops-backups"><img src="https://img.shields.io/packagist/php-v/yezzmedia/laravel-ops-backups?style=flat-square" alt="PHP Version"></a>
  <a href="https://packagist.org/packages/yezzmedia/laravel-ops-backups"><img src="https://img.shields.io/packagist/l/yezzmedia/laravel-ops-backups?style=flat-square" alt="License"></a>
</p>

---

# Laravel Ops Backups

`yezzmedia/laravel-ops-backups` is the Yezz Media ops-facing package for backup posture, retention visibility, restore readiness, and backup failure reporting.

It is intentionally a visibility and metadata package, not a backup execution engine.

## Version

Current release: `0.2.0`

## V1 Scope

- backup target inventory for platform, site, or resource scopes
- backup posture summary and detail views in the ops panel
- retention visibility and restore-readiness visibility
- recent failure reporting
- package-owned metadata recording actions for targets, runs, and artifacts
- package-owned audit events for refreshes and metadata recording
- doctor checks for store readiness, recent successful backups, retention coverage, and restore artifacts availability

## Non-Goals

- no real backup execution
- no real restore execution
- no artifact download UI
- no storage browsing
- no raw backup-content inspection
- no credential-bearing destination URIs in package metadata

## Package Surface

### Permissions

- `ops.backups.view`
- `ops.backups.manage`

Both permissions declare `defaultRoleHints: ['super-admin']`. Persistence and role assignment remain owned by `yezzmedia/laravel-access`.

### Features

- `backups.inventory`
- `backups.retention`
- `backups.restore_readiness`
- `backups.failures`

### Audit Events

- `ops.backups.posture_refreshed`
- `ops.backups.target_updated`
- `ops.backups.run_recorded`
- `ops.backups.artifact_recorded`

## Main Building Blocks

- `OpsBackupsPlatformPackage`
- `OpsBackupsServiceProvider`
- `OpsBackupsFilamentPlugin`
- `OpsBackupsManager`
- `RefreshBackupPostureAction`
- `UpsertBackupTargetAction`
- `RecordBackupRunAction`
- `RecordBackupArtifactAction`
- `OpsBackupsPage`
- `BackupTargetDetailsPage`

## Storage

The package owns these tables:

- `ops_backup_targets`
- `ops_backup_runs`
- `ops_backup_artifacts`

These tables store operator-safe metadata only.

## Development

Package-local scripts:

```bash
composer format
composer analyse
composer test
```

Shared fixture verification from `1-dev-test`:

```bash
composer test:ops-backups
composer test:all
```

## Host Integration

To expose the package in a consuming host:

1. require `yezzmedia/laravel-ops-backups`
2. install or update dependencies in the host
3. run package migrations
4. synchronize permissions through `yezzmedia/laravel-access`
5. verify `/ops/ops-backups`

## License

Proprietary. All rights reserved by Yezz Media.
