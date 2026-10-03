<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->unique(); // 45 chars fits IPv6
            $table->string('os_version', 100);
            $table->boolean('is_compliant')->default(false);
            $table->timestamps();

            // KRI: % unpatched servers per department
            $table->index(['department_id', 'is_compliant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
