<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CsrfMismatchRenderTest extends TestCase
{
    /**
     * Build a browser-style form POST. The render pipeline converts the
     * TokenMismatchException into HttpException(419) before callbacks run, so
     * constructing the original exception exercises the real code path.
     */
    private function formPost(string $uri, bool $json = false): Request
    {
        $request = Request::create($uri, 'POST', [
            '_token' => 'stale-token',
            'email' => 'someone@example.com',
        ]);
        $request->headers->set('referer', $uri);

        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }

        return $request;
    }

    private function render(Request $request): Response
    {
        // The URL generator only learns about the current request once a route
        // has been handled - the same warm-up AuthMiddlewareTest relies on.
        $this->get('/login');

        $request->setLaravelSession(app('session.store'));
        app('url')->setRequest($request);
        app('redirect')->setSession(app('session.store'));

        return app(ExceptionHandler::class)->render(
            $request,
            new TokenMismatchException('CSRF token mismatch.')
        );
    }

    public function test_a_guest_form_post_redirects_to_login_instead_of_failing_with_a_419(): void
    {
        Log::spy();

        $response = $this->render($this->formPost('http://localhost/login'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('login'), $response->getTargetUrl());
    }

    public function test_an_authenticated_form_post_returns_to_where_the_user_came_from(): void
    {
        Log::spy();
        $this->actingAs(User::factory()->create());

        $response = $this->render($this->formPost('http://localhost/bookings?tab=passenger'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('http://localhost/bookings?tab=passenger', $response->getTargetUrl());
    }

    public function test_the_login_page_receives_the_message_as_a_field_error(): void
    {
        Log::spy();

        $response = $this->render($this->formPost('http://localhost/login'));
        $errors = session('errors');

        $this->assertNotNull($errors, 'login.blade.php only renders $errors, not session("error")');
        $this->assertStringContainsString('session has expired', implode(' ', $errors->all()));
    }

    public function test_the_typed_email_is_not_lost(): void
    {
        Log::spy();

        $this->render($this->formPost('http://localhost/login'));

        $this->assertSame('someone@example.com', session()->get('_old_input.email'));
    }

    public function test_json_clients_still_receive_a_real_419(): void
    {
        Log::spy();

        $response = $this->render($this->formPost('http://localhost/passengers/1/status', json: true));

        $this->assertSame(419, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['message']);
    }

    public function test_the_mismatch_is_logged_with_the_fields_needed_to_diagnose_it(): void
    {
        $log = Log::spy();

        $this->render($this->formPost('http://localhost/bookings?tab=passenger'));

        $log->shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context) => $message === 'CSRF token mismatch'
                && array_key_exists('session_cookie', $context)
                && array_key_exists('user_id', $context)
                && array_key_exists('has_auth_key', $context)
                && array_key_exists('config_cache', $context)
                && array_key_exists('route_cache', $context)
                && array_key_exists('lifetime', $context)
        )->once();
    }
}
