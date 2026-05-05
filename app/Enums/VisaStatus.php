<?php

namespace App\Enums;

enum VisaStatus: string
{
    case PENDING = 'pending';
    case SUBMITTED = 'submitted';
    case ISSUED = 'issued';
}