<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A unit owner's challenge of one KRI figure for the reporting month, and IT Risk's decision.
 * An accepted dispute replaces the source figure in scoring; the source figure stays visible.
 */
class KriDispute extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    /**
     * KRIs that can be disputed, with the unit the figures are expressed in. Open audit findings are
     * not disputable: they change only through the remediation workflow owned by internal audit.
     */
    public const KRIS = [
        'unpatched_servers' => 'servers',
        'uptime' => 'percent',
        'phishing_failure' => 'employees',
    ];

    protected $fillable = ['kri', 'month', 'source_value', 'proposed_value', 'reason'];

    protected function casts(): array
    {
        return [
            'source_value' => 'float',
            'proposed_value' => 'float',
            'corrected_value' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeForMonth(Builder $query, string $month): void
    {
        $query->where('month', $month);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAccepted(Builder $query): void
    {
        $query->where('status', self::STATUS_ACCEPTED);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function unit(): string
    {
        return self::KRIS[$this->kri];
    }
}
