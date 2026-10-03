<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhishingTest extends Model
{
    protected $fillable = ['department_id', 'month', 'total_employees_tested', 'total_failed'];

    protected function casts(): array
    {
        return [
            'total_employees_tested' => 'integer',
            'total_failed' => 'integer',
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
