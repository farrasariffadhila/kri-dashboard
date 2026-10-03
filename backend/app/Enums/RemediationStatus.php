<?php

namespace App\Enums;

/**
 * Where an audit finding stands in its remediation workflow.
 * A finding without a status has had no remediation activity yet.
 */
enum RemediationStatus: string
{
    case PlanReview = 'plan_review';
    case PlanReturned = 'plan_returned';
    case InProgress = 'in_progress';
    case EvidenceReview = 'evidence_review';
    case EvidenceReturned = 'evidence_returned';
    case Closed = 'closed';

    /** States in which the unit owner may submit an action plan. */
    public static function acceptsPlan(?self $status): bool
    {
        return $status === null || $status === self::PlanReturned;
    }

    /** States in which the unit owner may submit remediation evidence. */
    public static function acceptsEvidence(?self $status): bool
    {
        return $status === self::InProgress || $status === self::EvidenceReturned;
    }
}
