<?php

namespace App\Enums;

enum RouteType: string
{
    case ONEWAY_INBOUND = 'oneway_inbound';
    case ONEWAY_OUTBOUND = 'oneway_outbound';
    case ROUND = 'round';
    case MULTI_CITY = 'multi_city';
}