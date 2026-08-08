<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiagnosticLogger
{
    public static function arrival(Request $request, string $route): void
    {
        $files = $request->allFiles();
        $fileCount = 0;
        $totalBytes = 0;
        array_walk_recursive($files, function ($file) use (&$fileCount, &$totalBytes) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $fileCount++;
                $totalBytes += $file->getSize();
            }
        });

        Log::channel('diag')->info('ARRIVAL', [
            'route' => $route,
            'diag_id' => $request->header('X-Diag-Id'),
            'user_id' => optional($request->user())->id,
            'content_length' => (int) ($_SERVER['CONTENT_LENGTH'] ?? 0),
            'files' => $fileCount,
            'file_bytes' => $totalBytes,
            'ajax' => $request->ajax() || $request->wantsJson(),
            'url' => $request->fullUrl(),
            'ts' => now()->format('Y-m-d H:i:s.v'),
        ]);
    }

    public static function client(array $payload): void
    {
        Log::channel('diag')->info('CLIENT', $payload + [
            'ts' => now()->format('Y-m-d H:i:s.v'),
        ]);
    }
}
