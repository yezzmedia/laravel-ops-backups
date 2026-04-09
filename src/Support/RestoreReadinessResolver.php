<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

use YezzMedia\OpsBackups\Data\RestoreReadinessRecord;
use YezzMedia\OpsBackups\Models\OpsBackupTarget;

final class RestoreReadinessResolver
{
    public function __construct(
        private readonly bool $requireChecksum = true,
        private readonly bool $requireEncryption = true,
    ) {}

    /**
     * @return array<int, RestoreReadinessRecord>
     */
    public function resolve(): array
    {
        return OpsBackupTarget::query()
            ->with(['runs.artifacts'])
            ->orderBy('name')
            ->get()
            ->map(fn (OpsBackupTarget $target): RestoreReadinessRecord => $this->mapTarget($target))
            ->values()
            ->all();
    }

    public function resolveForTarget(string $targetKey): ?RestoreReadinessRecord
    {
        $target = OpsBackupTarget::query()
            ->with(['runs.artifacts'])
            ->where('target_key', $targetKey)
            ->first();

        if ($target === null) {
            return null;
        }

        return $this->mapTarget($target);
    }

    private function mapTarget(OpsBackupTarget $target): RestoreReadinessRecord
    {
        $artifacts = $target->runs
            ->flatMap(fn ($run) => $run->artifacts)
            ->values();

        $hasRecentArtifact = $artifacts->isNotEmpty();
        $checksumPresent = $hasRecentArtifact && $artifacts->every(fn ($artifact): bool => (bool) $artifact->getAttribute('checksum_present'));
        $encrypted = $hasRecentArtifact ? $artifacts->every(fn ($artifact): bool => (bool) $artifact->getAttribute('is_encrypted')) : null;
        $hasReadyArtifacts = $hasRecentArtifact && $artifacts->contains(fn ($artifact): bool => (bool) $artifact->getAttribute('is_restore_ready'));
        $restoreTested = (bool) $target->getAttribute('is_restore_tested');

        $status = match (true) {
            ! $hasRecentArtifact => RestoreReadinessStatus::NotReady,
            $this->requireChecksum && ! $checksumPresent => RestoreReadinessStatus::NotReady,
            $this->requireEncryption && $encrypted !== true => RestoreReadinessStatus::NotReady,
            ! $hasReadyArtifacts => RestoreReadinessStatus::Warning,
            ! $restoreTested => RestoreReadinessStatus::Warning,
            default => RestoreReadinessStatus::Ready,
        };

        return new RestoreReadinessRecord(
            targetKey: (string) $target->getAttribute('target_key'),
            status: $status,
            hasRecentArtifact: $hasRecentArtifact,
            checksumPresent: $checksumPresent,
            encrypted: $encrypted,
            restoreTested: $restoreTested,
            summary: $this->messageFor($status, $hasRecentArtifact, $hasReadyArtifacts, $restoreTested),
            issues: $this->issuesFor($status, $hasRecentArtifact, $checksumPresent, $encrypted, $hasReadyArtifacts, $restoreTested),
        );
    }

    private function messageFor(RestoreReadinessStatus $status, bool $hasArtifacts, bool $hasReadyArtifacts, bool $isRestoreTested): string
    {
        return match ($status) {
            RestoreReadinessStatus::Ready => 'Restore readiness signals are healthy and tested.',
            RestoreReadinessStatus::Warning => ! $hasReadyArtifacts
                ? 'Artifacts exist but are not marked restore-ready.'
                : ($isRestoreTested ? 'Restore readiness needs operator review.' : 'Artifacts are available but no restore test has been recorded.'),
            RestoreReadinessStatus::NotReady => $hasArtifacts
                ? 'Artifacts are missing required checksum or encryption coverage.'
                : 'No artifacts are available for restore readiness assessment.',
            RestoreReadinessStatus::Unsupported => 'Restore readiness is unsupported for this target.',
        };
    }

    /**
     * @return array<int, string>
     */
    private function issuesFor(
        RestoreReadinessStatus $status,
        bool $hasArtifacts,
        bool $checksumPresent,
        ?bool $encrypted,
        bool $hasReadyArtifacts,
        bool $restoreTested,
    ): array {
        $issues = [];

        if (! $hasArtifacts) {
            $issues[] = 'No artifacts are available for restore readiness assessment.';
        }

        if ($status === RestoreReadinessStatus::NotReady && ! $checksumPresent) {
            $issues[] = 'Checksum coverage is incomplete.';
        }

        if ($status === RestoreReadinessStatus::NotReady && $encrypted === false) {
            $issues[] = 'Encryption coverage is incomplete.';
        }

        if ($status === RestoreReadinessStatus::Warning && ! $hasReadyArtifacts) {
            $issues[] = 'Artifacts exist but are not marked restore-ready.';
        }

        if (($status === RestoreReadinessStatus::Warning || $status === RestoreReadinessStatus::Ready) && ! $restoreTested) {
            $issues[] = 'No restore test has been recorded.';
        }

        return array_values(array_unique($issues));
    }
}
