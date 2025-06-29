<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\UidProcessor;

/**
 * Custom formatter for audit logs with compliance focus.
 */
class AuditLogFormatter
{
    public function __invoke(Logger $logger): void
    {
        // Add processors for enhanced audit context
        $logger->pushProcessor(new UidProcessor()); // Unique ID per log entry
        $logger->pushProcessor(new WebProcessor()); // Web request context
        $logger->pushProcessor(new IntrospectionProcessor()); // Code location
        
        // Add custom processor for audit-specific data
        $logger->pushProcessor(function ($record) {
            $record['extra']['audit_version'] = '1.0';
            $record['extra']['compliance_level'] = 'high';
            $record['extra']['retention_policy'] = '7_years';
            $record['extra']['log_source'] = 'genealogy_application';
            
            return $record;
        });
    }
}