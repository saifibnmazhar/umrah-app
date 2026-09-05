<?php

namespace App\Enums;

enum PassengerType: string
{
    case ADULT = 'adult';
    case CHILD = 'child';
    case INFANT = 'infant';
}
