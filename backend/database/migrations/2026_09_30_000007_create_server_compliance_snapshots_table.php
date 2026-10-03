<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Monthly patch-compliance snapshot per department. `servers` stays the current inventory;
        // this table holds the month-end picture used by the 6-month historical risk trend.
        Schema::create('server_compliance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7); // YYYY-MM
            $table->unsignedInteger('total_servers');
            $table->unsignedInteger('non_compliant');
            $table->timestamps();

            $table->unique(['department_id', 'month']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_compliance_snapshots');
    }
};
