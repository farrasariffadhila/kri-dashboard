<?php

namespace Tests\Feature;

use App\Models\DataLoad;
use Database\Seeders\KriCsvSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DataLineageTest extends TestCase
{
    use RefreshDatabase;

    private string $csvDir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kri.reporting_month' => '2026-09']);
        $this->actingAs(User::factory()->create());

        $this->csvDir = sys_get_temp_dir().'/kri-lineage-'.uniqid();
        File::makeDirectory($this->csvDir);
        $files = [
            'departments' => "id,name\n1,Treasury\n",
            'servers' => "id,department_id,hostname,ip_address,os_version,is_compliant\n1,1,trs-app-prd-001,10.45.0.2,Red Hat Enterprise Linux 9.4,1\n2,1,trs-db-prd-001,10.45.0.3,Red Hat Enterprise Linux 7.9,0\n",
            'audit_issues' => "id,department_id,title,auditor,risk_level,status,identified_date,due_date,closed_date\n1,1,Contoh temuan,Auditor Uji,High,Open,2026-09-01,2026-10-01,\n",
            'incidents' => "id,department_id,month,total_downtime_minutes,actual_uptime_pct\n1,1,2026-09,0,100.00\n",
            'phishing_tests' => "id,department_id,month,total_employees_tested,total_failed\n1,1,2026-09,100,5\n",
            'server_compliance_snapshots' => "id,department_id,month,total_servers,non_compliant\n1,1,2026-09,2,1\n",
        ];
        foreach ($files as $table => $csv) {
            File::put("{$this->csvDir}/{$table}.csv", $csv);
        }
        config(['kri.csv_path' => $this->csvDir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->csvDir);
        parent::tearDown();
    }

    public function test_seeder_records_every_import_with_row_count_and_checksum(): void
    {
        $this->seed(KriCsvSeeder::class);

        $this->assertSame(6, DataLoad::count());
        $servers = DataLoad::where('table_name', 'servers')->sole();
        $this->assertSame('servers.csv', $servers->source_file);
        $this->assertSame(2, $servers->row_count);
        $this->assertSame(hash_file('sha256', "{$this->csvDir}/servers.csv"), $servers->checksum);
    }

    public function test_summary_exposes_source_system_and_latest_import_per_kri(): void
    {
        $this->seed(KriCsvSeeder::class);
        $this->seed(KriCsvSeeder::class); // a re-import adds history; the summary shows the latest one

        $this->assertSame(12, DataLoad::count());
        $latestId = DataLoad::where('table_name', 'audit_issues')->max('id');

        $this->getJson('/api/departments/risk-summary')
            ->assertOk()
            ->assertJsonPath('data_sources.open_high_audits.system.id', 'Aplikasi GRC, modul temuan audit')
            ->assertJsonPath('data_sources.open_high_audits.system.en', 'GRC application, audit findings module')
            ->assertJsonPath('data_sources.open_high_audits.simulated', true)
            ->assertJsonPath('data_sources.open_high_audits.loads.0.table', 'audit_issues')
            ->assertJsonPath('data_sources.open_high_audits.loads.0.row_count', 1)
            ->assertJsonPath('data_sources.open_high_audits.loads.0.checksum', DataLoad::find($latestId)->checksum)
            ->assertJsonPath('data_sources.unpatched_servers.loads.1.table', 'server_compliance_snapshots');
    }

    public function test_sources_without_an_import_report_null_instead_of_failing(): void
    {
        $this->getJson('/api/departments/risk-summary')
            ->assertOk()
            ->assertJsonPath('data_sources.uptime.loads.0.loaded_at', null);
    }
}
