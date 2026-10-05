<?php

namespace App\Services;

class PhoneNormalizer
{
    public function whatsapp(string $value, string $defaultCountry = 'BO'): ?string
    {
        $value = trim($value);
        if (! preg_match('/^\+?[0-9\s().-]+$/D', $value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);
        if (str_starts_with($value, '+') || str_starts_with($digits, '00') || ($defaultCountry === 'BO' && str_starts_with($digits, '591'))) {
            $digits = str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
        } elseif ($defaultCountry === 'BO') {
            $digits = '591'.$digits;
        } else {
            return null;
        }

        // Bolivia mobile numbers have eight digits and begin with 6 or 7.
        if (str_starts_with($digits, '591')) {
            return preg_match('/^591[67][0-9]{7}$/D', $digits) ? '+'.$digits : null;
        }

        // Explicit international numbers are accepted without guessing a country code.
        return preg_match('/^[1-9][0-9]{7,14}$/D', $digits) ? '+'.$digits : null;
    }
}
