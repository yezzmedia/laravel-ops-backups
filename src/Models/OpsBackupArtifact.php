<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsBackupArtifact extends Model
{
    protected $table = 'ops_backup_artifacts';

    protected $guarded = [];

    /**
     * @return BelongsTo<OpsBackupRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(OpsBackupRun::class, 'run_id');
    }

    protected function casts(): array
    {
        return [
            'retention_until' => 'immutable_datetime',
            'created_at_backup' => 'immutable_datetime',
            'checksum_present' => 'bool',
            'is_encrypted' => 'bool',
            'is_restore_ready' => 'bool',
            'metadata' => 'array',
        ];
    }
}
