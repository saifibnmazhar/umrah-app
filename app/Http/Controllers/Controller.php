<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersBookingStatus;
use App\Concerns\HandlesBranchAccess;

abstract class Controller
{
    use FiltersBookingStatus;
    use HandlesBranchAccess;
}
