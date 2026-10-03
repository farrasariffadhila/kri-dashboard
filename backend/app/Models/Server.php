<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Server extends Model
{
    protected $fillable = ['department_id', 'hostname', 'ip_address', 'os_version', 'is_compliant'];

    protected function casts(): array
    {
        return [
            'is_compliant' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeNonCompliant(Builder $query): void
    {
        $query->where('is_compliant', false);
    }
}
