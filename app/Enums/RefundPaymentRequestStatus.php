<?php

namespace App\Enums;

enum RefundPaymentRequestStatus: string
{
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case REVERTED = 'reverted';
}
