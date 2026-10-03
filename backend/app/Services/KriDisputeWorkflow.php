<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\KriDispute;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * KRI data disputes: the unit owner (1st line) challenges a figure of their own department,
 * IT Risk (2nd line) accepts it with a corrected figure or rejects it with a reason.
 *
 * Only the reporting month can be disputed, one open dispute per department and KRI.
 */
class KriDisputeWorkflow
{
    public function __construct(
        private readonly DepartmentRiskService $risk,
        private readonly AttachmentStore $attachments,
    ) {
    }

    /** What the given user may do on this dispute right now (drives the UI, re-checked on every action). */
    public function permissions(KriDispute $dispute, User $user): array
    {
        return ['can_review' => $dispute->isPending() && $user->hasRole(Role::RiskOfficer)];
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function submit(User $owner, string $kri, float $proposed, string $reason, array $files = []): KriDispute
    {
        if (! $owner->hasRole(Role::UnitOwner) || $owner->department_id === null) {
            throw new AccessDeniedHttpException(__('workflow.dispute_owner_only'));
        }
        if (! array_key_exists($kri, KriDispute::KRIS)) {
            throw ValidationException::withMessages(['kri' => __('workflow.dispute_not_disputable')]);
        }

        return DB::transaction(function () use ($owner, $kri, $proposed, $reason, $files) {
            $month = $this->risk->reportingMonth();
            $baseline = $this->risk->disputeBaseline($owner->department_id, $kri);
            $this->validateFigure($proposed, $baseline, 'proposed_value');

            if ($proposed == $baseline['current']) {
                throw ValidationException::withMessages(['proposed_value' => __('workflow.dispute_same_value')]);
            }

            // Lock the department's disputes for this KRI so two open disputes cannot be filed at once.
            $open = KriDispute::query()
                ->where('department_id', $owner->department_id)
                ->where('kri', $kri)
                ->forMonth($month)
                ->pending()
                ->lockForUpdate()
                ->exists();
            if ($open) {
                throw new ConflictHttpException(__('workflow.dispute_already_open'));
            }

            $dispute = new KriDispute([
                'kri' => $kri,
                'month' => $month,
                'source_value' => $baseline['source'],
                'proposed_value' => $proposed,
                'reason' => $reason,
            ]);
            $dispute->department_id = $owner->department_id;
            $dispute->submitted_by = $owner->id;
            $dispute->save();

            foreach ($files as $file) {
                $this->attachments->store($file, $dispute, $owner);
            }

            ActivityLog::record('dispute.submitted', $dispute, [
                'kri' => $kri,
                'proposed_value' => $proposed,
                'attachments' => count($files),
            ], $owner);

            return $dispute;
        });
    }

    public function review(KriDispute $dispute, User $reviewer, bool $accept, ?float $corrected, ?string $note): KriDispute
    {
        return DB::transaction(function () use ($dispute, $reviewer, $accept, $corrected, $note) {
            $dispute = KriDispute::lockForUpdate()->findOrFail($dispute->id);

            if (! $reviewer->hasRole(Role::RiskOfficer)) {
                throw new AccessDeniedHttpException(__('workflow.dispute_reviewer_only'));
            }
            if ($dispute->submitted_by === $reviewer->id) {
                throw new AccessDeniedHttpException(__('workflow.own_submission'));
            }
            if (! $dispute->isPending()) {
                throw new ConflictHttpException(__('workflow.already_reviewed'));
            }

            if ($accept) {
                $corrected ??= $dispute->proposed_value;
                $this->validateFigure($corrected, $this->risk->disputeBaseline($dispute->department_id, $dispute->kri), 'corrected_value');
                $dispute->corrected_value = $corrected;
            }

            $dispute->status = $accept ? KriDispute::STATUS_ACCEPTED : KriDispute::STATUS_REJECTED;
            $dispute->reviewed_by = $reviewer->id;
            $dispute->review_note = $note;
            $dispute->reviewed_at = now();
            $dispute->save();

            // An accepted correction is picked up by the next risk-summary request, so the score changes at once.
            ActivityLog::record($accept ? 'dispute.accepted' : 'dispute.rejected', $dispute, array_filter([
                'corrected_value' => $accept ? $corrected : null,
                'note' => $note,
            ], fn ($v) => $v !== null), $reviewer);

            return $dispute;
        });
    }

    /** Counts must be whole numbers within the denominator; uptime is a percentage. */
    private function validateFigure(float $value, array $baseline, string $field): void
    {
        $valid = $value >= 0 && $value <= $baseline['max'] && (! $baseline['integer'] || floor($value) == $value);

        if (! $valid) {
            throw ValidationException::withMessages([$field => __('workflow.dispute_out_of_range', [
                'max' => $baseline['integer'] ? number_format($baseline['max'], 0, ',', '.') : '100',
            ])]);
        }
    }
}
