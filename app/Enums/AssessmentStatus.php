<?php

namespace App\Enums;

enum AssessmentStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Complete = 'COMPLETE';
    case Failed = 'FAILED';
}
