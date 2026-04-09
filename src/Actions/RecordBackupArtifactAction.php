<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use YezzMedia\OpsBackups\Events\BackupArtifactRecorded;
use YezzMedia\OpsBackups\Models\OpsBackupArtifact;
use YezzMedia\OpsBackups\Models\OpsBackupRun;
use YezzMedia\OpsBackups\Models\OpsBackupTarget;
use YezzMedia\OpsBackups\Support\OpsBackupsManager;

final class RecordBackupArtifactAction
{
    public function __construct(
        private readonly OpsBackupsManager $manager,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(OpsBackupTarget|string $target, string $runKey, array $data, string $source = 'manual'): OpsBackupArtifact
    {
        $target = $this->resolveTarget($target);
        $run = $this->resolveRun($target, $runKey);

        $validated = Validator::make($data, [
            'artifact_key' => ['required', 'string', 'max:255'],
            'retention_until' => ['nullable', 'date'],
            'created_at_backup' => ['nullable', 'date'],
            'size_bytes' => ['nullable', 'integer', 'min:0'],
            'checksum_present' => ['sometimes', 'boolean'],
            'is_encrypted' => ['sometimes', 'boolean'],
            'is_restore_ready' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        $artifact = OpsBackupArtifact::query()->updateOrCreate(
            [
                'run_id' => $run->getKey(),
                'artifact_key' => $validated['artifact_key'],
            ],
            [
                'retention_until' => $validated['retention_until'] ?? null,
                'created_at_backup' => $validated['created_at_backup'] ?? null,
                'size_bytes' => $validated['size_bytes'] ?? null,
                'checksum_present' => (bool) ($validated['checksum_present'] ?? false),
                'is_encrypted' => (bool) ($validated['is_encrypted'] ?? false),
                'is_restore_ready' => (bool) ($validated['is_restore_ready'] ?? false),
                'metadata' => $validated['metadata'] ?? null,
            ],
        );

        $run->forceFill([
            'artifact_count' => $run->artifacts()->count(),
        ])->save();

        $this->manager->refresh();

        $this->events->dispatch(new BackupArtifactRecorded(
            targetKey: (string) $target->getAttribute('target_key'),
            runKey: (string) $run->getAttribute('run_reference'),
            artifactKey: (string) $artifact->getAttribute('artifact_key'),
            retentionUntil: $this->eventMoment($artifact->getAttribute('retention_until')),
            isRestoreReady: (bool) $artifact->getAttribute('is_restore_ready'),
            actorId: Auth::id(),
            source: $source,
            completedAt: now()->toIso8601String(),
        ));

        return $artifact;
    }

    private function resolveTarget(OpsBackupTarget|string $target): OpsBackupTarget
    {
        if ($target instanceof OpsBackupTarget) {
            return $target;
        }

        $resolved = OpsBackupTarget::query()->where('target_key', $target)->first();

        if ($resolved instanceof OpsBackupTarget) {
            return $resolved;
        }

        throw ValidationException::withMessages([
            'target_key' => 'The selected backup target is invalid.',
        ]);
    }

    private function resolveRun(OpsBackupTarget $target, string $runKey): OpsBackupRun
    {
        $run = OpsBackupRun::query()
            ->where('target_id', $target->getKey())
            ->where('run_reference', $runKey)
            ->first();

        if ($run instanceof OpsBackupRun) {
            return $run;
        }

        throw ValidationException::withMessages([
            'run_key' => 'The selected backup run is invalid.',
        ]);
    }

    private function eventMoment(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return ($value instanceof CarbonImmutable ? $value : CarbonImmutable::parse((string) $value))->toIso8601String();
    }
}
