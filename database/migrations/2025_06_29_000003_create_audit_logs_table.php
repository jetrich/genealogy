<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Core audit information
            $table->string('action')->index();
            $table->string('category')->index();
            $table->string('request_id')->unique()->index();
            
            // User context
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('user_email')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            
            // Request context
            $table->ipAddress('ip_address')->index();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->string('referer')->nullable();
            
            // Genealogy context
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('set null');
            $table->string('subject_type')->nullable()->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            
            // Audit metadata
            $table->json('context')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $table->boolean('requires_review')->default(false)->index();
            $table->boolean('reviewed')->default(false)->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('review_notes')->nullable();
            
            // Security context
            $table->string('device_fingerprint')->nullable()->index();
            $table->boolean('suspicious_activity')->default(false)->index();
            $table->boolean('cross_team_access')->default(false)->index();
            
            // Compliance
            $table->timestamp('retention_until')->nullable()->index();
            $table->boolean('exported')->default(false);
            $table->timestamp('exported_at')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance and compliance
            $table->index(['created_at', 'category']);
            $table->index(['user_id', 'created_at']);
            $table->index(['team_id', 'created_at']);
            $table->index(['category', 'severity', 'created_at']);
            $table->index(['requires_review', 'reviewed']);
            $table->index(['ip_address', 'created_at']);
            
            // Full-text search index for action (context is JSON, can't be in FULLTEXT)
            $table->fullText(['action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};