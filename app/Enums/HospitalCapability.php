<?php

namespace App\Enums;

enum HospitalCapability: string
{
    case EmergencyCapable = 'ER_CAPABLE';
    case ClinicOnly = 'CLINIC_ONLY';
}
