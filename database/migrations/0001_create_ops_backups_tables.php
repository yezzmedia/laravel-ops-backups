<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            $table->index(['scope_type', 'scope_key']);
        });

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

            $table->unique(['target_id', 'run_reference']);
            $table->index(['status', 'completed_at']);
        });

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

            $table->unique(['run_id', 'artifact_key']);
            $table->index(['retention_until', 'is_restore_ready']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ops_backup_artifacts');
        Schema::dropIfExists('ops_backup_runs');
        Schema::dropIfExists('ops_backup_targets');
    }
};
