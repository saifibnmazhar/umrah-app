<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\BookingUpdateLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HistoricAnchorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Package $package;

    private Booking $booking;

    private Branch $branch;

    private District $district;

    private Customer $customer;

    private VisaSellingPrice $price;

    private FingerprintCharge $fpCharge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);

        $this->district = District::create(['name' => 'D', 'division' => 'Div']);
        $this->customer = Customer::create([
            'name' => 'Cust', 'passport_no' => 'T1', 'mobile_no' => '0501',
            'iqama_type' => 'none', 'address' => 'A',
        ]);
        FlightDateGap::getOrCreate();

        $this->price = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $this->fpCharge = FingerprintCharge::create([
            'district_id' => $this->district->id, 'user_id' => $this->user->id, 'fingerprint_charge' => 50.00,
        ]);

        $this->package = Package::withoutEvents(fn () => Package::create([
            'package_name' => 'Pkg A',
            'visa_selling_price_id' => $this->price->id,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]));

        $this->booking = $this->makeBooking();
        $this->setCreatedAt($this->booking, '2026-07-20 10:00:00');
    }

    private function makeBooking(): Booking
    {
        return Booking::create([
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'fingerprint_branch_id' => $this->branch->id,
            'district_id' => $this->district->id,
            'package_id' => $this->package->id,
            'package_name' => $this->package->package_name,
            'fingerprint_charge_id' => $this->fpCharge->id,
            'booking_branch_id' => $this->branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000.00,
            'remarks' => '',
            'is_cancelled' => false,
        ]);
    }

    private function setCreatedAt($model, string $at): void
    {
        $model->created_at = Carbon::parse($at);
        $model->saveQuietly();
    }

    private function createPassenger(string $createdAt): Passenger
    {
        $passenger = Passenger::create([
            'booking_id' => $this->booking->id,
            'first_name' => 'Pax'.substr(uniqid(), -4),
            'last_name' => 'Test',
            'passport_no' => 'PP'.substr(uniqid(), -8),
            'mobile_no' => '0500000000',
            'date_of_birth' => '1990-01-01',
            'passenger_type' => 'adult',
            'passport_expiry' => '2030-12-31',
            'stay_duration' => 14,
            'service_required' => 'all',
            'flight_date_from' => now()->addDays(5)->toDateString(),
            'flight_date_to' => now()->addDays(15)->toDateString(),
            'ticket_status' => 'pending',
            'address' => 'Addr',
            'package_value' => 0,
        ]);
        $this->setCreatedAt($passenger, $createdAt);

        return $passenger;
    }

    private function logSwap(string $at, int $fromPackageId, int $toPackageId, ?Booking $booking = null): void
    {
        $log = new BookingUpdateLog([
            'booking_id' => ($booking ?? $this->booking)->id,
            'user_id' => $this->user->id,
            'action' => 'updated',
            'old_values' => ['package_id' => $fromPackageId],
            'new_values' => ['package_id' => $toPackageId],
        ]);
        $log->created_at = Carbon::parse($at);
        $log->save();
    }

    public function test_never_swapped_anchor_is_created_at(): void
    {
        $passenger = $this->createPassenger('2026-07-20 10:00:00');

        $this->assertTrue(
            Carbon::parse('2026-07-20 10:00:00')->equalTo($passenger->historicAnchor())
        );
    }

    public function test_swap_after_creation_anchors_at_swap_time(): void
    {
        $passenger = $this->createPassenger('2026-07-20 10:00:00');
        $this->logSwap('2026-08-01 15:30:00', $this->package->id, $this->package->id + 1);

        $this->assertTrue(
            Carbon::parse('2026-08-01 15:30:00')->equalTo($passenger->historicAnchor())
        );
    }

    public function test_latest_of_multiple_swaps_wins(): void
    {
        $passenger = $this->createPassenger('2026-07-20 10:00:00');
        $this->logSwap('2026-08-01 15:30:00', $this->package->id, $this->package->id + 1);
        $this->logSwap('2026-08-10 09:00:00', $this->package->id + 1, $this->package->id + 2);
        $this->logSwap('2026-08-05 12:00:00', $this->package->id + 2, $this->package->id); // out of insertion order

        $this->assertTrue(
            Carbon::parse('2026-08-10 09:00:00')->equalTo($passenger->historicAnchor())
        );
    }

    public function test_passenger_added_after_swap_anchors_at_creation(): void
    {
        $this->logSwap('2026-08-01 15:30:00', $this->package->id, $this->package->id + 1);
        $passenger = $this->createPassenger('2026-08-05 12:00:00'); // added after the swap

        $this->assertTrue(
            Carbon::parse('2026-08-05 12:00:00')->equalTo($passenger->historicAnchor())
        );
    }

    public function test_created_action_log_is_not_a_swap(): void
    {
        $passenger = $this->createPassenger('2026-07-20 10:00:00');

        // The booking's `created` log row carries package_id in new_values and
        // must never masquerade as a swap.
        $log = new BookingUpdateLog([
            'booking_id' => $this->booking->id,
            'user_id' => $this->user->id,
            'action' => 'created',
            'old_values' => null,
            'new_values' => ['package_id' => $this->package->id, 'total_value' => 40000.00],
        ]);
        $log->created_at = Carbon::parse('2026-07-20 10:00:00');
        $log->save();

        $this->assertTrue(
            Carbon::parse('2026-07-20 10:00:00')->equalTo($passenger->historicAnchor())
        );
    }

    public function test_logs_of_other_bookings_are_ignored(): void
    {
        $passenger = $this->createPassenger('2026-07-20 10:00:00');

        $otherBooking = $this->makeBooking();
        $this->logSwap('2026-08-02 00:00:00', 1, 2, $otherBooking);

        $this->assertTrue(
            Carbon::parse('2026-07-20 10:00:00')->equalTo($passenger->historicAnchor())
        );
    }
}
