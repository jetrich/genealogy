<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\UidProcessor;

/**
 * Custom formatter for admin logs with privilege tracking.
 */
class AdminLogFormatter
{
    public function __invoke(Logger $logger): void
    {
        // Add processors for enhanced admin context
        $logger->pushProcessor(new UidProcessor()); // Unique ID per log entry
        $logger->pushProcessor(new WebProcessor()); // Web request context
        $logger->pushProcessor(new IntrospectionProcessor()); // Code location
        
        // Add custom processor for admin-specific data
        $logger->pushProcessor(function ($record) {
            $record['extra']['admin_version'] = '1.0';
            $record['extra']['privilege_level'] = 'high';
            $record['extra']['approval_required'] = 'manual';
            $record['extra']['log_source'] = 'genealogy_admin';
            $record['extra']['escalation_level'] = auth()->user()?->is_developer ? 'system' : 'admin';
            
            return $record;
        });
    }
}