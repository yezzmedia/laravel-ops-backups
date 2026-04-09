<?php

declare(strict_types=1);

namespace YezzMedia\OpsBackups\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpsBackupTarget extends Model
{
    protected $table = 'ops_backup_targets';

    protected $guarded = [];

    /**
     * @return HasMany<OpsBackupRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(OpsBackupRun::class, 'target_id');
    }

    protected function casts(): array
    {
        return [
            'is_restore_tested' => 'bool',
            'metadata' => 'array',
        ];
    }
}
