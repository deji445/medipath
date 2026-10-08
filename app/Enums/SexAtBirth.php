<?php

namespace App\Enums;

enum SexAtBirth: string
{
    case Female = 'FEMALE';
    case Male = 'MALE';
    case Intersex = 'INTERSEX';
    case Unknown = 'UNKNOWN';
    case PreferNotToSay = 'PREFER_NOT_TO_SAY';
}
