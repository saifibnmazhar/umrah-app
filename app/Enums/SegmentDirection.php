<?php

namespace App\Enums;

enum SegmentDirection: string
{
    case INBOUND = 'inbound';
    case OUTBOUND = 'outbound';
}