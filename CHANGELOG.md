# Changelog

All notable changes to `yezzmedia/laravel-ops-backups` will be documented in this file.

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
