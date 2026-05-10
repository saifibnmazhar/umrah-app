<?php

namespace App\Enums;

enum RouteDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}