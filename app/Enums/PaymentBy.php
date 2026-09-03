<?php

namespace App\Enums;

enum PaymentBy: string
{
    case CUSTOMER = 'customer';
    case AIRLINE = 'airline';
    case EMPLOYEE = 'employee';
    case COMPANY = 'company';
}
