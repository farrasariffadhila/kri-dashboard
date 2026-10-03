<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo accounts, one per role and one unit owner per department, flagged is_demo so the
 * password-less demo sign-in (KRI_DEMO_MODE) can use them. If DEMO_USER_PASSWORD is set they also
 * work with the normal sign-in form; otherwise they get a random password nobody knows.
 */
class DemoUserSeeder extends Seeder
{
    /** [email, name, role, department name or null] */
    private const USERS = [
        ['direksi@kri.test', 'Hendra Wijaya', Role::Director, null],
        ['auditor@kri.test', 'Fajar Nugroho', Role::Auditor, null],
        ['itrisk@kri.test', 'Maya Anggraini', Role::RiskOfficer, null],
        ['kartukredit@kri.test', 'Rina Kartika', Role::UnitOwner, 'Kartu Kredit'],
        ['ultramikro@kri.test', 'Agus Prasetyo', Role::UnitOwner, 'Ultra Mikro'],
        ['digitalbanking@kri.test', 'Dewi Lestari', Role::UnitOwner, 'Digital Banking'],
        ['treasury@kri.test', 'Bayu Saputra', Role::UnitOwner, 'Treasury'],
        ['hrd@kri.test', 'Sari Wulandari', Role::UnitOwner, 'HRD'],
    ];

    public function run(): void
    {
        $password = config('kri.demo_password') ?: Str::password(32);

        $departments = Department::query()->pluck('id', 'name');

        foreach (self::USERS as [$email, $name, $role, $departmentName]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->password = $password;
            $user->role = $role;
            $user->department_id = $departmentName ? ($departments[$departmentName] ?? null) : null;
            $user->is_demo = true;
            $user->save();
        }

        $this->command?->info(count(self::USERS).' demo accounts ready'.(config('kri.demo_password') ? ' (password: DEMO_USER_PASSWORD in .env)' : ''));
    }
}
