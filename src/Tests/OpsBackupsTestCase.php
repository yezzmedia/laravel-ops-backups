<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Tests;

use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\LivewireServiceProvider;
use YezzMedia\Foundation\Testing\FoundationTestCase;
use YezzMedia\OpsBackups\OpsBackupsServiceProvider;
use YezzMedia\OpsBackups\Testing\Fixtures\OpsBackupsTestPanelProvider;
use YezzMedia\OpsBackups\Testing\Fixtures\TestOpsBackupsUser;

abstract class OpsBackupsTestCase extends FoundationTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            LivewireServiceProvider::class,
            FilamentServiceProvider::class,
            OpsBackupsServiceProvider::class,
            OpsBackupsTestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        Config::set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        Config::set('database.default', 'testing');
        Config::set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        Config::set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => TestOpsBackupsUser::class,
        ]);
        Config::set('ops-backups.cache.enabled', false);
        Config::set('ops-backups.audit.driver', null);

        $app->booted(function (): void {
            foreach (['ops.backups.view', 'ops.backups.manage'] as $ability) {
                Gate::define($ability, static fn (TestOpsBackupsUser $user): bool => $user->allows($ability));
            }
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesExist();

        Filament::setCurrentPanel('ops-backups-test');
    }

    private function ensureTablesExist(): void
    {
        if (! Schema::hasTable('migrations')) {
            Schema::create('migrations', function (Blueprint $table): void {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            });
        }

        if (! Schema::hasTable('ops_backup_targets')) {
            Schema::create('ops_backup_targets', function (Blueprint $table): void {
                $table->id();
                $table->string('target_key')->unique();
                $table->string('scope_type');
                $table->string('scope_key');
                $table->string('name');
                $table->string('lifecycle_status')->default('unknown');
                $table->string('backup_driver')->nullable();
                $table->string('backup_destination')->nullable();
                $table->boolean('is_restore_tested')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ops_backup_runs')) {
            Schema::create('ops_backup_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('target_id')->constrained('ops_backup_targets')->cascadeOnDelete();
                $table->string('run_reference');
                $table->string('status')->default('unsupported');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedInteger('duration_seconds')->nullable();
                $table->unsignedInteger('artifact_count')->default(0);
                $table->unsignedBigInteger('total_bytes')->nullable();
                $table->text('error_summary')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ops_backup_artifacts')) {
            Schema::create('ops_backup_artifacts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('run_id')->constrained('ops_backup_runs')->cascadeOnDelete();
                $table->string('artifact_key');
                $table->timestamp('retention_until')->nullable();
                $table->timestamp('created_at_backup')->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->boolean('checksum_present')->default(false);
                $table->boolean('is_encrypted')->default(false);
                $table->boolean('is_restore_ready')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }
}
