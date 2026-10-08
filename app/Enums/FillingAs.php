<?php

namespace App\Enums;

enum FillingAs: string
{
    case Self = 'SELF';
    case Companion = 'COMPANION';
    case Caregiver = 'CAREGIVER';
}
