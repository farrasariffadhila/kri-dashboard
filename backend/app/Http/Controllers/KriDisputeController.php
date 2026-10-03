<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\KriDispute;
use App\Models\User;
use App\Services\AttachmentStore;
use App\Services\DepartmentRiskService;
use App\Services\KriDisputeWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class KriDisputeController extends Controller
{
    public function __construct(private readonly KriDisputeWorkflow $workflow)
    {
    }

    /** GET /api/kri-disputes: open disputes first, then the latest decisions. Unit owners see their own department only. */
    public function index(Request $request, DepartmentRiskService $risk): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'status' => ['nullable', Rule::in([KriDispute::STATUS_PENDING, KriDispute::STATUS_ACCEPTED, KriDispute::STATUS_REJECTED])],
        ]);

        $disputes = KriDispute::query()
            ->with(['department:id,name', 'submitter:id,name,role', 'reviewer:id,name,role'])
            ->forMonth($risk->reportingMonth())
            ->when($user->hasRole(Role::UnitOwner), fn ($q) => $q->where('department_id', $user->department_id))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json([
            'reporting_month' => $risk->reportingMonth(),
            'data' => $disputes->map(fn (KriDispute $d) => self::summary($d))->values(),
        ]);
    }

    /** GET /api/kri-disputes/{dispute} */
    public function show(Request $request, KriDispute $dispute, DepartmentRiskService $risk): JsonResponse
    {
        $user = $request->user();
        $this->authorizeView($dispute, $user);

        $dispute->load(['department:id,name', 'submitter:id,name,role', 'reviewer:id,name,role', 'attachments']);
        $baseline = $risk->disputeBaseline($dispute->department_id, $dispute->kri);

        $trail = ActivityLog::query()
            ->with('user:id,name,role')
            ->where('subject_type', $dispute->getMorphClass())
            ->where('subject_id', $dispute->id)
            ->latest('id')
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => self::person($log->user),
                'note' => $log->properties['note'] ?? null,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return response()->json([
            'dispute' => [
                ...self::summary($dispute),
                'reason' => $dispute->reason,
                'review_note' => $dispute->review_note,
                'attachments' => $dispute->attachments->map->toSummary()->values(),
            ],
            // Today's figures, so the reviewer sees whether the source system changed since filing.
            'baseline' => $baseline,
            'activity' => $trail,
            'permissions' => $this->workflow->permissions($dispute, $user),
        ]);
    }

    /** POST /api/kri-disputes: the department is always the unit owner's own. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kri' => ['required', Rule::in(array_keys(KriDispute::KRIS))],
            'proposed_value' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'files' => ['nullable', 'array', 'max:'.AttachmentStore::MAX_FILES],
            // `mimetypes` checks the sniffed content type, so a renamed executable is rejected.
            'files.*' => ['file', 'max:'.AttachmentStore::MAX_KILOBYTES, 'mimetypes:'.implode(',', AttachmentStore::ALLOWED_MIME_TYPES)],
        ]);

        $dispute = $this->workflow->submit(
            $request->user(), $data['kri'], (float) $data['proposed_value'], $data['reason'], $request->file('files', []),
        );

        return response()->json(['id' => $dispute->id], 201);
    }

    /** POST /api/kri-disputes/{dispute}/review */
    public function review(Request $request, KriDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
            'corrected_value' => ['nullable', 'numeric'],
            'note' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ]);

        $dispute = $this->workflow->review(
            $dispute,
            $request->user(),
            $data['decision'] === 'accept',
            isset($data['corrected_value']) ? (float) $data['corrected_value'] : null,
            $data['note'] ?? null,
        );

        return response()->json(['id' => $dispute->id, 'status' => $dispute->status]);
    }

    /** List and work-queue shape. */
    public static function summary(KriDispute $d): array
    {
        return [
            'id' => $d->id,
            'department' => ['id' => $d->department->id, 'name' => $d->department->name],
            'kri' => $d->kri,
            'unit' => $d->unit(),
            'month' => $d->month,
            'status' => $d->status,
            'source_value' => $d->source_value,
            'proposed_value' => $d->proposed_value,
            'corrected_value' => $d->corrected_value,
            'submitted_by' => self::person($d->submitter),
            'submitted_at' => $d->created_at->toIso8601String(),
            'reviewed_by' => self::person($d->reviewer),
            'reviewed_at' => $d->reviewed_at?->toIso8601String(),
        ];
    }

    private static function person(?User $user): ?array
    {
        return $user ? ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value, 'role_label' => $user->role->label()] : null;
    }

    /** Need to know: a unit owner only sees disputes of their own department. */
    private function authorizeView(KriDispute $dispute, User $user): void
    {
        if ($user->hasRole(Role::UnitOwner) && $user->department_id !== $dispute->department_id) {
            throw new AccessDeniedHttpException(__('workflow.dispute_forbidden'));
        }
    }
}
