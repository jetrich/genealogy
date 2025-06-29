<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description');
            $table->string('category')->default('general'); // admin, system, user_management, etc.
            $table->boolean('is_sensitive')->default(false); // Requires additional authorization
            $table->timestamps();
            
            $table->index(['category', 'name']);
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->timestamp('granted_at');
            $table->foreignId('granted_by')->constrained('users');
            $table->timestamp('expires_at')->nullable(); // Optional expiration
            $table->text('justification')->nullable(); // Why was permission granted
            $table->timestamps();
            
            $table->unique(['user_id', 'permission_id']);
            $table->index(['user_id', 'granted_at']);
            $table->index(['expires_at']);
        });

        Schema::create('permission_audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->string('action'); // granted, revoked, used
            $table->foreignId('performed_by')->constrained('users');
            $table->json('context')->nullable(); // Additional context about the action
            $table->timestamp('performed_at');
            $table->ipAddress('ip_address');
            $table->text('user_agent')->nullable();
            
            $table->index(['user_id', 'performed_at']);
            $table->index(['permission_id', 'action']);
            $table->index(['performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_audit_log');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('permissions');
    }
};