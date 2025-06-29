<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Comprehensive security headers middleware for genealogy application.
 * Implements defense-in-depth security headers to protect against various attacks.
 */
class SecurityHeaders
{
    /**
     * Security headers configuration for different environments.
     */
    private const SECURITY_HEADERS = [
        'production' => [
            'strict_csp' => true,
            'hsts_max_age' => 31536000, // 1 year
            'frame_options' => 'DENY',
            'content_type_options' => 'nosniff',
            'xss_protection' => '1; mode=block',
            'referrer_policy' => 'strict-origin-when-cross-origin',
        ],
        'local' => [
            'strict_csp' => false,
            'hsts_max_age' => 0,
            'frame_options' => 'DENY',
            'content_type_options' => 'nosniff',
            'xss_protection' => '1; mode=block',
            'referrer_policy' => 'no-referrer-when-downgrade',
        ],
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        $environment = app()->environment();
        $config = self::SECURITY_HEADERS[$environment] ?? self::SECURITY_HEADERS['production'];
        
        // Apply security headers based on environment
        $this->applyContentSecurityPolicy($response, $request, $config);
        $this->applyFrameProtection($response, $config);
        $this->applyContentTypeProtection($response, $config);
        $this->applyXSSProtection($response, $config);
        $this->applyReferrerPolicy($response, $config);
        $this->applyPermissionsPolicy($response);
        $this->applyTransportSecurity($response, $request, $config);
        $this->applyCrossOriginPolicies($response);
        $this->applyAdditionalSecurityHeaders($response);
        $this->removeServerHeaders($response);
        
        return $response;
    }
    
    /**
     * Apply Content Security Policy based on genealogy application needs.
     */
    private function applyContentSecurityPolicy($response, Request $request, array $config): void
    {
        if ($config['strict_csp']) {
            // Production CSP - Very strict
            $csp = [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
                "font-src 'self' https://fonts.gstatic.com",
                "img-src 'self' data: https: blob:",
                "media-src 'self' blob:",
                "object-src 'none'",
                "frame-src 'none'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "connect-src 'self' https:",
                "worker-src 'self' blob:",
                "manifest-src 'self'",
                "upgrade-insecure-requests",
            ];
        } else {
            // Development CSP - More permissive for Vite HMR
            $csp = [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
                "font-src 'self' https://fonts.gstatic.com",
                "img-src 'self' data: https: blob:",
                "media-src 'self' blob:",
                "object-src 'none'",
                "frame-src 'none'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "connect-src 'self' ws: wss: https:", // Allow WebSocket for Vite HMR
                "worker-src 'self' blob:",
                "manifest-src 'self'",
            ];
        }
        
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        
        // Also set CSP Report-Only for monitoring
        $reportOnlyCsp = array_merge($csp, ["report-uri /api/csp-report"]);
        $response->headers->set('Content-Security-Policy-Report-Only', implode('; ', $reportOnlyCsp));
    }
    
    /**
     * Apply frame protection headers.
     */
    private function applyFrameProtection($response, array $config): void
    {
        $response->headers->set('X-Frame-Options', $config['frame_options']);
    }
    
    /**
     * Apply content type protection.
     */
    private function applyContentTypeProtection($response, array $config): void
    {
        $response->headers->set('X-Content-Type-Options', $config['content_type_options']);
    }
    
    /**
     * Apply XSS protection headers.
     */
    private function applyXSSProtection($response, array $config): void
    {
        $response->headers->set('X-XSS-Protection', $config['xss_protection']);
    }
    
    /**
     * Apply referrer policy - Important for genealogy privacy.
     */
    private function applyReferrerPolicy($response, array $config): void
    {
        $response->headers->set('Referrer-Policy', $config['referrer_policy']);
    }
    
    /**
     * Apply permissions policy - Genealogy app doesn't need these features.
     */
    private function applyPermissionsPolicy($response): void
    {
        $permissions = [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'usb=()',
            'bluetooth=()',
            'accelerometer=()',
            'gyroscope=()',
            'magnetometer=()',
            'payment=()',
            'midi=()',
            'encrypted-media=()',
            'autoplay=()',
        ];
        
        $response->headers->set('Permissions-Policy', implode(', ', $permissions));
    }
    
    /**
     * Apply HTTP Strict Transport Security.
     */
    private function applyTransportSecurity($response, Request $request, array $config): void
    {
        if ($request->isSecure() && $config['hsts_max_age'] > 0) {
            $hsts = "max-age={$config['hsts_max_age']}; includeSubDomains";
            
            // Add preload directive for production
            if (app()->environment('production')) {
                $hsts .= '; preload';
            }
            
            $response->headers->set('Strict-Transport-Security', $hsts);
        }
    }
    
    /**
     * Apply cross-origin policies for enhanced security.
     */
    private function applyCrossOriginPolicies($response): void
    {
        // Cross-Origin Embedder Policy
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        
        // Cross-Origin Opener Policy  
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        
        // Cross-Origin Resource Policy
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
    }
    
    /**
     * Apply additional security headers.
     */
    private function applyAdditionalSecurityHeaders($response): void
    {
        // Prevent MIME type sniffing
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        
        // Prevent IE from executing downloads in site's context
        $response->headers->set('X-Download-Options', 'noopen');
        
        // DNS prefetch control
        $response->headers->set('X-DNS-Prefetch-Control', 'off');
        
        // Prevent browsers from MIME-sniffing the response
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Feature policy for older browsers
        $response->headers->set('Feature-Policy', 
            "camera 'none'; microphone 'none'; geolocation 'none'"
        );
    }
    
    /**
     * Remove server information headers.
     */
    private function removeServerHeaders($response): void
    {
        $headersToRemove = [
            'Server',
            'X-Powered-By',
            'X-AspNet-Version',
            'X-AspNetMvc-Version',
            'X-Runtime',
        ];
        
        foreach ($headersToRemove as $header) {
            $response->headers->remove($header);
        }
    }
}