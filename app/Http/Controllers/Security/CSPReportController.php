<?php

declare(strict_types=1);

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Content Security Policy violation reporting endpoint.
 */
class CSPReportController extends Controller
{
    /**
     * Handle CSP violation reports.
     */
    public function report(Request $request)
    {
        try {
            $report = $request->getContent();
            $reportData = json_decode($report, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response('Invalid JSON', 400);
            }
            
            // Log CSP violation for analysis
            Log::channel('security')->warning('CSP Violation Report', [
                'report' => $reportData,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
                'timestamp' => now()->toISOString(),
            ]);
            
            // Store in security events for tracking
            if (isset($reportData['csp-report'])) {
                $violation = $reportData['csp-report'];
                
                \DB::table('security_events')->insert([
                    'event_type' => 'csp_violation',
                    'user_id' => auth()->id(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'context' => json_encode([
                        'violated_directive' => $violation['violated-directive'] ?? null,
                        'blocked_uri' => $violation['blocked-uri'] ?? null,
                        'document_uri' => $violation['document-uri'] ?? null,
                        'source_file' => $violation['source-file'] ?? null,
                        'line_number' => $violation['line-number'] ?? null,
                    ]),
                    'severity' => 'medium',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            return response('', 204);
            
        } catch (\Exception $e) {
            Log::error('Failed to process CSP report: ' . $e->getMessage());
            return response('Internal Server Error', 500);
        }
    }
}