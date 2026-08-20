<?php

namespace App\Http\Controllers;

use App\Concerns\HandlesBranchAccess;

abstract class Controller
{
    use HandlesBranchAccess;
}
