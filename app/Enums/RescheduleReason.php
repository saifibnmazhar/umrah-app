<?php

namespace App\Enums;

enum RescheduleReason: string
{
    case RESCHEDULED_BY_CLIENT = 'rescheduled_by_client';
    case RESCHEDULED_BY_BMT = 'rescheduled_by_bmt';
    case NFC_PROBLEM = 'nfc_problem';
    case OTHERS = 'others';
}
