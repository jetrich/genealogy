<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Genealogy-specific security validation for names, dates, and family data.
 */
class GenealogySecureInput implements ValidationRule
{
    private array $genealogyPatterns = [
        // Malicious GEDCOM patterns
        'GEDCOM', '0 HEAD', '1 SOUR', '2 VERS', '0 @', '1 @',
        
        // File upload attacks
        '<?php', '<?=', '<script', '<iframe', '<object', '<embed',
        
        // Data extraction patterns  
        'SELECT * FROM', 'UNION ALL', 'INFORMATION_SCHEMA',
        
        // Common genealogy-specific attacks
        '../gedcom', '/gedcom/', 'gedcom.zip', '.ged"', '.GED"',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            return;
        }
        
        $uppercaseValue = strtoupper($value);
        
        foreach ($this->genealogyPatterns as $pattern) {
            if (str_contains($uppercaseValue, strtoupper($pattern))) {
                $fail("The {$attribute} field contains content not suitable for genealogy data.");
                return;
            }
        }
        
        // Validate genealogy name patterns (should be reasonable names)
        if (in_array($attribute, ['firstname', 'surname', 'name'])) {
            if (preg_match('/[0-9]{3,}/', $value)) {
                $fail("The {$attribute} field should not contain long number sequences.");
                return;
            }
            
            if (strlen($value) > 100) {
                $fail("The {$attribute} field is unusually long for a name.");
                return;
            }
        }
        
        // Validate date fields
        if (str_contains($attribute, 'date') || str_contains($attribute, 'birth') || str_contains($attribute, 'death')) {
            if (preg_match('/[<>\'\"&]/', $value)) {
                $fail("The {$attribute} field contains invalid characters for a date.");
                return;
            }
        }
    }
}