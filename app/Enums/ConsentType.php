<?php

namespace App\Enums;

enum ConsentType: string
{
    case AiDisclaimer = 'AI_DISCLAIMER';
    case PrivacyNotice = 'PRIVACY_NOTICE';
    case LocationAccess = 'LOCATION_ACCESS';
    case FitbitAccess = 'FITBIT_ACCESS';
    case HospitalShare = 'HOSPITAL_SHARE';
}
