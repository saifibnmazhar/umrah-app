<?php

namespace App\Http\Controllers;

use App\Support\DiagnosticLogger;
use Illuminate\Http\Request;

class DiagnosticController extends Controller
{
    public function recordUploadFailure(Request $request)
    {
        DiagnosticLogger::client(array_merge(
            ['user_id' => $request->user()?->id],
            $request->input() ?? [],
        ));

        return response()->json(['success' => true]);
    }
}
