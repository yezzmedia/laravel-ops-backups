# Install And Doctor Rules

Declared install steps:

- `PublishOpsBackupsMigrationsInstallStep`
- `EnsureOpsBackupsStoreReadyInstallStep`
- `ConfigureOpsBackupsAuditInstallStep`

Declared doctor checks:

- `BackupsStoreReadyCheck`
- `RecentSuccessfulBackupCheck`
- `RetentionCoverageCheck`
- `RestoreArtifactsAvailableCheck`

Keep store readiness explicit through `OpsBackupsStoreSetup` and keep doctor checks diagnostic-only.
