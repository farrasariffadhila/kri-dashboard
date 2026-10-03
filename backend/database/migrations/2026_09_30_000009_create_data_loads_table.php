<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ingestion log: which file fed which table, when, and with what content (SHA-256),
        // so every number on the dashboard can be traced back to its source extract.
        Schema::create('data_loads', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 64);
            $table->string('source_file');
            $table->unsignedInteger('row_count');
            $table->char('checksum', 64); // SHA-256 of the source file
            $table->timestamp('loaded_at');
            $table->timestamps();

            $table->index(['table_name', 'loaded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_loads');
    }
};
