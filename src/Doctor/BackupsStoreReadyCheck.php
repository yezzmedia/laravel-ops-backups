<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Doctor;

use YezzMedia\Foundation\Data\DoctorResult;
use YezzMedia\Foundation\Doctor\DoctorCheck;
use YezzMedia\OpsBackups\Support\OpsBackupsStoreSetup;

final class BackupsStoreReadyCheck implements DoctorCheck
{
    private const KEY = 'backups_store_ready';

    private const PACKAGE = 'yezzmedia/laravel-ops-backups';

    public function __construct(private readonly OpsBackupsStoreSetup $storeSetup) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function package(): string
    {
        return self::PACKAGE;
    }

    public function run(): DoctorResult
    {
        $missingTables = $this->storeSetup->missingTables();

        if ($missingTables === []) {
            return $this->result('passed', 'Ops backups persistence store is ready.', false);
        }

        return $this->result('failed', 'Ops backups persistence store is missing required tables.', true, ['missing_tables' => $missingTables]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function result(string $status, string $message, bool $blocking, array $context = []): DoctorResult
    {
        return new DoctorResult(
            key: self::KEY,
            package: self::PACKAGE,
            status: $status,
            message: $message,
            isBlocking: $blocking,
            context: $context,
        );
    }
}
