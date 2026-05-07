<?php

namespace App\Enums;

enum ServiceRequired: string
{
    case ALL = 'all';
    case VISA_ONLY = 'visa_only';
    case TICKET_ONLY = 'ticket_only';
}