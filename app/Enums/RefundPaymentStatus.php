<?php

namespace App\Enums;

enum RefundPaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
}
