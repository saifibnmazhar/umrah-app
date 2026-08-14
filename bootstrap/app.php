<?php

use App\Exceptions\DatabaseErrorHumanizer;
use App\Http\Middleware\CheckActive;
use App\Http\Middleware\CheckRole;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/booking-cancellation.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->alias([
            'role' => CheckRole::class,
        ]);

        $middleware->appendToGroup('auth', CheckActive::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('ticket-fares:expire')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $e, Request $request) {
            $message = DatabaseErrorHumanizer::humanize($e);

            Log::error('Database error: '.$e->getMessage(), [
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'url' => $request->fullUrl(),
                'user_id' => auth()->id(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 500);
            }

            return redirect()->back()->with('error', $message)->withInput();
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            $message = 'The requested record was not found.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 404);
            }

            return redirect()->back()->with('error', $message);
        });
    })->create();
