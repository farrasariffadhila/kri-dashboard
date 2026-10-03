<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7); // YYYY-MM
            $table->unsignedInteger('total_downtime_minutes')->default(0);
            $table->decimal('actual_uptime_pct', 5, 2); // 0.00 - 100.00
            $table->timestamps();

            // KRI: average uptime per department per month
            $table->index(['department_id', 'month']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
