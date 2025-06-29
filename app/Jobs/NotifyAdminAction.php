<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

/**
 * Job to notify administrators of critical actions.
 */
class NotifyAdminAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $action;
    public array $context;

    /**
     * Create a new job instance.
     */
    public function __construct(string $action, array $context)
    {
        $this->action = $action;
        $this->context = $context;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Log the notification attempt
        Log::channel('admin')->info('Sending admin action notification', [
            'action' => $this->action,
            'notification_context' => $this->context,
        ]);

        // Get all developer users for critical notifications
        $developers = User::where('is_developer', true)->get();

        if ($developers->isEmpty()) {
            Log::channel('admin')->warning('No developer users found for admin action notification');
            return;
        }

        // For now, just log the notification - in production, this would send emails
        foreach ($developers as $developer) {
            Log::channel('admin')->info('Admin action notification queued', [
                'recipient' => $developer->email,
                'action' => $this->action,
                'timestamp' => now()->toISOString(),
            ]);
        }

        // Future implementation would include:
        // - Email notifications to administrators
        // - Slack/Discord webhook notifications
        // - SMS alerts for critical actions
        // - Dashboard real-time notifications
    }
}