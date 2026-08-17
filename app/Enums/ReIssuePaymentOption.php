<?php

namespace App\Enums;

enum ReIssuePaymentOption: string
{
    case CUSTOMER_PAYMENT = 'customer_payment';
    case REFUND_ADJUSTMENT = 'refund_adjustment';
}
