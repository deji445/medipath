<?php

namespace App\Support;

class PhoneNumberNormalizer
{
    public function normalize(string $phone): string
    {
        $phone = trim($phone);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            return '+233'.substr($digits, 1);
        }

        if (str_starts_with($digits, '233')) {
            return '+'.$digits;
        }

        return '+'.$digits;
    }
}
