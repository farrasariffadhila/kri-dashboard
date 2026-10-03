<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AuditIssue;
use App\Models\Department;
use App\Models\RemediationSubmission;
use App\Models\User;
use App\Services\RemediationWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class RemediationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Department $cards;
    private Department $treasury;
    private User $owner;
    private User $otherOwner;
    private User $auditor;
    private User $director;
    private AuditIssue $issue;

    private const PLAN = 'Patch kritikal diterapkan bertahap pada seluruh server CDE melalui change request CR-2026-114.';
    private const EVIDENCE = 'Laporan pemindaian ulang menunjukkan seluruh server CDE sudah ter-patch per 25 Oktober.';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['kri.reporting_month' => '2026-09']);

        $this->cards = Department::create(['name' => 'Kartu Kredit']);
        $this->treasury = Department::create(['name' => 'Treasury']);
        $this->owner = User::factory()->unitOwner($this->cards)->create();
        $this->otherOwner = User::factory()->unitOwner($this->treasury)->create();
        $this->auditor = User::factory()->auditor()->create();
        $this->director = User::factory()->create();

        $this->issue = AuditIssue::create([
            'department_id' => $this->cards->id,
            'title' => 'PCI DSS 6.3.3: Patch kritikal pada server CDE melewati batas 1 bulan',
            'auditor' => 'Gaiman Kuswoyo',
            'risk_level' => AuditIssue::RISK_HIGH,
            'status' => AuditIssue::STATUS_OPEN,
            'identified_date' => '2026-08-01',
            'due_date' => '2026-08-31',
        ]);
    }

    private function submitPlan(?User $as = null, array $overrides = [])
    {
        return $this->actingAs($as ?? $this->owner)->postJson("/api/audit-issues/{$this->issue->id}/plans", [
            'description' => self::PLAN,
            'target_date' => now()->addMonth()->toDateString(),
            ...$overrides,
        ]);
    }

    private function submitEvidence(?User $as = null, ?array $files = null)
    {
        return $this->actingAs($as ?? $this->owner)->postJson("/api/audit-issues/{$this->issue->id}/evidence", [
            'description' => self::EVIDENCE,
            'files' => $files ?? [UploadedFile::fake()->create('laporan-scan.pdf', 200, 'application/pdf')],
        ]);
    }

    private function review(int $submissionId, string $decision, ?string $note = null, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->auditor)->postJson("/api/remediation-submissions/{$submissionId}/review", array_filter([
            'decision' => $decision,
            'note' => $note,
        ]));
    }

    public function test_full_workflow_closes_the_finding_and_lowers_the_kri(): void
    {
        $openHighBefore = AuditIssue::open()->highRisk()->where('department_id', $this->cards->id)->count();

        $planId = $this->submitPlan()->assertCreated()->json('id');
        $this->assertSame('plan_review', $this->issue->fresh()->remediation_status->value);

        $this->review($planId, 'approve')->assertOk()->assertJsonPath('status', 'approved');
        $this->assertSame('in_progress', $this->issue->fresh()->remediation_status->value);

        $evidenceId = $this->submitEvidence()->assertCreated()->json('id');
        $this->assertSame('evidence_review', $this->issue->fresh()->remediation_status->value);
        $this->assertSame(1, RemediationSubmission::find($evidenceId)->attachments()->count());

        $this->review($evidenceId, 'approve')->assertOk();

        $issue = $this->issue->fresh();
        $this->assertSame(AuditIssue::STATUS_CLOSED, $issue->status);
        $this->assertSame('closed', $issue->remediation_status->value);
        $this->assertSame(now()->toDateString(), $issue->closed_date->toDateString());

        // The dashboard reflects the closure immediately.
        $this->actingAs($this->director)->getJson('/api/departments/risk-summary')
            ->assertJsonPath('data.0.kris.open_high_audits.value', $openHighBefore - 1);

        $this->assertSame(
            ['remediation.plan_submitted', 'remediation.plan_approved', 'remediation.evidence_submitted', 'remediation.evidence_approved'],
            ActivityLog::where('subject_id', $this->issue->id)->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_returning_a_submission_requires_a_note_and_allows_resubmission(): void
    {
        $planId = $this->submitPlan()->json('id');

        $this->review($planId, 'return')->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->review($planId, 'return', 'Target terlalu lama, percepat ke dua minggu.')->assertOk();

        $this->assertSame('plan_returned', $this->issue->fresh()->remediation_status->value);
        $this->assertSame('Target terlalu lama, percepat ke dua minggu.', RemediationSubmission::find($planId)->review_note);
        $this->submitPlan()->assertCreated();
    }

    public function test_only_the_owning_unit_can_submit_and_only_auditors_can_review(): void
    {
        $this->submitPlan($this->otherOwner)->assertForbidden();
        $this->submitPlan($this->director)->assertForbidden();
        $this->submitPlan($this->auditor)->assertForbidden();

        $planId = $this->submitPlan()->json('id');
        $this->review($planId, 'approve', as: $this->owner)->assertForbidden();
        $this->review($planId, 'approve', as: $this->director)->assertForbidden();
    }

    public function test_maker_checker_blocks_reviewing_your_own_submission(): void
    {
        $planId = $this->submitPlan()->json('id');
        // Simulate an auditor who also authored the submission (e.g. after a role change).
        RemediationSubmission::whereKey($planId)->update(['submitted_by' => $this->auditor->id]);

        $this->expectException(AccessDeniedHttpException::class);
        app(RemediationWorkflow::class)->review(RemediationSubmission::find($planId), $this->auditor, true, null);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $this->submitEvidence()->assertStatus(409); // no approved plan yet

        $planId = $this->submitPlan()->json('id');
        $this->submitPlan()->assertStatus(409); // plan already under review
        $this->review($planId, 'approve')->assertOk();
        $this->review($planId, 'return', 'Terlambat.')->assertStatus(409); // already decided
    }

    public function test_evidence_files_are_validated_by_content_type_count_and_size(): void
    {
        $this->review($this->submitPlan()->json('id'), 'approve');

        $this->submitEvidence(files: [])->assertUnprocessable()->assertJsonValidationErrors('files');
        $this->submitEvidence(files: [UploadedFile::fake()->create('bukti.pdf', 10, 'application/x-msdownload')])
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->submitEvidence(files: [UploadedFile::fake()->create('besar.pdf', 6000, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('files.0');
        $this->submitEvidence(files: array_fill(0, 4, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')))
            ->assertUnprocessable()->assertJsonValidationErrors('files');

        $this->submitEvidence(files: [UploadedFile::fake()->image('tangkapan-layar.png')])->assertCreated();
    }

    public function test_attachments_are_private_and_served_only_to_authorised_users(): void
    {
        $this->review($this->submitPlan()->json('id'), 'approve');
        $evidenceId = $this->submitEvidence()->json('id');
        $attachment = RemediationSubmission::find($evidenceId)->attachments()->first();

        Storage::disk('local')->assertExists($attachment->stored_path);
        $this->assertStringStartsWith('attachments/', $attachment->stored_path);
        $this->assertSame(64, strlen($attachment->checksum));

        $this->actingAs($this->owner)->get("/api/attachments/{$attachment->id}")
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertDownload('laporan-scan.pdf');
        $this->actingAs($this->auditor)->get("/api/attachments/{$attachment->id}")->assertOk();
        $this->actingAs($this->otherOwner)->getJson("/api/attachments/{$attachment->id}")->assertForbidden();
    }

    public function test_detail_and_work_queue_reflect_what_each_role_can_do(): void
    {
        $this->actingAs($this->owner)->getJson("/api/audit-issues/{$this->issue->id}")
            ->assertOk()
            ->assertJsonPath('permissions.can_submit_plan', true)
            ->assertJsonPath('permissions.can_review', false);
        $this->actingAs($this->owner)->getJson('/api/work-queue')->assertJsonPath('counts.action_needed', 1);

        $planId = $this->submitPlan()->json('id');

        $this->actingAs($this->owner)->getJson('/api/work-queue')
            ->assertJsonPath('counts.action_needed', 0)
            ->assertJsonPath('counts.waiting', 1);
        $this->actingAs($this->auditor)->getJson('/api/work-queue')
            ->assertJsonPath('counts.action_needed', 1)
            ->assertJsonPath('action_needed.0.remediation_status', 'plan_review');
        $this->actingAs($this->auditor)->getJson("/api/audit-issues/{$this->issue->id}")
            ->assertJsonPath('permissions.can_review', true)
            ->assertJsonPath('permissions.pending_submission_id', $planId)
            ->assertJsonPath('submissions.0.description', self::PLAN)
            ->assertJsonPath('activity.0.action', 'remediation.plan_submitted');
        $this->actingAs($this->director)->getJson('/api/work-queue')
            ->assertForbidden()
            ->assertJsonPath('message', 'Peran Anda tidak memiliki daftar tindak lanjut.');
        $this->actingAs($this->director)->getJson('/api/me')->assertJsonPath('user.has_work_queue', false);
        $this->actingAs($this->auditor)->getJson('/api/me')->assertJsonPath('user.has_work_queue', true);
    }

    public function test_audit_trail_entries_cannot_be_changed(): void
    {
        $this->submitPlan();
        $entry = ActivityLog::firstOrFail();

        $this->expectException(LogicException::class);
        $entry->update(['action' => 'something.else']);
    }
}
