<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use YezzMedia\OpsBackups\Events\BackupTargetUpdated;
use YezzMedia\OpsBackups\Models\OpsBackupTarget;
use YezzMedia\OpsBackups\Support\BackupScopeType;
use YezzMedia\OpsBackups\Support\OpsBackupsManager;

final class UpsertBackupTargetAction
{
    public function __construct(
        private readonly OpsBackupsManager $manager,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, string $source = 'manual'): OpsBackupTarget
    {
        $validated = Validator::make($data, [
            'target_key' => ['required', 'string', 'max:255'],
            'scope_type' => ['required', Rule::in(array_map(fn (BackupScopeType $type): string => $type->value, BackupScopeType::cases()))],
            'scope_key' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'lifecycle_status' => ['required', 'string', 'max:255'],
            'backup_driver' => ['nullable', 'string', 'max:255'],
            'backup_destination' => ['nullable', 'string', 'max:255'],
            'is_restore_tested' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ])->validate();

        $target = OpsBackupTarget::query()->updateOrCreate(
            ['target_key' => $validated['target_key']],
            [
                'scope_type' => $validated['scope_type'],
                'scope_key' => $validated['scope_key'],
                'name' => $validated['name'],
                'lifecycle_status' => $validated['lifecycle_status'],
                'backup_driver' => $validated['backup_driver'] ?? null,
                'backup_destination' => $validated['backup_destination'] ?? null,
                'is_restore_tested' => (bool) ($validated['is_restore_tested'] ?? false),
                'metadata' => $validated['metadata'] ?? null,
            ],
        );

        $this->manager->refresh();

        $this->events->dispatch(new BackupTargetUpdated(
            targetKey: (string) $target->getAttribute('target_key'),
            scopeType: (string) $target->getAttribute('scope_type'),
            scopeKey: (string) $target->getAttribute('scope_key'),
            lifecycleStatus: (string) $target->getAttribute('lifecycle_status'),
            actorId: Auth::id(),
            source: $source,
            completedAt: now()->toIso8601String(),
        ));

        return $target;
    }
}
