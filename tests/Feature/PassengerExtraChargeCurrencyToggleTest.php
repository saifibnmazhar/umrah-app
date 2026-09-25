<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassengerExtraChargeCurrencyToggleTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        $branch = Branch::create([
            'name' => 'Main Admin Branch',
            'address' => 'Admin Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $user;
    }

    private function renderPassengerEdit(): string
    {
        $this->actingAs($this->createUser());

        $booking = new Booking;
        $booking->id = 1;
        $booking->package_id = null;
        $booking->setRelation('invoice', null);

        $passenger = new Passenger;
        $passenger->id = 213;
        $passenger->forceFill([
            'first_name' => 'Test',
            'last_name' => 'Pax',
            'extra_charge' => 150,
        ]);
        $passenger->setRelation('booking', $booking);

        return view('passengers.edit', [
            'passenger' => $passenger,
            'ticketFares' => collect([]),
            'packages' => collect([]),
            'rate' => 30,
            'canEditExtraCharge' => true,
        ])->render();
    }

    private function assertCurrencyAwareExtraChargeMarkup(string $html): void
    {
        $this->assertStringContainsString('x-model="passengerData.extra_charge_bdt"', $html);
        $this->assertStringContainsString('Extra Charge (BDT)', $html);
        $this->assertStringContainsString(
            ':readonly="$store.currency.mode === \'BDT\' && $store.currency.rate > 0"',
            $html
        );
        $this->assertStringContainsString(
            'passengerData.extra_charge = ((parseFloat(passengerData.extra_charge_bdt)',
            $html
        );
    }

    public function test_create_booking_page_has_currency_aware_extra_charge_fields(): void
    {
        $response = $this->actingAs($this->createUser())->get('/bookings/create');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertCurrencyAwareExtraChargeMarkup($html);

        $this->assertStringContainsString('$currency(passenger.extra_charge', $html);
        $this->assertStringContainsString("'passengers[' + index + '][extra_charge]'", $html);
    }

    public function test_passenger_edit_page_has_currency_aware_extra_charge_fields(): void
    {
        $this->assertCurrencyAwareExtraChargeMarkup($this->renderPassengerEdit());
    }

    public function test_passenger_edit_page_sends_sar_value_on_submit(): void
    {
        $html = $this->renderPassengerEdit();

        $this->assertStringContainsString('extra_charge: parseFloat(this.passengerData.extra_charge) || 0', $html);
    }

    public function test_passenger_edit_page_hides_extra_charge_field_for_non_admins(): void
    {
        $this->actingAs($this->createUser());

        $booking = new Booking;
        $booking->id = 1;
        $booking->package_id = null;
        $booking->setRelation('invoice', null);

        $passenger = new Passenger;
        $passenger->id = 213;
        $passenger->forceFill(['first_name' => 'Test', 'last_name' => 'Pax']);
        $passenger->setRelation('booking', $booking);

        $html = view('passengers.edit', [
            'passenger' => $passenger,
            'ticketFares' => collect([]),
            'packages' => collect([]),
            'rate' => 30,
            'canEditExtraCharge' => false,
        ])->render();

        $this->assertStringNotContainsString('x-model="passengerData.extra_charge_bdt"', $html);
        $this->assertStringNotContainsString('Extra Charge (BDT)', $html);
    }
}
