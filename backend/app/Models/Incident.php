<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Incident extends Model
{
    protected $fillable = ['department_id', 'month', 'total_downtime_minutes', 'actual_uptime_pct'];

    protected function casts(): array
    {
        return [
            'total_downtime_minutes' => 'integer',
            'actual_uptime_pct' => 'decimal:2',
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
