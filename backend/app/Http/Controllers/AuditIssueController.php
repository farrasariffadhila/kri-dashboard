<?php

namespace App\Http\Controllers;

use App\Enums\RemediationStatus;
use App\Models\ActivityLog;
use App\Models\AuditIssue;
use App\Models\RemediationSubmission;
use App\Services\DepartmentRiskService;
use App\Services\RemediationWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditIssueController extends Controller
{
    private const SORTS = ['due_date', '-due_date', 'identified_date', '-identified_date'];

    /** GET /api/audit-issues: paginated, filterable list of audit findings. */
    public function index(Request $request, DepartmentRiskService $risk): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'risk_level' => ['nullable', Rule::in([AuditIssue::RISK_HIGH, AuditIssue::RISK_MEDIUM, AuditIssue::RISK_LOW])],
            'status' => ['nullable', Rule::in([AuditIssue::STATUS_OPEN, AuditIssue::STATUS_CLOSED])],
            'overdue' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'remediation_status' => ['nullable', Rule::in([...array_column(RemediationStatus::cases(), 'value'), 'none'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $asOf = $risk->asOf();
        $sort = $filters['sort'] ?? 'due_date';

        $page = AuditIssue::query()
            ->with('department:id,name')
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($filters['risk_level'] ?? null, fn ($q, $level) => $q->where('risk_level', $level))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($request->boolean('overdue'), fn ($q) => $q->open()->whereDate('due_date', '<', $asOf->toDateString()))
            ->when($filters['remediation_status'] ?? null, fn ($q, $status) => $status === 'none'
                ? $q->whereNull('remediation_status')
                : $q->where('remediation_status', $status))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('auditor', 'like', $like));
            })
            ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString()
            ->through(fn (AuditIssue $a) => [
                'id' => $a->id,
                'department' => ['id' => $a->department->id, 'name' => $a->department->name],
                'title' => $a->title,
                'auditor' => $a->auditor,
                'risk_level' => $a->risk_level,
                'status' => $a->status,
                'remediation_status' => $a->remediation_status?->value,
                'identified_date' => $a->identified_date?->toDateString(),
                'due_date' => $a->due_date->toDateString(),
                'closed_date' => $a->closed_date?->toDateString(),
                'days_overdue' => $a->status === AuditIssue::STATUS_OPEN ? $risk->daysOverdue($a->due_date, $asOf) : null,
            ]);

        return response()->json([...$page->toArray(), 'as_of' => $asOf->toDateString()]);
    }

    /** GET /api/audit-issues/{auditIssue}: one finding with its remediation history and audit trail. */
    public function show(Request $request, AuditIssue $auditIssue, DepartmentRiskService $risk, RemediationWorkflow $workflow): JsonResponse
    {
        $asOf = $risk->asOf();
        $auditIssue->load(['department:id,name', 'remediationSubmissions' => fn ($q) => $q->latest('id'),
            'remediationSubmissions.submitter:id,name,role', 'remediationSubmissions.reviewer:id,name,role',
            'remediationSubmissions.attachments']);

        $person = fn ($user) => $user ? ['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value, 'role_label' => $user->role->label()] : null;

        $trail = ActivityLog::query()
            ->with('user:id,name,role')
            ->where('subject_type', $auditIssue->getMorphClass())
            ->where('subject_id', $auditIssue->id)
            ->latest('id')
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $person($log->user),
                'note' => $log->properties['note'] ?? null,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return response()->json([
            'as_of' => $asOf->toDateString(),
            'issue' => [
                'id' => $auditIssue->id,
                'department' => ['id' => $auditIssue->department->id, 'name' => $auditIssue->department->name],
                'title' => $auditIssue->title,
                'auditor' => $auditIssue->auditor,
                'risk_level' => $auditIssue->risk_level,
                'status' => $auditIssue->status,
                'remediation_status' => $auditIssue->remediation_status?->value,
                'identified_date' => $auditIssue->identified_date?->toDateString(),
                'due_date' => $auditIssue->due_date->toDateString(),
                'closed_date' => $auditIssue->closed_date?->toDateString(),
                'days_overdue' => $auditIssue->isOpen() ? $risk->daysOverdue($auditIssue->due_date, $asOf) : null,
            ],
            'submissions' => $auditIssue->remediationSubmissions->map(fn (RemediationSubmission $s) => [
                'id' => $s->id,
                'type' => $s->type,
                'status' => $s->status,
                'description' => $s->description,
                'target_date' => $s->target_date?->toDateString(),
                'submitted_by' => $person($s->submitter),
                'submitted_at' => $s->created_at->toIso8601String(),
                'reviewed_by' => $person($s->reviewer),
                'reviewed_at' => $s->reviewed_at?->toIso8601String(),
                'review_note' => $s->review_note,
                'attachments' => $s->attachments->map->toSummary()->values(),
            ])->values(),
            'activity' => $trail,
            'permissions' => $workflow->permissions($auditIssue, $request->user()),
        ]);
    }
}
