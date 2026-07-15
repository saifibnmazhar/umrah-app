<?php

namespace App\Enums;

enum CancelledBookingStatus: string
{
    case PROCESSING = 'cancellation processing';
    case CANCELLED = 'cancelled';
}
