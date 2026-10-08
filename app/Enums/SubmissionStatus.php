<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Received = 'RECEIVED';
    case Acknowledged = 'ACKNOWLEDGED';
    case InTriage = 'IN_TRIAGE';
    case Closed = 'CLOSED';
}
