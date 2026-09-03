<?php

namespace App\Enums;

enum TicketStatus: string
{
    case PENDING = 'pending';
    case ISSUED = 'issued';
    case RE_ISSUED = 're-issued';
    case REFUNDED = 'refunded';
    case AWAITING_GROUP = 'awaiting-group';
}
