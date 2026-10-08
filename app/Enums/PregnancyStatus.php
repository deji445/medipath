<?php

namespace App\Enums;

enum PregnancyStatus: string
{
    case Pregnant = 'PREGNANT';
    case NotPregnant = 'NOT_PREGNANT';
    case Unknown = 'UNKNOWN';
    case NotApplicable = 'NOT_APPLICABLE';
}
