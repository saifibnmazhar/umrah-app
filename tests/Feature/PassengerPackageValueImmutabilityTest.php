<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Passenger;
use App\Rules\FlightDateSlot;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * Test 3 + 10 (plan 17): fare/package updates must have NO impact on stored
 * passengers.package_value / bookings.total_value / invoices.total_amount —
 * regardless of which booking/passenger mutation follows. Also covers the
 * deletion of the unused recalculate endpoint (D7).
 */
class PassengerPackageValueImmutabilityTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private Package $package;

    private Booking $booking;

    private Passenger $passenger;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->pvBranch();
        $this->user = $this->pvUser($branch);
        $district = $this->pvDistrict();
        $fpCharge = $this->pvFpCharge($district, $this->user);
        $visaPrice = $this->pvVisaPrice($this->user, 2000.00);

        $fare = $this->pvTicketFare($this->user);
        $this->package = $this->pvPackage($this->user, $visaPrice, [
            'ticket_fare_id' => $fare->id,
            'regular_price' => 30000.00, // selling 28000 + visa 2000
            'offer_price' => 29000.00,
        ]);

        $this->booking = $this->pvBooking($this->package, $this->user, $fpCharge, $district, [
            'fingerprint_location' => 'home', // fingerprint charge applies
            'total_value' => 31550.00, // 31500 passengers + 50 fp charge
        ]);
        $this->passenger = $this->pvPassenger($this->booking, [
            'ticket_fare_id' => $fare->id,
            'package_value' => 31500.00, // 28000 fare + 2000 visa + 1500 service
        ]);
        $this->invoice = app(InvoiceService::class)->createForBooking($this->booking);
        $this->invoice->updateQuietly(['total_amount' => 31550.00, 'paid_amount' => 0]);
    }

    private function assertFinancialsFrozen(string $context): void
    {
        $this->assertEqualsWithDelta(31500.00, (float) $this->passenger->fresh()->package_value, 0.001, "package_value ({$context})");
        $this->assertEqualsWithDelta(31550.00, (float) $this->booking->fresh()->total_value, 0.001, "total_value ({$context})"); // +50 fp charge
        $this->assertEqualsWithDelta(31550.00, (float) $this->invoice->fresh()->total_amount, 0.001, "invoice total ({$context})");
    }

    public function test_fare_update_then_booking_edit_keeps_stored_values(): void
    {
        $this->actingAs($this->user);

        $this->put(route('bookings.update', $this->booking->id), ['remarks' => 'edited'])
            ->assertRedirect();

        $this->assertFinancialsFrozen('booking edit after fare era');
    }

    public function test_fare_update_then_passenger_edit_keeps_stored_values(): void
    {
        $this->actingAs($this->user);

        // Live fare moved far away from the stored value.
        $fare = $this->package->ticketFare;
        $fare->update(['selling_fare' => 55000.00]);

        $this->put(route('passengers.update', $this->passenger->id), [
            'first_name' => 'Renamed',
            'last_name' => $this->passenger->last_name,
            'passport_no' => $this->passenger->passport_no,
            'date_of_birth' => '1990-01-01',
            'stay_duration' => 14,
            'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
            'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
        ])->assertRedirect();

        $this->assertSame('Renamed', $this->passenger->fresh()->first_name);
        $this->assertFinancialsFrozen('passenger edit after fare update');
    }

    public function test_fare_update_then_fingerprint_location_change_keeps_stored_values(): void
    {
        $this->actingAs($this->user);

        $this->package->ticketFare->update(['selling_fare' => 55000.00]);

        $this->patchJson(route('bookings.fingerprint-location.update', $this->booking->id), [
            'fingerprint_location' => 'office',
        ])->assertOk();

        // Office location => fingerprint charge 0; package_value untouched.
        $this->assertEqualsWithDelta(31500.00, (float) $this->passenger->fresh()->package_value, 0.001);
        $this->assertEqualsWithDelta(31500.00, (float) $this->booking->fresh()->total_value, 0.001);
        $this->assertEqualsWithDelta(31500.00, (float) $this->invoice->fresh()->total_amount, 0.001);
    }

    public function test_package_service_charge_update_then_booking_edit_keeps_stored_values(): void
    {
        $this->actingAs($this->user);

        $this->package->update(['service_charge' => 9999.00]);

        $this->put(route('bookings.update', $this->booking->id), ['remarks' => 'edited'])
            ->assertRedirect();

        $this->assertFinancialsFrozen('service_charge bump');
    }

    public function test_package_visa_price_update_then_booking_edit_keeps_stored_values(): void
    {
        $this->actingAs($this->user);

        $newPrice = $this->pvVisaPrice($this->user, 7000.00);
        $this->package->update(['visa_selling_price_id' => $newPrice->id]);

        $this->put(route('bookings.update', $this->booking->id), ['remarks' => 'edited'])
            ->assertRedirect();

        $this->assertFinancialsFrozen('visa price bump');
    }

    public function test_package_regular_price_update_then_booking_edit_keeps_stored_values(): void
    {
        $this->actingAs($this->user);

        $this->package->update(['regular_price' => 99000.00]);

        $this->put(route('bookings.update', $this->booking->id), ['remarks' => 'edited'])
            ->assertRedirect();

        $this->assertFinancialsFrozen('regular_price bump');
    }

    public function test_recalculate_endpoint_is_gone(): void
    {
        $this->actingAs($this->user);

        // Raw path — route() would throw for the deleted route name (D7).
        $this->patch("/bookings/{$this->booking->id}/passengers/{$this->passenger->id}/recalculate")
            ->assertNotFound();
    }
}
