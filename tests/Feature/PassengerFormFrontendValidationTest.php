<?php

namespace Tests\Feature;

use Tests\TestCase;

class PassengerFormFrontendValidationTest extends TestCase
{
    public function test_passenger_modal_passport_expiry_is_required_with_future_min(): void
    {
        $html = view('partials.passenger-form-modal')->render();

        $this->assertStringContainsString('Passport Expiry Date', $html);
        $this->assertStringContainsString('x-model="passengerData.passport_expiry"', $html);

        // Expiry input must be HTML5 required.
        $this->assertMatchesRegularExpression(
            '/<input[^>]*x-model="passengerData\.passport_expiry"[^>]*required[^>]*>/',
            $html,
            'Passport expiry input must carry the required attribute.'
        );

        // Must prevent past dates via min attribute (static or Alpine-bound).
        $this->assertMatchesRegularExpression(
            '/<input[^>]*x-model="passengerData\.passport_expiry"[^>]*(:min|min)="[^"]+"[^>]*>/',
            $html,
            'Passport expiry input must carry a min (or :min) attribute for future-date enforcement.'
        );
    }

    public function test_passenger_modal_gender_is_required_when_adult(): void
    {
        $html = view('partials.passenger-form-modal')->render();

        $this->assertStringContainsString('x-model="passengerData.gender"', $html);

        // Gender select must be conditionally required when type is Adult.
        $this->assertStringContainsString(':required="passengerData.passenger_type === \'Adult\'"', $html);
    }

    public function test_create_booking_js_validates_gender_and_expiry(): void
    {
        $js = file_get_contents(base_path('resources/js/booking.js'));
        $this->assertNotFalse($js);

        $createStart = strpos($js, "Alpine.data('createBookingApp'");
        $editStart = strpos($js, "Alpine.data('editBookingApp'");
        $this->assertNotFalse($createStart);
        $this->assertNotFalse($editStart);
        $this->assertGreaterThan($createStart, $editStart);

        $createBlock = substr($js, $createStart, $editStart - $createStart);

        $this->assertStringContainsString(
            'passenger_type',
            $createBlock,
            'createBookingApp block missing.'
        );

        // Adult gender guard must live inside the create flow.
        $this->assertStringContainsString(
            'Please select gender for adult passenger',
            $createBlock,
            'createBookingApp.savePassenger() must guard adult gender.'
        );

        // Expiry required + future-date guard must live inside the create flow.
        $this->assertStringContainsString('passport_expiry', $createBlock);
        $this->assertStringContainsString(
            'Please enter passport expiry date',
            $createBlock,
            'createBookingApp.savePassenger() must require passport expiry.'
        );
        $this->assertStringContainsString(
            'Passport expiry must be a future date',
            $createBlock,
            'createBookingApp.savePassenger() must enforce future expiry date.'
        );
    }
}
