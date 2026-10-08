<?php

namespace App\Enums;

enum StaffRole: string
{
    case Triage = 'TRIAGE';
    case Doctor = 'DOCTOR';
    case HospitalAdmin = 'HOSPITAL_ADMIN';
    case SuperAdmin = 'SUPER_ADMIN';
}
