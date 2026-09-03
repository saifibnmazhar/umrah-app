<?php

namespace App\Enums;

enum FingerprintStatus: string
{
    case NONE = 'none';
    case PROCESSING = 'processing';
    case APPROVED = 'approved';
    case CANCELLED = 'cancelled';
    case DONE = 'done';
}
