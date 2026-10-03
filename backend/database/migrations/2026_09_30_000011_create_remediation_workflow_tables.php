<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_issues', function (Blueprint $table) {
            // Denormalised current state of the workflow, for fast listing and filtering.
            $table->string('remediation_status', 24)->nullable()->after('status');
            $table->index('remediation_status');
        });

        // Each action plan or evidence package a unit owner submits, and the auditor's decision on it.
        Schema::create('remediation_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_issue_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['plan', 'evidence']);
            $table->text('description');
            $table->date('target_date')->nullable(); // action plans only
            $table->enum('status', ['pending', 'approved', 'returned'])->default('pending');
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['audit_issue_id', 'type', 'status']);
        });

        // Uploaded files (stored outside the public folder), attachable to any workflow record.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable');
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->char('checksum', 64); // SHA-256
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        // Append-only audit trail. Rows are never updated or deleted by the application.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('remediation_submissions');
        Schema::table('audit_issues', function (Blueprint $table) {
            $table->dropIndex(['remediation_status']);
            $table->dropColumn('remediation_status');
        });
    }
};
