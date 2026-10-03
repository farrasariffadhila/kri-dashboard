<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Least privilege by default: a user without an explicit role can only view.
            $table->enum('role', ['director', 'unit_owner', 'auditor', 'risk_officer'])->default('director')->after('email');
            // Set for unit owners: the only department whose findings they may act on.
            $table->foreignId('department_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('role');
        });
    }
};
