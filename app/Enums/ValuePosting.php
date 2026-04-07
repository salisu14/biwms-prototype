<?php

namespace App\Enums;

/**
 * Defines the rules for how a dimension must be populated
 * during transaction posting.
 */
enum ValuePosting: string
{
    case None = 'none';
    case CodeMandatory = 'code_mandatory';
    case SameCode = 'same_code';
    case NoCode = 'no_code';

    public function label(): string
    {
        return match($this) {
            self::None => 'No Requirement',
            self::CodeMandatory => 'Code Mandatory', // Any value allowed, but must exist
            self::SameCode => 'Same Code',           // Must be this specific value
            self::NoCode => 'No Code',               // Must be empty
        };
    }

    public function requiresValidation(): bool
    {
        return $this !== self::None;
    }
}
