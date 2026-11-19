<?php

namespace App\Support;

class PhoneNumberNormalizer
{
    public function normalize(string $phone): string
    {
        $phone = trim($phone);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $phone = '+'.$digits;
        }

        if (preg_match('/^2330([0-9]{9})$/', $digits, $matches)) {
            return '+233'.$matches[1];
        }

        if (! str_starts_with($phone, '+') && preg_match('/^[25][0-9]{8}$/', $digits)) {
            return '+233'.$digits;
        }

        if (! str_starts_with($phone, '+') && str_starts_with($digits, '0')) {
            return '+233'.substr($digits, 1);
        }

        return '+'.$digits;
    }
}
