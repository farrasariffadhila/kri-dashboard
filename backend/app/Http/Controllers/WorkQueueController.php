<?php

namespace App\Http\Controllers;

use App\Enums\RemediationStatus;
use App\Enums\Role;
use App\Models\AuditIssue;
use App\Models\KriDispute;
use App\Services\DepartmentRiskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** GET /api/work-queue: what the signed-in user has to act on, and what they are waiting for. */
class WorkQueueController extends Controller
{
    public function index(Request $request, DepartmentRiskService $risk): JsonResponse
    {
        $user = $request->user();
        if (! $user->role->hasWorkQueue()) {
            throw new AccessDeniedHttpException(__('workflow.no_work_queue'));
        }
        $asOf = $risk->asOf();

        [$actionNeeded, $waiting] = match ($user->role) {
            Role::UnitOwner => [
                $this->issues(fn (Builder $q) => $q->open()->where('department_id', $user->department_id)
                    ->where(fn ($w) => $w->whereNull('remediation_status')->orWhereIn('remediation_status', [
                        RemediationStatus::PlanReturned, RemediationStatus::InProgress, RemediationStatus::EvidenceReturned,
                    ]))),
                $this->issues(fn (Builder $q) => $q->open()->where('department_id', $user->department_id)
                    ->whereIn('remediation_status', [RemediationStatus::PlanReview, RemediationStatus::EvidenceReview])),
            ],
            Role::Auditor => [
                $this->issues(fn (Builder $q) => $q->open()
                    ->whereIn('remediation_status', [RemediationStatus::PlanReview, RemediationStatus::EvidenceReview])),
                collect(),
            ],
            Role::RiskOfficer => [collect(), collect()],
        };

        // Open KRI disputes: IT Risk has to decide them; a unit owner is waiting on their own.
        $disputes = $user->hasRole(Role::Auditor) ? collect() : KriDispute::query()
            ->with(['department:id,name', 'submitter:id,name,role'])
            ->forMonth($risk->reportingMonth())
            ->pending()
            ->when($user->hasRole(Role::UnitOwner), fn ($q) => $q->where('department_id', $user->department_id))
            ->oldest('id')
            ->get();
        $disputesNeedAction = $user->hasRole(Role::RiskOfficer);

        $present = fn ($issues) => $issues->map(fn (AuditIssue $a) => [
            'id' => $a->id,
            'title' => $a->title,
            'department' => ['id' => $a->department->id, 'name' => $a->department->name],
            'risk_level' => $a->risk_level,
            'due_date' => $a->due_date->toDateString(),
            'days_overdue' => $risk->daysOverdue($a->due_date, $asOf),
            'remediation_status' => $a->remediation_status?->value,
        ])->values();

        return response()->json([
            'role' => $user->role->value,
            'counts' => [
                'action_needed' => $actionNeeded->count() + ($disputesNeedAction ? $disputes->count() : 0),
                'waiting' => $waiting->count() + ($disputesNeedAction ? 0 : $disputes->count()),
            ],
            'action_needed' => $present($actionNeeded),
            'waiting' => $present($waiting),
            'disputes' => $disputes->map(fn (KriDispute $d) => KriDisputeController::summary($d))->values(),
        ]);
    }

    private function issues(callable $scope)
    {
        return $scope(AuditIssue::query()->with('department:id,name'))
            // High risk first, then the oldest deadline.
            ->orderByRaw("CASE risk_level WHEN 'High' THEN 0 WHEN 'Medium' THEN 1 ELSE 2 END")
            ->orderBy('due_date')
            ->get();
    }
}
