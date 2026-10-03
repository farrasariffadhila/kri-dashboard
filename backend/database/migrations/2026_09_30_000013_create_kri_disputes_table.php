<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A unit owner challenges a KRI figure for the reporting month; IT Risk accepts (with a corrected
        // figure) or rejects it. Figures are in the KRI's raw unit: server count, employee count, or uptime %.
        Schema::create('kri_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('kri', 32);
            $table->char('month', 7);
            $table->decimal('source_value', 10, 2);   // figure from the source system when the dispute was filed
            $table->decimal('proposed_value', 10, 2); // figure the unit owner says is correct
            $table->decimal('corrected_value', 10, 2)->nullable(); // figure IT Risk accepted
            $table->text('reason');
            $table->string('status', 16)->default('pending');
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['month', 'status']);
            $table->index(['department_id', 'kri', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kri_disputes');
    }
};
