<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Descriptive fields for the KRI drill-down (which findings are open, on which hosts).
        // Nullable so the migration also runs on tables that already hold rows.
        Schema::table('audit_issues', function (Blueprint $table) {
            $table->string('title')->nullable()->after('department_id');
            $table->string('auditor', 100)->nullable()->after('title');
        });

        Schema::table('servers', function (Blueprint $table) {
            $table->string('hostname', 63)->nullable()->unique()->after('department_id'); // 63 = DNS label limit
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropUnique(['hostname']);
            $table->dropColumn('hostname');
        });

        Schema::table('audit_issues', function (Blueprint $table) {
            $table->dropColumn(['title', 'auditor']);
        });
    }
};
