<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the double-click protection on the payment modal Save/Update buttons.
 *
 * A rapid second click used to fire a second request, creating a duplicate
 * payment (notably on the Invoice Details page where savePayment() POSTs
 * immediately). Each booking component now exposes a `savingPayment` flag that
 * disables the button and swaps its label to "Saving..." while in flight.
 */
class BookingPaymentButtonGuardTest extends TestCase
{
    private function bookingJs(): string
    {
        $js = file_get_contents(base_path('resources/js/booking.js'));
        $this->assertNotFalse($js);

        return $js;
    }

    private function componentBlock(string $js, string $name, ?string $nextName = null): string
    {
        $start = strpos($js, "Alpine.data('{$name}'");
        $this->assertNotFalse($start, "{$name} component not found.");

        if ($nextName === null) {
            return substr($js, $start);
        }

        $end = strpos($js, "Alpine.data('{$nextName}'", $start);
        $this->assertNotFalse($end, "{$nextName} component not found.");

        return substr($js, $start, $end - $start);
    }

    public function test_all_booking_components_declare_saving_payment_flag(): void
    {
        $js = $this->bookingJs();

        foreach (['createBookingApp', 'editBookingApp', 'showBookingApp'] as $name) {
            $block = $this->componentBlock($js, $name);

            $this->assertStringContainsString(
                'savingPayment: false,',
                $block,
                "{$name} must declare the savingPayment flag."
            );
        }
    }

    public function test_show_booking_save_payment_is_in_flight_guarded(): void
    {
        $block = $this->componentBlock($this->bookingJs(), 'showBookingApp');

        $this->assertStringContainsString(
            'if (this.savingPayment) {',
            $block,
            'showBookingApp.savePayment() must bail out while a save is in flight.'
        );

        $flagAt = strpos($block, 'this.savingPayment = true;');
        $fetchAt = strpos($block, 'fetch(url, {');
        $this->assertNotFalse($flagAt, 'showBookingApp.savePayment() must set savingPayment before submitting.');
        $this->assertNotFalse($fetchAt, 'showBookingApp.savePayment() must POST the payment.');
        $this->assertLessThan($fetchAt, $flagAt, 'savingPayment must be set before the payment request is sent.');

        $this->assertStringContainsString(
            'this.savingPayment = false;',
            $block,
            'showBookingApp.savePayment() must clear savingPayment on failure so the user can retry.'
        );
    }

    public function test_create_and_edit_save_payment_mark_saving_state(): void
    {
        $js = $this->bookingJs();

        foreach ([
            'createBookingApp' => 'editBookingApp',
            'editBookingApp' => 'showBookingApp',
        ] as $name => $nextName) {
            $block = $this->componentBlock($js, $name, $nextName);

            $this->assertStringContainsString(
                'this.savingPayment = true;',
                $block,
                "{$name}.savePayment() must mark savingPayment while saving."
            );
        }
    }

    public function test_payment_modal_buttons_bind_disabled_and_saving_text(): void
    {
        foreach ([
            'bookings/create.blade.php',
            'bookings/edit.blade.php',
            'bookings/show.blade.php',
        ] as $view) {
            $html = file_get_contents(resource_path("views/{$view}"));
            $this->assertNotFalse($html, "{$view} not found.");

            $this->assertStringContainsString(
                ':disabled="savingPayment"',
                $html,
                "{$view} payment button must be disabled while saving."
            );
            $this->assertStringContainsString(
                "savingPayment ? 'Saving...'",
                $html,
                "{$view} payment button must show a saving label while saving."
            );
        }
    }
}
