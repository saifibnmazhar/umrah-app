<?php

namespace App\Enums;

enum DiscountType: string
{
    case FIXED_AMOUNT = 'fixed_amount';
    case PERCENTAGE = 'percentage';
}
