<?php

namespace App\Enums;

enum TicketType: string
{
    case REGULAR = 'regular';
    case OFFER = 'offer';
    case GROUP = 'group';
}
