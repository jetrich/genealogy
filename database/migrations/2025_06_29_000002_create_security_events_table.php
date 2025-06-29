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
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->ipAddress('ip_address')->index();
            $table->text('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium')->index();
            $table->boolean('resolved')->default(false)->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['created_at', 'severity']);
            $table->index(['event_type', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['resolved', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};