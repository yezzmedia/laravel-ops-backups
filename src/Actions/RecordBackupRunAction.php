<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use YezzMedia\OpsBackups\Events\BackupRunRecorded;
use YezzMedia\OpsBackups\Models\OpsBackupRun;
use YezzMedia\OpsBackups\Models\OpsBackupTarget;
use YezzMedia\OpsBackups\Support\BackupPostureStatus;
use YezzMedia\OpsBackups\Support\OpsBackupsManager;

final class RecordBackupRunAction
{
    public function __construct(
        private readonly OpsBackupsManager $manager,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(OpsBackupTarget|string $target, array $data, string $source = 'manual'): OpsBackupRun
    {
        $target = $this->resolveTarget($target);

        $validated = Validator::make($data, [
            'run_key' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_map(fn (BackupPostureStatus $status): string => $status->value, BackupPostureStatus::cases()))],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'artifact_count' => ['nullable', 'integer', 'min:0'],
            'total_bytes' => ['nullable', 'integer', 'min:0'],
            'summary' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        $run = OpsBackupRun::query()->updateOrCreate(
            [
                'target_id' => $target->getKey(),
                'run_reference' => $validated['run_key'],
            ],
            [
                'status' => $validated['status'],
                'started_at' => $validated['started_at'] ?? null,
                'completed_at' => $validated['completed_at'] ?? null,
                'duration_seconds' => $validated['duration_seconds'] ?? null,
                'artifact_count' => (int) ($validated['artifact_count'] ?? 0),
                'total_bytes' => $validated['total_bytes'] ?? null,
                'error_summary' => $validated['summary'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
            ],
        );

        $this->manager->refresh();

        $this->events->dispatch(new BackupRunRecorded(
            targetKey: (string) $target->getAttribute('target_key'),
            runKey: (string) $run->getAttribute('run_reference'),
            status: (string) $run->getAttribute('status'),
            artifactCount: (int) $run->getAttribute('artifact_count'),
            completedAt: $this->eventMoment($run)->toIso8601String(),
            actorId: Auth::id(),
            source: $source,
        ));

        return $run;
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

    private function eventMoment(OpsBackupRun $run): CarbonImmutable
    {
        $value = $run->getAttribute('completed_at') ?? $run->getAttribute('started_at');

        return $value instanceof CarbonImmutable ? $value : CarbonImmutable::parse((string) ($value ?? now()->toIso8601String()));
    }
}
