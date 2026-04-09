<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

final class OpsBackupsStoreSetup
{
    /**
     * @var array<string, bool>
     */
    private array $tableExistsMemo = [];

    public function migrationPath(): string
    {
        return dirname(__DIR__, 2).'/database/migrations';
    }

    /**
     * @return array<int, string>
     */
    public function missingTables(): array
    {
        return array_values(array_filter(
            $this->requiredTables(),
            fn (string $table): bool => ! $this->tableExists($table),
        ));
    }

    public function hasPartialTables(): bool
    {
        $missingTables = $this->missingTables();

        return $missingTables !== [] && count($missingTables) !== count($this->requiredTables());
    }

    public function storeReady(): bool
    {
        return $this->missingTables() === [];
    }

    public function runMigrations(): void
    {
        Artisan::call('migrate', [
            '--path' => $this->migrationPath(),
            '--realpath' => true,
            '--force' => true,
        ]);

        $this->tableExistsMemo = [];
    }

    /**
     * @return array<int, string>
     */
    private function requiredTables(): array
    {
        return [
            'ops_backup_targets',
            'ops_backup_runs',
            'ops_backup_artifacts',
        ];
    }

    private function tableExists(string $table): bool
    {
        return $this->tableExistsMemo[$table] ??= Schema::hasTable($table);
    }
}
