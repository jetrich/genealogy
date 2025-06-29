<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Enhanced security validation rule for genealogy application inputs.
 */
class SecureInput implements ValidationRule
{
    private array $suspiciousPatterns = [
        // XSS patterns
        '<script', '</script', 'javascript:', 'onclick=', 'onerror=', 'onload=',
        'vbscript:', 'data:text/html', 'data:application/javascript',
        
        // SQL injection patterns
        'union select', 'drop table', 'delete from', 'insert into', 'update set',
        'or 1=1', 'and 1=1', '--', '/*', '*/', 'xp_', 'sp_',
        
        // File inclusion patterns
        '../', './', '..\\', '.\\', 'file://', 'http://', 'ftp://',
        
        // Command injection patterns
        '$(', '`', ';rm ', ';cat ', ';ls ', '|cat ', '|ls ', '&&',
        
        // LDAP injection patterns
        '(cn=', '(uid=', '(mail=', '*))', '(|',
        
        // Path traversal patterns
        '%2e%2e%2f', '%2e%2e%5c', '..%2f', '..%5c',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }
        
        $lowercaseValue = strtolower($value);
        
        foreach ($this->suspiciousPatterns as $pattern) {
            if (str_contains($lowercaseValue, strtolower($pattern))) {
                $fail("The {$attribute} field contains potentially malicious content.");
                return;
            }
        }
        
        // Check for excessive HTML entities (potential encoding attacks)
        if (substr_count($value, '&') > 10) {
            $fail("The {$attribute} field contains excessive HTML entities.");
            return;
        }
        
        // Check for excessive special characters
        $specialCharCount = preg_match_all('/[<>"\'\&\(\)\[\]\{\}]/', $value);
        if ($specialCharCount > strlen($value) * 0.1) {
            $fail("The {$attribute} field contains excessive special characters.");
            return;
        }
    }
}