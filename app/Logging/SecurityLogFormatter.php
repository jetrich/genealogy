<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\UidProcessor;

/**
 * Custom formatter for security logs with enhanced context.
 */
class SecurityLogFormatter
{
    public function __invoke(Logger $logger): void
    {
        // Add processors for enhanced security context
        $logger->pushProcessor(new UidProcessor()); // Unique ID per log entry
        $logger->pushProcessor(new WebProcessor()); // Web request context
        $logger->pushProcessor(new IntrospectionProcessor()); // Code location
        
        // Add custom processor for security-specific data
        $logger->pushProcessor(function ($record) {
            $record['extra']['security_version'] = '2.0';
            $record['extra']['threat_level'] = 'monitored';
            $record['extra']['incident_response'] = 'automated';
            $record['extra']['log_source'] = 'genealogy_security';
            $record['extra']['correlation_id'] = uniqid('sec_', true);
            
            return $record;
        });
    }
}