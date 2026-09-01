<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ContainsNoContactInformation implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = (string) $value;
        $containsEmail = preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $message) === 1;
        $containsLink = preg_match('/(?:https?:\/\/|www\.|\b[a-z0-9-]+\.(?:com|net|org|hn|io)\b)/i', $message) === 1;
        $containsPhone = preg_match('/(?<!\d)(?:\+?\d[\s().-]*){8,15}(?!\d)/', $message) === 1;

        if ($containsEmail || $containsLink || $containsPhone) {
            $fail(app()->getLocale() === 'es'
                ? 'Por seguridad, no compartas teléfonos, correos ni enlaces externos.'
                : 'For safety, do not share phone numbers, email addresses, or external links.');
        }
    }
}
