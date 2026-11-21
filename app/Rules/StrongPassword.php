<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Password tidak valid.');

            return;
        }

        if (mb_strlen($value) < 8) {
            $fail('Password minimal 8 karakter.');

            return;
        }

        if (strlen($value) > 72) {
            $fail('Password maksimal 72 karakter.');

            return;
        }

        if (! preg_match('/[A-Za-z]/', $value) || ! preg_match('/\d/', $value)) {
            $fail('Password harus mengandung huruf dan angka.');
        }
    }
}
