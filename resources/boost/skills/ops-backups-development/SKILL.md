---
name: ops-backups-development
description: "Build and maintain yezzmedia/laravel-ops-backups. Activate when changing backup target metadata, backup run or artifact recording, retention or restore-readiness posture, backups install or doctor flows, backups Filament pages, audit integration, or package tests that depend on the approved backups V1 surface."
license: MIT
metadata:
  author: yezzmedia
---

# Ops Backups Development

## Documentation

Use `search-docs` for Laravel, Filament, Pest, Package Tools, and Boost details. Use the reference files in this skill for the approved backups runtime surface.

Use the `foundation-package-development` skill when descriptor capability choices or foundation registration behavior change.

## When To Use This Skill

Activate this skill when working inside `yezzmedia/laravel-ops-backups`, especially when changing:

- backup target metadata or inventory posture
- backup run recording, artifact recording, retention posture, or restore readiness
- backups install steps, doctor checks, or store setup
- backups Filament pages or target-detail workflows
- backups audit persistence or related events
- package tests that prove registration, store, and UI behavior

## Core Rules

- Keep `OpsBackupsPlatformPackage` declarative and aligned with the real package surface.
- Keep actions responsible for writes and resolvers responsible for posture projection.
- Keep backup targets, runs, and artifacts as distinct runtime concerns.
- Keep audit integration optional and package-config driven.
- Keep operator UI technical and recovery-oriented.

## References

- Use [references/runtime-surface.md](references/runtime-surface.md) for the approved backups surface.
- Use [references/install-and-doctor.md](references/install-and-doctor.md) for install-step and doctor-check boundaries.
- Use [references/filament-surface.md](references/filament-surface.md) for operator pages and detail workflows.
- Use [references/testing.md](references/testing.md) for verification expectations.
- Use [references/checklist.md](references/checklist.md) before finalizing backups changes.

## Common Pitfalls

- collapsing target, run, and artifact concerns into one write path
- changing retention or restore rules inside UI code instead of resolvers
- adding pages without aligning ops-module declarations
- skipping store readiness behavior when migrations or tables are missing
