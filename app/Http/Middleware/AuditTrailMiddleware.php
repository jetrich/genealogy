<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\SecurityAuditService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for automatic audit trail logging of all requests.
 */
class AuditTrailMiddleware
{
    /**
     * Handle an incoming request and log it for audit purposes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        
        // Skip audit logging for certain routes to avoid noise
        $skipRoutes = [
            'livewire/update',
            'livewire/message',
            '_ignition',
            'telescope',
            'horizon',
            'debugbar',
        ];
        
        $shouldSkip = collect($skipRoutes)->some(fn($route) => 
            str_contains($request->path(), $route)
        );
        
        if (!$shouldSkip && auth()->check()) {
            $this->logRequestStart($request);
        }

        $response = $next($request);
        
        if (!$shouldSkip && auth()->check()) {
            $this->logRequestComplete($request, $response, $startTime);
        }

        return $response;
    }

    /**
     * Log the start of a request.
     */
    private function logRequestStart(Request $request): void
    {
        $context = [
            'request_method' => $request->method(),
            'request_path' => $request->path(),
            'request_params' => $this->getSafeParameters($request),
            'request_headers' => $this->getSafeHeaders($request),
            'request_size' => strlen($request->getContent()),
        ];

        // Log different types of requests with appropriate detail
        if ($this->isAdminRequest($request)) {
            SecurityAuditService::logAdminAction('admin_request', $context);
        } elseif ($this->isGenealogyRequest($request)) {
            SecurityAuditService::logGenealogyAction('genealogy_request', null, $context);
        } elseif ($this->isAuthRequest($request)) {
            SecurityAuditService::logUserAction('auth_request', $context);
        }
    }

    /**
     * Log the completion of a request.
     */
    private function logRequestComplete(Request $request, Response $response, float $startTime): void
    {
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        
        $context = [
            'response_status' => $response->getStatusCode(),
            'response_size' => strlen($response->getContent()),
            'execution_time_ms' => round($executionTime, 2),
            'memory_usage' => memory_get_peak_usage(true),
            'sql_queries' => \DB::getQueryLog() ? count(\DB::getQueryLog()) : 0,
        ];

        // Log performance issues
        if ($executionTime > 5000) { // Slower than 5 seconds
            SecurityAuditService::logAdminAction('slow_request_detected', array_merge($context, [
                'performance_issue' => true,
                'requires_investigation' => true,
            ]));
        }

        // Log error responses
        if ($response->getStatusCode() >= 400) {
            SecurityAuditService::logAdminAction('request_error', array_merge($context, [
                'error_status' => $response->getStatusCode(),
                'requires_review' => $response->getStatusCode() >= 500,
            ]));
        }
    }

    /**
     * Check if request is for admin functionality.
     */
    private function isAdminRequest(Request $request): bool
    {
        $adminPaths = [
            'admin/',
            'developer/',
            'back/',
        ];

        return collect($adminPaths)->some(fn($path) => 
            str_starts_with($request->path(), $path)
        );
    }

    /**
     * Check if request is for genealogy data.
     */
    private function isGenealogyRequest(Request $request): bool
    {
        $genealogyPaths = [
            'people/',
            'person/',
            'couples/',
            'family/',
            'gedcom/',
        ];

        return collect($genealogyPaths)->some(fn($path) => 
            str_starts_with($request->path(), $path)
        );
    }

    /**
     * Check if request is for authentication.
     */
    private function isAuthRequest(Request $request): bool
    {
        $authPaths = [
            'login',
            'register',
            'logout',
            'password/',
            'two-factor/',
        ];

        return collect($authPaths)->some(fn($path) => 
            str_starts_with($request->path(), $path)
        );
    }

    /**
     * Get safe request parameters (excluding sensitive data).
     */
    private function getSafeParameters(Request $request): array
    {
        $sensitive = [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'api_token',
            '_token',
            'two_factor_code',
            'recovery_code',
        ];

        $params = $request->all();
        
        foreach ($sensitive as $field) {
            if (isset($params[$field])) {
                $params[$field] = '[REDACTED]';
            }
        }

        return $params;
    }

    /**
     * Get safe request headers (excluding sensitive data).
     */
    private function getSafeHeaders(Request $request): array
    {
        $sensitive = [
            'authorization',
            'cookie',
            'x-api-key',
            'x-auth-token',
        ];

        $headers = $request->headers->all();
        
        foreach ($sensitive as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }

        return $headers;
    }
}