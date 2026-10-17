<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the double-click protection on the discount modal Apply buttons.
 *
 * A rapid second click used to fire a second request (notably on the Bookings
 * list and Invoice Details pages, where applyDiscount() PATCHes the booking).
 * In-flight requests now disable the Apply button and swap its label to
 * "Applying..." until the response settles.
 */
class BookingDiscountButtonGuardTest extends TestCase
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

    public function test_create_and_edit_declare_applying_discount_flag(): void
    {
        $js = $this->bookingJs();

        foreach ([
            'createBookingApp' => 'editBookingApp',
            'editBookingApp' => 'showBookingApp',
        ] as $name => $nextName) {
            $block = $this->componentBlock($js, $name, $nextName);

            $this->assertStringContainsString(
                'applyingDiscount: false,',
                $block,
                "{$name} must declare the applyingDiscount flag."
            );
            $this->assertStringContainsString(
                'applyDiscountModal() {',
                $block,
                "{$name} must expose applyDiscountModal()."
            );
            $this->assertStringContainsString(
                'this.applyingDiscount = true;',
                $block,
                "{$name}.applyDiscountModal() must mark applyingDiscount."
            );
        }
    }

    public function test_close_discount_modal_clears_applying_flag(): void
    {
        $js = $this->bookingJs();

        $this->assertMatchesRegularExpression(
            '/closeDiscountModal\(\)\s*\{\s*this\.discountModalVisible = false;\s*this\.applyingDiscount = false;\s*\}/',
            $js,
            'closeDiscountModal() must clear applyingDiscount.'
        );
    }

    public function test_create_and_edit_apply_buttons_use_applying_bindings(): void
    {
        foreach ([
            'bookings/create.blade.php',
            'bookings/edit.blade.php',
        ] as $view) {
            $html = file_get_contents(resource_path("views/{$view}"));
            $this->assertNotFalse($html, "{$view} not found.");

            $this->assertStringContainsString(
                '@click="applyDiscountModal()"',
                $html,
                "{$view} Apply button must call applyDiscountModal()."
            );
            $this->assertStringContainsString(
                ':disabled="applyingDiscount"',
                $html,
                "{$view} Apply button must be disabled while applying."
            );
            $this->assertStringContainsString(
                "applyingDiscount ? 'Applying...'",
                $html,
                "{$view} Apply button must show an applying label."
            );
        }
    }

    public function test_invoice_details_apply_is_in_flight_guarded(): void
    {
        $html = file_get_contents(resource_path('views/bookings/show.blade.php'));
        $this->assertNotFalse($html);

        $this->assertStringContainsString('id="applyDiscountBtn"', $html);
        $this->assertStringContainsString('let applyDiscountInFlight = false;', $html);
        $this->assertStringContainsString('if (applyDiscountInFlight) {', $html);
        $this->assertStringContainsString("applyBtn.textContent = 'Applying...';", $html);
        $this->assertStringContainsString('applyDiscountInFlight = false;', $html);
        $this->assertStringContainsString('.finally(', $html);
    }

    public function test_bookings_index_apply_is_in_flight_guarded(): void
    {
        $html = file_get_contents(resource_path('views/bookings/index.blade.php'));
        $this->assertNotFalse($html);

        $this->assertStringContainsString('id="applyIndexDiscountBtn"', $html);
        $this->assertStringContainsString('let indexDiscountInFlight = false;', $html);
        $this->assertStringContainsString('if (indexDiscountInFlight) {', $html);
        $this->assertStringContainsString("applyBtn.textContent = 'Applying...';", $html);
        $this->assertStringContainsString('indexDiscountInFlight = false;', $html);
        $this->assertStringContainsString('.finally(', $html);
    }
}
