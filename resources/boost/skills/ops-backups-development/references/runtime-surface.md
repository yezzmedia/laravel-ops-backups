# Approved V1 Ops Backups Surface

- permissions:
  - `ops.backups.view`
  - `ops.backups.manage`
- features:
  - `backups.inventory`
  - `backups.retention`
  - `backups.restore_readiness`
  - `backups.failures`
- audit events:
  - `ops.backups.posture_refreshed`
  - `ops.backups.target_updated`
  - `ops.backups.run_recorded`
  - `ops.backups.artifact_recorded`
- ops modules:
  - `diagnostics.backups.overview`
  - `diagnostics.backups.detail`

Core public runtime types include:

- `OpsBackupsPlatformPackage`
- `OpsBackupsServiceProvider`
- `OpsBackupsManager`
- `UpsertBackupTargetAction`
- `RecordBackupRunAction`
- `RecordBackupArtifactAction`
- `RefreshBackupPostureAction`
- `BackupInventoryResolver`
- `BackupJobPostureResolver`
- `RetentionPostureResolver`
- `RestoreReadinessResolver`
- `BackupFailureResolver`
