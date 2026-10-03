<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\DepartmentRiskService;
use App\Services\KriDisputeWorkflow;
use Illuminate\Database\Seeder;

/**
 * Demo mode only: one open dispute, so IT Risk's queue has something to decide on first visit.
 * Filed through the workflow so it gets the same audit trail as a dispute made in the UI.
 */
class DemoDisputeSeeder extends Seeder
{
    private const DECOMMISSIONED_SERVERS = 14;

    public function run(KriDisputeWorkflow $workflow, DepartmentRiskService $risk): void
    {
        if (! config('kri.demo_mode')) {
            return;
        }

        $owner = User::query()->where('email', 'ultramikro@kri.test')->first();
        if (! $owner?->department_id) {
            return;
        }

        $current = $risk->disputeBaseline($owner->department_id, 'unpatched_servers')['current'];
        $workflow->submit(
            $owner,
            'unpatched_servers',
            max(0, $current - self::DECOMMISSIONED_SERVERS),
            sprintf(
                '%d server Red Hat Enterprise Linux 7.9 sudah dinonaktifkan pada 22 September 2026 (CR-2026-0917), '
                .'tetapi belum dihapus dari CMDB sehingga masih terhitung belum di-patch.',
                self::DECOMMISSIONED_SERVERS,
            ),
        );

        $this->command?->info('1 demo dispute filed');
    }
}
