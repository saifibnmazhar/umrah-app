<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassengerFlightDateEditModalTest extends TestCase
{
    use RefreshDatabase;

    private function renderEdit(): string
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::create(['name' => 'Super Admin']));
        $this->actingAs($user);

        $booking = new Booking;
        $booking->id = 1;
        $booking->package_id = null;
        $booking->setRelation('invoice', null);

        $passenger = new Passenger;
        $passenger->id = 213;
        $passenger->forceFill([
            'first_name' => 'Test',
            'last_name' => 'Pax',
            'flight_date_from' => '2026-09-01',
            'flight_date_to' => '2026-09-15',
        ]);
        $passenger->setRelation('booking', $booking);

        return view('passengers.edit', [
            'passenger' => $passenger,
            'ticketFares' => collect([]),
            'packages' => collect([]),
            'rate' => 0,
        ])->render();
    }

    public function test_flight_date_range_is_readonly_with_edit_button(): void
    {
        $html = $this->renderEdit();

        $this->assertStringContainsString('id="passengerFlightDateRange"', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('openFlightDateModal()', $html);
    }

    public function test_flight_date_modal_has_from_and_to_ddmmmyy_fields(): void
    {
        $html = $this->renderEdit();

        $this->assertStringContainsString('flightDateModalVisible', $html);
        $this->assertStringContainsString('flightDateModalFrom', $html);
        $this->assertStringContainsString('flightDateModalTo', $html);
        $this->assertStringContainsString('DD-MMM-YY', $html);
    }

    public function test_edit_button_hidden_for_non_admin_users(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $booking = new Booking;
        $booking->id = 1;
        $booking->package_id = null;
        $booking->setRelation('invoice', null);

        $passenger = new Passenger;
        $passenger->id = 213;
        $passenger->forceFill([
            'first_name' => 'Test',
            'last_name' => 'Pax',
            'flight_date_from' => '2026-09-01',
            'flight_date_to' => '2026-09-15',
        ]);
        $passenger->setRelation('booking', $booking);

        $html = view('passengers.edit', [
            'passenger' => $passenger,
            'ticketFares' => collect([]),
            'packages' => collect([]),
            'rate' => 0,
        ])->render();

        $this->assertStringContainsString('id="passengerFlightDateRange"', $html);
        $this->assertStringNotContainsString('<button type="button" @click="openFlightDateModal()"', $html);
    }
}
