<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerComplianceSnapshot extends Model
{
    protected $fillable = ['department_id', 'month', 'total_servers', 'non_compliant'];

    protected function casts(): array
    {
        return [
            'total_servers' => 'integer',
            'non_compliant' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @param string $month Format YYYY-MM */
    public function scopeForMonth(Builder $query, string $month): void
    {
        $query->where('month', $month);
    }
}
