<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** An action plan or evidence package submitted for an audit finding, plus the auditor's decision. */
class RemediationSubmission extends Model
{
    public const TYPE_PLAN = 'plan';
    public const TYPE_EVIDENCE = 'evidence';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RETURNED = 'returned';

    protected $fillable = ['type', 'description', 'target_date'];

    protected function casts(): array
    {
        return [
            'target_date' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
        ];
    }

    public function auditIssue(): BelongsTo
    {
        return $this->belongsTo(AuditIssue::class);
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
