<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the double-submit protection on the standalone payment forms.
 *
 * Both forms are plain full-page POSTs, so a rapid second click used to be
 * able to fire a duplicate request before the browser navigated. The Alpine
 * `submitting` flag now disables the button and swaps its label while the
 * request is in flight.
 */
class PaymentFormSubmitGuardTest extends TestCase
{
    public function test_create_payment_form_guards_against_double_submit(): void
    {
        $html = file_get_contents(resource_path('views/payments/create.blade.php'));
        $this->assertNotFalse($html);

        $this->assertStringContainsString('submitting: false', $html);
        $this->assertStringContainsString('@submit="submitting = true"', $html);
        $this->assertStringContainsString(':disabled="submitting"', $html);
        $this->assertStringContainsString("submitting ? 'Creating...' : 'Create Payment'", $html);
    }

    public function test_edit_payment_form_guards_against_double_submit(): void
    {
        $html = file_get_contents(resource_path('views/payments/edit.blade.php'));
        $this->assertNotFalse($html);

        $this->assertStringContainsString('submitting: false', $html);
        $this->assertStringContainsString('@submit="submitting = true"', $html);
        $this->assertStringContainsString(':disabled="submitting"', $html);
        $this->assertStringContainsString("submitting ? 'Updating...' : 'Update Payment'", $html);
    }
}
