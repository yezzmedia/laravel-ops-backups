# Ops Backups Testing Pattern

- Keep registration expectations in `RegistrationTest`.
- Keep install and store behavior in `StoreAndInstallTest`.
- Keep posture refresh and metadata recording flows in their feature tests.
- Keep page and plugin behavior in `OpsBackupsPageTest` and `PluginAndDetailsTest`.
- Run `composer test:ops-backups` from `/home/yezz/Developement/packages/1-dev-test` when available in the shared runner.
