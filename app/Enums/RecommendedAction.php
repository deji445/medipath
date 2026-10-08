<?php

namespace App\Enums;

enum RecommendedAction: string
{
    case RoutineCareOrSelfCare = 'ROUTINE_CARE_OR_SELF_CARE';
    case SeekCareSoon = 'SEEK_CARE_SOON';
    case SeekEmergencyCareNow = 'SEEK_EMERGENCY_CARE_NOW';
}
