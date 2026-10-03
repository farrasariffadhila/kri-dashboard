<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Incident;
use App\Models\KriDispute;
use App\Models\PhishingTest;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KriDisputeTest extends TestCase
{
    use RefreshDatabase;

    private Department $cards;
    private Department $treasury;
    private User $owner;
    private User $otherOwner;
    private User $riskOfficer;
    private User $auditor;
    private User $director;

    private const REASON = '4 server RHEL 7.9 sudah dinonaktifkan 22 September tetapi masih tercatat di CMDB.';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['kri.reporting_month' => '2026-09']);

        $this->cards = Department::create(['name' => 'Kartu Kredit']);
        $this->treasury = Department::create(['name' => 'Treasury']);
        $this->owner = User::factory()->unitOwner($this->cards)->create();
        $this->otherOwner = User::factory()->unitOwner($this->treasury)->create();
        $this->riskOfficer = User::factory()->riskOfficer()->create();
        $this->auditor = User::factory()->auditor()->create();
        $this->director = User::factory()->create();

        // Kartu Kredit 8/20 unpatched (40%), Treasury 2/20; bank 10/40 = 25%.
        $this->servers($this->cards, compliant: 12, nonCompliant: 8);
        $this->servers($this->treasury, compliant: 18, nonCompliant: 2);
        // Uptime: Kartu Kredit 99.00%, Treasury 100%; bank mean over 4 applications = 99.50%.
        foreach ([[$this->cards, 99.0], [$this->treasury, 100.0]] as [$department, $uptime]) {
            foreach ([1, 2] as $_) {
                Incident::create(['department_id' => $department->id, 'month' => '2026-09', 'total_downtime_minutes' => 0, 'actual_uptime_pct' => $uptime]);
            }
        }
        PhishingTest::create(['department_id' => $this->cards->id, 'month' => '2026-09', 'total_employees_tested' => 100, 'total_failed' => 30]);
    }

    private function file(string $kri = 'unpatched_servers', float $proposed = 4, ?User $as = null, array $extra = [])
    {
        return $this->actingAs($as ?? $this->owner)->postJson('/api/kri-disputes', [
            'kri' => $kri,
            'proposed_value' => $proposed,
            'reason' => self::REASON,
            ...$extra,
        ]);
    }

    private function review(int $id, string $decision, array $extra = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->riskOfficer)->postJson("/api/kri-disputes/{$id}/review", ['decision' => $decision, ...$extra]);
    }

    private function cardsKri(string $kri, ?User $as = null): array
    {
        $summary = $this->actingAs($as ?? $this->director)->getJson('/api/departments/risk-summary')->json();

        return collect($summary['data'])->firstWhere('name', 'Kartu Kredit')['kris'][$kri];
    }

    public function test_unit_owner_files_a_dispute_that_is_flagged_but_does_not_change_the_score_yet(): void
    {
        $before = $this->cardsKri('unpatched_servers');

        $id = $this->file(extra: ['files' => [UploadedFile::fake()->create('cmdb-export.pdf', 120, 'application/pdf')]])
            ->assertCreated()->json('id');

        $dispute = KriDispute::findOrFail($id);
        $this->assertSame(KriDispute::STATUS_PENDING, $dispute->status);
        $this->assertSame(8.0, $dispute->source_value);
        $this->assertSame('2026-09', $dispute->month);
        $this->assertSame($this->cards->id, $dispute->department_id);
        $this->assertCount(1, $dispute->attachments);

        $after = $this->cardsKri('unpatched_servers');
        $this->assertSame($before['value'], $after['value']);
        $this->assertSame($id, $after['open_dispute_id']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'dispute.submitted', 'subject_id' => $id, 'user_id' => $this->owner->id]);
    }

    public function test_only_unit_owners_can_file_and_only_for_disputable_kris(): void
    {
        $this->file(as: $this->riskOfficer)->assertForbidden();
        $this->file(as: $this->auditor)->assertForbidden();
        $this->file(as: $this->director)->assertForbidden();

        // Audit findings change through the remediation workflow, never by dispute.
        $this->file('open_high_audits', 3)->assertUnprocessable()->assertJsonValidationErrors('kri');
    }

    public function test_proposed_figure_must_be_valid_and_different(): void
    {
        $this->file(proposed: 21)->assertUnprocessable()->assertJsonValidationErrors('proposed_value'); // more than 20 servers
        $this->file(proposed: 4.5)->assertUnprocessable()->assertJsonValidationErrors('proposed_value'); // half a server
        $this->file(proposed: 8)->assertUnprocessable()->assertJsonValidationErrors('proposed_value');   // unchanged
        $this->file('uptime', 100.5)->assertUnprocessable()->assertJsonValidationErrors('proposed_value');
        $this->file(extra: ['reason' => 'salah'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    }

    public function test_only_one_open_dispute_per_kri(): void
    {
        $this->file()->assertCreated();
        $this->file(proposed: 5)->assertConflict();
        $this->file('phishing_failure', 20)->assertCreated(); // another KRI is fine
    }

    public function test_accepted_dispute_replaces_the_figure_everywhere_and_keeps_the_source_visible(): void
    {
        $id = $this->file()->json('id');

        $this->review($id, 'accept')->assertOk()->assertJsonPath('status', 'accepted');

        $kri = $this->cardsKri('unpatched_servers');
        $this->assertEquals(20.0, $kri['value']); // 4 / 20
        $this->assertSame(4, $kri['non_compliant']);
        $this->assertEquals(40.0, $kri['correction']['source_value']);
        $this->assertEquals(8, $kri['correction']['source_figure']);
        $this->assertSame($id, $kri['correction']['dispute_id']);
        $this->assertArrayNotHasKey('open_dispute_id', $kri);

        $summary = $this->actingAs($this->director)->getJson('/api/departments/risk-summary')->json();
        $this->assertSame(6, $summary['totals']['current']['unpatched_servers']['non_compliant']); // 4 + 2
        $this->assertEquals(15.0, $summary['totals']['current']['unpatched_servers']['value']);

        // The trend ends on the corrected score.
        $cards = collect($summary['data'])->firstWhere('name', 'Kartu Kredit');
        $history = $this->getJson("/api/departments/{$this->cards->id}/historical-risk")->json('data');
        $this->assertSame($cards['composite_score'], end($history)['composite_score']);

        $this->assertDatabaseHas('activity_logs', ['action' => 'dispute.accepted', 'subject_id' => $id, 'user_id' => $this->riskOfficer->id]);
    }

    public function test_it_risk_can_accept_a_different_figure_and_uptime_corrections_reach_the_bank_mean(): void
    {
        $id = $this->file('uptime', 99.9)->json('id');

        $this->review($id, 'accept', ['corrected_value' => 99.5])->assertOk();

        $this->assertEquals(99.5, $this->cardsKri('uptime')['value']);
        $totals = $this->actingAs($this->director)->getJson('/api/departments/risk-summary')->json('totals.current');
        $this->assertEquals(99.75, $totals['uptime']['value']); // (99.5×2 + 100×2) / 4
        $this->assertEquals(99.5, KriDispute::find($id)->corrected_value);

        $this->review($this->file('phishing_failure', 20)->json('id'), 'accept', ['corrected_value' => 101])
            ->assertUnprocessable()->assertJsonValidationErrors('corrected_value');
    }

    public function test_rejection_needs_a_reason_and_leaves_the_figure_alone(): void
    {
        $id = $this->file()->json('id');

        $this->review($id, 'reject')->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->review($id, 'reject', ['note' => 'Server masih aktif menurut pemindaian 28 September.'])->assertOk();

        $this->assertEquals(40.0, $this->cardsKri('unpatched_servers')['value']);
        $this->review($id, 'accept')->assertConflict(); // already decided
        $this->file(proposed: 5)->assertCreated();       // and the owner may try again
    }

    public function test_only_it_risk_reviews(): void
    {
        $id = $this->file()->json('id');

        $this->review($id, 'accept', as: $this->owner)->assertForbidden();
        $this->review($id, 'accept', as: $this->auditor)->assertForbidden();
        $this->review($id, 'accept', as: $this->director)->assertForbidden();
        $this->assertSame(KriDispute::STATUS_PENDING, KriDispute::find($id)->status);
    }

    public function test_visibility_follows_need_to_know(): void
    {
        $id = $this->file(extra: ['files' => [UploadedFile::fake()->create('cmdb-export.pdf', 120, 'application/pdf')]])->json('id');
        $attachmentId = KriDispute::find($id)->attachments->first()->id;

        $this->actingAs($this->otherOwner)->getJson("/api/kri-disputes/{$id}")->assertForbidden();
        $this->actingAs($this->otherOwner)->getJson('/api/kri-disputes')->assertJsonCount(0, 'data');
        $this->actingAs($this->otherOwner)->getJson("/api/attachments/{$attachmentId}")->assertForbidden();

        $this->actingAs($this->director)->getJson('/api/kri-disputes')->assertJsonCount(1, 'data');
        $this->actingAs($this->riskOfficer)->getJson("/api/kri-disputes/{$id}")
            ->assertOk()
            ->assertJsonPath('dispute.reason', self::REASON)
            ->assertJsonPath('baseline.current', 8)
            ->assertJsonPath('permissions.can_review', true);
        $this->actingAs($this->owner)->getJson("/api/kri-disputes/{$id}")->assertJsonPath('permissions.can_review', false);
    }

    public function test_work_queue_shows_disputes_to_it_risk_and_to_the_waiting_owner(): void
    {
        $id = $this->file()->json('id');

        $this->actingAs($this->riskOfficer)->getJson('/api/work-queue')
            ->assertOk()
            ->assertJsonPath('counts.action_needed', 1)
            ->assertJsonPath('disputes.0.id', $id);
        $this->actingAs($this->owner)->getJson('/api/work-queue')
            ->assertJsonPath('counts.waiting', 1)
            ->assertJsonPath('disputes.0.id', $id);
        $this->actingAs($this->otherOwner)->getJson('/api/work-queue')->assertJsonCount(0, 'disputes');
        $this->actingAs($this->auditor)->getJson('/api/work-queue')->assertJsonCount(0, 'disputes');

        $this->review($id, 'accept');
        $this->actingAs($this->riskOfficer)->getJson('/api/work-queue')->assertJsonPath('counts.action_needed', 0);
    }

    public function test_dispute_messages_follow_the_accept_language_header(): void
    {
        $this->actingAs($this->auditor)
            ->postJson('/api/kri-disputes', ['kri' => 'unpatched_servers', 'proposed_value' => 4, 'reason' => self::REASON], ['Accept-Language' => 'en'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only a unit owner can dispute figures of their own department.');
    }

    private function servers(Department $department, int $compliant, int $nonCompliant): void
    {
        static $host = 0;
        foreach ([true => $compliant, false => $nonCompliant] as $isCompliant => $count) {
            for ($i = 0; $i < $count; $i++) {
                $host++;
                Server::create([
                    'department_id' => $department->id,
                    'hostname' => "test-app-prd-{$host}",
                    'ip_address' => "10.98.0.{$host}",
                    'os_version' => 'Red Hat Enterprise Linux 7.9',
                    'is_compliant' => (bool) $isCompliant,
                ]);
            }
        }
    }
}
