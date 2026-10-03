<?php

namespace App\Services;

use App\Enums\RemediationStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\AuditIssue;
use App\Models\RemediationSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Audit finding remediation, following the three lines of defence:
 * the unit owner (1st line) proposes a plan and later submits evidence,
 * the internal auditor (3rd line) approves or returns each submission and closes the finding.
 *
 * Every transition runs in a transaction, enforces maker-checker and writes to the audit trail.
 */
class RemediationWorkflow
{
    public function __construct(private readonly AttachmentStore $attachments)
    {
    }

    /** What the given user may do on this finding right now (drives the UI, re-checked on every action). */
    public function permissions(AuditIssue $issue, User $user): array
    {
        $pending = $this->pendingSubmission($issue);

        return [
            'can_submit_plan' => $this->canSubmit($issue, $user) && RemediationStatus::acceptsPlan($issue->remediation_status),
            'can_submit_evidence' => $this->canSubmit($issue, $user) && RemediationStatus::acceptsEvidence($issue->remediation_status),
            'can_review' => $pending !== null && $this->canReview($pending, $user),
            'pending_submission_id' => $pending?->id,
        ];
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function submitPlan(AuditIssue $issue, User $owner, string $description, string $targetDate, array $files = []): RemediationSubmission
    {
        $this->authorizeSubmit($issue, $owner);
        if (! RemediationStatus::acceptsPlan($issue->remediation_status)) {
            throw new ConflictHttpException(__('workflow.plan_not_allowed'));
        }

        return $this->submit($issue, $owner, RemediationSubmission::TYPE_PLAN, $description, $targetDate, $files, RemediationStatus::PlanReview);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function submitEvidence(AuditIssue $issue, User $owner, string $description, array $files): RemediationSubmission
    {
        $this->authorizeSubmit($issue, $owner);
        if (! RemediationStatus::acceptsEvidence($issue->remediation_status)) {
            throw new ConflictHttpException(__('workflow.evidence_not_allowed'));
        }

        return $this->submit($issue, $owner, RemediationSubmission::TYPE_EVIDENCE, $description, null, $files, RemediationStatus::EvidenceReview);
    }

    public function review(RemediationSubmission $submission, User $reviewer, bool $approve, ?string $note): RemediationSubmission
    {
        return DB::transaction(function () use ($submission, $reviewer, $approve, $note) {
            // Lock both rows so two reviewers cannot decide the same submission concurrently.
            $submission = RemediationSubmission::lockForUpdate()->findOrFail($submission->id);
            $issue = AuditIssue::lockForUpdate()->findOrFail($submission->audit_issue_id);

            if (! $reviewer->hasRole(Role::Auditor)) {
                throw new AccessDeniedHttpException(__('workflow.auditor_only'));
            }
            if ($submission->submitted_by === $reviewer->id) {
                throw new AccessDeniedHttpException(__('workflow.own_submission'));
            }
            if (! $submission->isPending()) {
                throw new ConflictHttpException(__('workflow.already_reviewed'));
            }

            $submission->status = $approve ? RemediationSubmission::STATUS_APPROVED : RemediationSubmission::STATUS_RETURNED;
            $submission->reviewed_by = $reviewer->id;
            $submission->review_note = $note;
            $submission->reviewed_at = now();
            $submission->save();

            $isPlan = $submission->type === RemediationSubmission::TYPE_PLAN;
            $issue->remediation_status = match (true) {
                $isPlan && $approve => RemediationStatus::InProgress,
                $isPlan => RemediationStatus::PlanReturned,
                $approve => RemediationStatus::Closed,
                default => RemediationStatus::EvidenceReturned,
            };

            // Verified evidence closes the finding, which immediately lowers the department's KRI.
            if (! $isPlan && $approve) {
                $issue->status = AuditIssue::STATUS_CLOSED;
                $issue->closed_date = now()->toDateString();
            }
            $issue->save();

            ActivityLog::record(
                sprintf('remediation.%s_%s', $submission->type, $approve ? 'approved' : 'returned'),
                $issue,
                ['submission_id' => $submission->id, 'note' => $note],
                $reviewer,
            );

            return $submission;
        });
    }

    private function submit(AuditIssue $issue, User $owner, string $type, string $description, ?string $targetDate, array $files, RemediationStatus $next): RemediationSubmission
    {
        return DB::transaction(function () use ($issue, $owner, $type, $description, $targetDate, $files, $next) {
            $issue = AuditIssue::lockForUpdate()->findOrFail($issue->id);

            $submission = new RemediationSubmission(['type' => $type, 'description' => $description, 'target_date' => $targetDate]);
            $submission->audit_issue_id = $issue->id;
            $submission->submitted_by = $owner->id;
            $submission->save();

            foreach ($files as $file) {
                $this->attachments->store($file, $submission, $owner);
            }

            $issue->remediation_status = $next;
            $issue->save();

            ActivityLog::record("remediation.{$type}_submitted", $issue, [
                'submission_id' => $submission->id,
                'attachments' => count($files),
            ], $owner);

            return $submission;
        });
    }

    private function canSubmit(AuditIssue $issue, User $user): bool
    {
        return $issue->isOpen() && $user->ownsDepartment($issue->department_id);
    }

    private function canReview(RemediationSubmission $submission, User $user): bool
    {
        return $user->hasRole(Role::Auditor) && $submission->submitted_by !== $user->id;
    }

    private function authorizeSubmit(AuditIssue $issue, User $user): void
    {
        if (! $user->ownsDepartment($issue->department_id)) {
            throw new AccessDeniedHttpException(__('workflow.owner_only'));
        }
        if (! $issue->isOpen()) {
            throw new ConflictHttpException(__('workflow.issue_closed'));
        }
    }

    private function pendingSubmission(AuditIssue $issue): ?RemediationSubmission
    {
        return $issue->remediationSubmissions()->where('status', RemediationSubmission::STATUS_PENDING)->latest('id')->first();
    }
}
