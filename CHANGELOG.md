# Changelog

All notable changes to `yezzmedia/laravel-ops-backups` will be documented in this file.

## [Unreleased]

## [0.2.0] - 2026-06-30

### Changed

- Bumped minimum `yezzmedia/laravel-foundation` dependency to `^0.2`

## [0.1.1] - 2026-04-13

### Fixed

- published the host `config/ops-backups.php` file automatically inside the audit install flow when it was missing
- kept ops backups audit configuration working for Basecamp and other audit-only installs that do not run ordinary publish steps first

## [0.1.0] - 2026-04-09

### Added

- initial `laravel-ops-backups` package scaffold with package tools bootstrap and foundation registration
- package config and migrations for backup targets, runs, and artifacts
- backup posture enums, DTOs, models, resolvers, and cache-backed manager
- install steps and doctor checks for store readiness, backup freshness, retention, and restore artifacts visibility
- Filament plugin with overview and detail pages for ops-facing backup posture visibility
- refresh action and normalized posture refresh audit event handling
- metadata recording actions for backup targets, backup runs, and backup artifacts
- audit runtime support for target-updated, run-recorded, and artifact-recorded events
- package testbench support, feature tests, and shared `1-dev-test` bootstrap integration
- conditional ops panel integration in `yezzmedia/laravel-ops`
