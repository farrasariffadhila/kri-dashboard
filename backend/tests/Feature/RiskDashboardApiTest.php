<?php

namespace Tests\Feature;

use App\Models\AuditIssue;
use App\Models\Department;
use App\Models\Incident;
use App\Models\PhishingTest;
use App\Models\Server;
use App\Models\ServerComplianceSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiskDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private Department $cards;

    private Department $treasury;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kri.reporting_month' => '2026-09']);
        $this->actingAs(User::factory()->create());

        $this->cards = Department::create(['name' => 'Kartu Kredit']);
        $this->treasury = Department::create(['name' => 'Treasury']);

        // Kartu Kredit: 5 of 10 servers non-compliant (50% -> score 100)
        $this->servers($this->cards, compliant: 5, nonCompliant: 5);
        // 3 open High findings (-> score 30), one of them overdue at 2026-09-30
        $this->audit($this->cards, '2026-08-01', '2026-08-31', null);
        $this->audit($this->cards, '2026-09-10', '2026-10-10', null);
        $this->audit($this->cards, '2026-09-15', '2026-10-15', null);
        // Closed in September, so it was still open at the end of August
        $this->audit($this->cards, '2026-08-10', '2026-09-09', '2026-09-05');
        $this->incidents($this->cards, '2026-09', [99.0, 99.0]); // -> score 100
        PhishingTest::create(['department_id' => $this->cards->id, 'month' => '2026-09', 'total_employees_tested' => 100, 'total_failed' => 30]); // 30% -> 100
        ServerComplianceSnapshot::create(['department_id' => $this->cards->id, 'month' => '2026-08', 'total_servers' => 10, 'non_compliant' => 1]);

        // Treasury: clean on every KRI
        $this->servers($this->treasury, compliant: 10, nonCompliant: 0);
        $this->incidents($this->treasury, '2026-09', [100.0]);
        PhishingTest::create(['department_id' => $this->treasury->id, 'month' => '2026-09', 'total_employees_tested' => 100, 'total_failed' => 2]);
    }

    public function test_risk_summary_scores_and_ranks_departments(): void
    {
        $response = $this->getJson('/api/departments/risk-summary')->assertOk();

        $response->assertJsonPath('reporting_month', '2026-09')
            ->assertJsonPath('as_of', '2026-09-30')
            ->assertJsonPath('data.0.name', 'Kartu Kredit')
            ->assertJsonPath('data.0.composite_score', 82.5) // .35*100 + .25*30 + .25*100 + .15*100
            ->assertJsonPath('data.0.rating', 'Critical')
            ->assertJsonPath('data.0.kris.unpatched_servers.value', 50)
            ->assertJsonPath('data.0.kris.unpatched_servers.non_compliant', 5)
            ->assertJsonPath('data.0.kris.open_high_audits.value', 3)
            ->assertJsonPath('data.0.kris.open_high_audits.score', 30)
            ->assertJsonPath('data.0.kris.open_high_audits.overdue', 1)
            ->assertJsonPath('data.0.kris.open_high_audits.findings.0.days_overdue', 30)
            ->assertJsonPath('data.0.kris.uptime.value', 99)
            ->assertJsonPath('data.0.kris.phishing_failure.value', 30)
            ->assertJsonPath('data.1.name', 'Treasury')
            ->assertJsonPath('data.1.composite_score', 0)
            ->assertJsonPath('data.1.rating', 'Safe')
            ->assertJsonCount(3, 'data.0.kris.open_high_audits.findings')
            ->assertJsonStructure(['methodology' => ['weights', 'thresholds', 'anchor_scores', 'bands']]);
    }

    public function test_historical_risk_returns_six_months_ending_on_the_summary_score(): void
    {
        $response = $this->getJson("/api/departments/{$this->cards->id}/historical-risk")->assertOk();

        $response->assertJsonCount(6, 'data')
            ->assertJsonPath('department.name', 'Kartu Kredit')
            ->assertJsonPath('data.0.month', '2026-04')
            ->assertJsonPath('data.5.month', '2026-09')
            ->assertJsonPath('data.5.composite_score', 82.5);

        // August: snapshot 10% unpatched (-> 50) and two High findings open at month end (-> 20).
        // No incident or phishing data, so only those two weights count: (.35*50 + .25*20) / .60
        $response->assertJsonPath('data.4.kris.unpatched_servers.value', 10)
            ->assertJsonPath('data.4.kris.open_high_audits.value', 2)
            ->assertJsonPath('data.4.kris.uptime.value', null)
            ->assertJsonPath('data.4.composite_score', round((0.35 * 50 + 0.25 * 20) / 0.60, 1));
    }

    public function test_risk_summary_includes_previous_month_and_bank_totals(): void
    {
        // August for Kartu Kredit: snapshot 10% (-> 50), two High findings open at month end (-> 20),
        // no incident or phishing data: (.35*50 + .25*20) / .60
        $this->getJsonSummary()
            ->assertJsonPath('previous_month', '2026-08')
            ->assertJsonPath('data.0.previous_composite_score', round((0.35 * 50 + 0.25 * 20) / 0.60, 1))
            ->assertJsonPath('data.1.previous_composite_score', 0)
            ->assertJsonPath('totals.current.unpatched_servers.non_compliant', 5)
            ->assertJsonPath('totals.current.unpatched_servers.total_servers', 20)
            ->assertJsonPath('totals.current.unpatched_servers.value', 25)
            ->assertJsonPath('totals.current.unpatched_servers.score', 81.3) // 75 + (25 - 20) / (40 - 20) * 25
            ->assertJsonPath('totals.current.unpatched_servers.rating', 'Critical')
            ->assertJsonPath('totals.current.open_high_audits.value', 3)
            ->assertJsonPath('totals.current.open_high_audits.overdue', 1)
            ->assertJsonPath('totals.current.open_high_audits.rating', null) // a bank-wide count is not rated
            ->assertJsonPath('totals.current.phishing_failure.value', 16) // 32 of 200
            ->assertJsonPath('totals.previous.unpatched_servers.value', 10)
            ->assertJsonPath('totals.previous.open_high_audits.value', 2)
            ->assertJsonPath('totals.previous.uptime.value', null);
    }

    public function test_audit_issue_list_filters_and_reports_overdue_days(): void
    {
        $this->getJson('/api/audit-issues?status=Open')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('as_of', '2026-09-30')
            ->assertJsonPath('data.0.due_date', '2026-08-31')
            ->assertJsonPath('data.0.days_overdue', 30)
            ->assertJsonPath('data.0.department.name', 'Kartu Kredit');

        $this->getJson('/api/audit-issues?overdue=1')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/audit-issues?status=Closed')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.days_overdue', null);
        $this->getJson("/api/audit-issues?department_id={$this->treasury->id}")->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/audit-issues?q=Test%20Aud')->assertOk()->assertJsonPath('total', 4);
        $this->getJson('/api/audit-issues?q=100%25')->assertOk()->assertJsonPath('total', 0); // % is matched literally
        $this->getJson('/api/audit-issues?sort=-due_date')->assertOk()->assertJsonPath('data.0.due_date', '2026-10-15');
    }

    public function test_server_list_filters_by_compliance_and_search(): void
    {
        $this->getJson('/api/servers?compliance=non_compliant')
            ->assertOk()
            ->assertJsonPath('total', 5)
            ->assertJsonPath('data.0.is_compliant', false)
            ->assertJsonPath('facets.os_versions', ['Red Hat Enterprise Linux 7.9', 'Red Hat Enterprise Linux 9.4']);

        $this->getJson("/api/servers?department_id={$this->treasury->id}&compliance=compliant")->assertOk()->assertJsonPath('total', 10);
        $this->getJson('/api/servers?os_version=Red%20Hat%20Enterprise%20Linux%207.9')->assertOk()->assertJsonPath('total', 5);
        $this->getJson('/api/servers?q=10.99.0.1&per_page=10')->assertOk()->assertJsonPath('per_page', 10);
    }

    public function test_list_endpoints_reject_invalid_filters(): void
    {
        $this->getJson('/api/audit-issues?risk_level=Critical')->assertUnprocessable()->assertJsonValidationErrors('risk_level');
        $this->getJson('/api/audit-issues?sort=title')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson('/api/servers?department_id=999')->assertUnprocessable()->assertJsonValidationErrors('department_id');
        $this->getJson('/api/servers?per_page=5000')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    private function getJsonSummary()
    {
        return $this->getJson('/api/departments/risk-summary')->assertOk();
    }

    public function test_unknown_department_returns_generic_json_404_in_the_requested_language(): void
    {
        $this->get('/api/departments/999/historical-risk')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Data tidak ditemukan.']);

        $this->get('/api/departments/999/historical-risk', ['Accept-Language' => 'en'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.']);
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
                    'ip_address' => "10.99.0.{$host}",
                    'os_version' => $isCompliant ? 'Red Hat Enterprise Linux 9.4' : 'Red Hat Enterprise Linux 7.9',
                    'is_compliant' => (bool) $isCompliant,
                ]);
            }
        }
    }

    private function audit(Department $department, string $identified, string $due, ?string $closed): void
    {
        AuditIssue::create([
            'department_id' => $department->id,
            'title' => 'Patch keamanan kritikal belum diterapkan lebih dari 30 hari',
            'auditor' => 'Test Auditor',
            'risk_level' => AuditIssue::RISK_HIGH,
            'status' => $closed ? AuditIssue::STATUS_CLOSED : AuditIssue::STATUS_OPEN,
            'identified_date' => $identified,
            'due_date' => $due,
            'closed_date' => $closed,
        ]);
    }

    private function incidents(Department $department, string $month, array $uptimes): void
    {
        foreach ($uptimes as $uptime) {
            Incident::create([
                'department_id' => $department->id,
                'month' => $month,
                'total_downtime_minutes' => (int) round((100 - $uptime) / 100 * 43200),
                'actual_uptime_pct' => $uptime,
            ]);
        }
    }
}
