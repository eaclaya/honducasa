<?php

namespace App\Rules;

use Blaspsoft\Blasp\Facades\Blasp;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ContainsNoProfanity implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $isOffensive = Blasp::in('spanish', 'english')
            ->driver('regex')
            ->check((string) $value)
            ->isOffensive();

        if ($isOffensive) {
            $fail(app()->getLocale() === 'es'
                ? 'Tu mensaje contiene lenguaje inapropiado. Por favor, reformúlalo.'
                : 'Your message contains inappropriate language. Please rephrase it.');
        }
    }
}
