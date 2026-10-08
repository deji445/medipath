<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

final class PatientAge
{
    public static function fromDateOfBirth(mixed $dateOfBirth): ?int
    {
        if (! is_string($dateOfBirth) || $dateOfBirth === '') {
            return null;
        }

        try {
            $birthDate = CarbonImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
        } catch (Throwable) {
            return null;
        }

        if ($birthDate === null || $birthDate->isFuture()) {
            return null;
        }

        return (int) $birthDate->diffInYears(CarbonImmutable::today());
    }
}
