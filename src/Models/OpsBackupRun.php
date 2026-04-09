<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpsBackupRun extends Model
{
    protected $table = 'ops_backup_runs';

    protected $guarded = [];

    /**
     * @return BelongsTo<OpsBackupTarget, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(OpsBackupTarget::class, 'target_id');
    }

    /**
     * @return HasMany<OpsBackupArtifact, $this>
     */
    public function artifacts(): HasMany
    {
        return $this->hasMany(OpsBackupArtifact::class, 'run_id');
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
