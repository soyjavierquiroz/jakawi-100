<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WhatsAppCommunityUrl implements ValidationRule
{
    public static function valid(?string $value): bool
    {
        // A canonical invitation only: no credentials, port, query, fragment or redirect host.
        return is_string($value) && preg_match('~\Ahttps://chat\.whatsapp\.com/[A-Za-z0-9]{22}\z~D', $value) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::valid($value)) {
            $fail('Usa el enlace oficial https://chat.whatsapp.com/ seguido del código de invitación de 22 caracteres, sin parámetros.');
        }
    }
}
