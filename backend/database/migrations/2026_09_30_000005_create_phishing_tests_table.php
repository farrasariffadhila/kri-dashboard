<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phishing_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->char('month', 7); // YYYY-MM
            $table->unsignedInteger('total_employees_tested');
            $table->unsignedInteger('total_failed');
            $table->timestamps();

            // KRI: phishing failure rate per department per month
            $table->index(['department_id', 'month']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phishing_tests');
    }
};
