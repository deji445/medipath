<?php

namespace App\Enums;

enum AssessmentReasonCode: string
{
    case NoRedFlagIdentified = 'NO_RED_FLAG_IDENTIFIED';
    case SymptomsWorsening = 'SYMPTOMS_WORSENING';
    case SevereReportedIntensity = 'SEVERE_REPORTED_INTENSITY';
    case DailyActivityLimited = 'DAILY_ACTIVITY_LIMITED';
    case DailyActivityPrevented = 'DAILY_ACTIVITY_PREVENTED';
    case AssociatedSymptomsReported = 'ASSOCIATED_SYMPTOMS_REPORTED';
    case PersistentSymptoms = 'PERSISTENT_SYMPTOMS';
    case AbnormalVitalSignsReported = 'ABNORMAL_VITAL_SIGNS_REPORTED';
    case InconclusiveAfterQuestionLimit = 'INCONCLUSIVE_AFTER_QUESTION_LIMIT';
}
