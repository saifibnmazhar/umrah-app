<?php

use App\Exceptions\DatabaseErrorHumanizer;
use App\Http\Middleware\CheckActive;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureTicketRequestAccess;
use App\Http\Middleware\TrustProxies;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        // Use custom TrustProxies that extends monicahq/laravel-cloudflare
        // to automatically trust Cloudflare IP ranges (auto-refreshing).
        $middleware->prepend(TrustProxies::class);

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->alias([
            'role' => CheckRole::class,
            'ticket-request-branch' => EnsureTicketRequestAccess::class,
        ]);

        // CheckActive must be appended to the 'web' group: appending it to 'auth' would create a
        // middleware *group* named auth (shadowing the auth *alias* in MiddlewareNameResolver),
        // silently dropping Illuminate\Auth\Middleware\Authenticate from every auth route.
        $middleware->appendToGroup('web', CheckActive::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('ticket-fares:expire')->daily();
        $schedule->command('cloudflare:reload')->daily();
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

        // A 419 used to reach the user as a raw "CSRF token mismatch" page.
        // Keep the incident diagnosable and turn the failure into something the
        // user can act on: JSON clients still get a real 419 (their error paths
        // key off the status code), browser form posts get bounced back to
        // something they can submit again.
        //
        // Typed on HttpException rather than TokenMismatchException because
        // Handler::prepareException() rewrites the latter into HttpException(419)
        // BEFORE render callbacks run. 419 is only ever produced by CSRF
        // verification in this app (no abort(419) anywhere).
        //
        // The log line is what tells us WHY the mismatch happened:
        //   session_cookie=false             -> the cookie itself is gone (idle past
        //                                      SESSION_LIFETIME); raise the lifetime.
        //   session_cookie=true, user_id=null -> the session was destroyed while
        //                                      the cookie survived; infrastructure.
        //   session_cookie=true, user_id set -> the session is fine and the form
        //                                      carried a stale token (another tab
        //                                      logged out, or a back/forward-cache
        //                                      restore); only the redirect helps.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            $session = $request->hasSession() ? $request->session() : null;
            $sessionCookie = $request->cookies->get(config('session.cookie'));
            $inputToken = $request->input('_token')
                ?: $request->header('X-CSRF-TOKEN')
                ?: $request->header('X-XSRF-TOKEN');
            $sessionToken = $session?->get('_token');
            $sessionKeys = $session ? array_keys($session->all()) : [];

            Log::warning('CSRF token mismatch', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'referer' => $request->headers->get('referer'),
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
                'cf_ray' => $request->header('cf-ray'),
                'session_cookie' => $sessionCookie !== null,
                'session_id' => $session ? substr(hash('sha256', $session->getId()), 0, 12) : null,
                'session_keys' => $sessionKeys,
                'has_auth_key' => (bool) array_filter(
                    $sessionKeys,
                    fn (string $key) => str_starts_with($key, 'login_web_')
                ),
                'input_token' => $inputToken ? substr(hash('sha256', (string) $inputToken), 0, 12) : null,
                'session_token' => $sessionToken ? substr(hash('sha256', (string) $sessionToken), 0, 12) : null,
                'config_cache' => file_exists(base_path('bootstrap/cache/config.php')),
                'route_cache' => (bool) glob(base_path('bootstrap/cache/routes-*.php')),
                'lifetime' => config('session.lifetime'),
                'driver' => config('session.driver'),
                'environment' => app()->environment(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your session has expired. Please log in again.',
                ], 419);
            }

            $message = 'Your session has expired. Please log in again and retry.';
            $input = $request->except(['_token', 'password']);

            // One hop only: flash data survives a single request, so sending a
            // guest through back() -> auth -> /login would drop the message.
            if (auth()->check()) {
                return redirect()->back()->withInput($input)->with('error', $message);
            }

            return redirect()->route('login')->withInput($input)->withErrors(['email' => $message]);
        });
    })->create();
