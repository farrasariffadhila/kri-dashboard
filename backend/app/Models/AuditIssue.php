<?php

namespace App\Models;

use App\Enums\RemediationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditIssue extends Model
{
    public const RISK_HIGH = 'High';
    public const RISK_MEDIUM = 'Medium';
    public const RISK_LOW = 'Low';

    public const STATUS_OPEN = 'Open';
    public const STATUS_CLOSED = 'Closed';

    protected $fillable = ['department_id', 'title', 'auditor', 'risk_level', 'status', 'identified_date', 'due_date', 'closed_date'];

    protected function casts(): array
    {
        return [
            // Explicit format keeps JSON as plain dates instead of UTC-shifted timestamps.
            'identified_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'closed_date' => 'date:Y-m-d',
            'remediation_status' => RemediationStatus::class,
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function remediationSubmissions(): HasMany
    {
        return $this->hasMany(RemediationSubmission::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN);
    }

    public function scopeHighRisk(Builder $query): void
    {
        $query->where('risk_level', self::RISK_HIGH);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN)->whereDate('due_date', '<', now());
    }

    /** Issues that were open at the end of the given date (for historical trend). */
    public function scopeOpenAsOf(Builder $query, string $date): void
    {
        $query->whereDate('identified_date', '<=', $date)
            ->where(function (Builder $q) use ($date) {
                $q->whereNull('closed_date')->orWhereDate('closed_date', '>', $date);
            });
    }
}
