<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Draft = 'DRAFT';
    case Assessing = 'ASSESSING';
    case AssessmentComplete = 'ASSESSMENT_COMPLETE';
    case Submitted = 'SUBMITTED';
    case Cancelled = 'CANCELLED';
}
