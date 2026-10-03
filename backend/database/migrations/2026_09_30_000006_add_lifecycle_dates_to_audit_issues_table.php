<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_issues', function (Blueprint $table) {
            // Lifecycle dates let us rebuild "open as of month X" for the historical trend.
            // An issue is open at date D when identified_date <= D and (closed_date IS NULL or closed_date > D).
            $table->date('identified_date')->nullable()->after('status');
            $table->date('closed_date')->nullable()->after('due_date');

            $table->index(['department_id', 'risk_level', 'identified_date', 'closed_date'], 'audit_issues_history_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_issues', function (Blueprint $table) {
            $table->dropIndex('audit_issues_history_index');
            $table->dropColumn(['identified_date', 'closed_date']);
        });
    }
};
