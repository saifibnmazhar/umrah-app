<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPackageChangeVisaSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $deps = [];

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $this->user = User::create([
            'name' => 'Super Admin',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

        $district = District::create(['name' => 'D', 'division' => 'Div']);
        $customer = Customer::create([
            'name' => 'Cust', 'passport_no' => 'T1', 'mobile_no' => '0501',
            'iqama_type' => 'none', 'address' => 'A',
        ]);
        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id, 'user_id' => $this->user->id, 'fingerprint_charge' => 50.00,
        ]);
        FlightDateGap::getOrCreate();

        $priceA = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $priceB = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2500.00]);
        $priceC = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 3000.00]);

        $packageA = $this->makePackage('Pkg A', $priceA->id);
        $packageB = $this->makePackage('Pkg B', $priceB->id);

        $this->deps = compact('branch', 'district', 'customer', 'fpCharge', 'priceA', 'priceB', 'priceC', 'packageA', 'packageB');
    }

    private function makePackage(string $name, int $visaPriceId): Package
    {
        return Package::create([
            'package_name' => $name,
            'visa_selling_price_id' => $visaPriceId,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
    }

    private function createBooking(Package $package): Booking
    {
        return Booking::create([
            'user_id' => $this->user->id,
            'customer_id' => $this->deps['customer']->id,
            'fingerprint_branch_id' => $this->deps['branch']->id,
            'district_id' => $this->deps['district']->id,
            'package_id' => $package->id,
            'package_name' => $package->package_name,
            'fingerprint_charge_id' => $this->deps['fpCharge']->id,
            'booking_branch_id' => $this->deps['branch']->id,
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

    private function createPassenger(Booking $booking): Passenger
    {
        return Passenger::create([
            'booking_id' => $booking->id,
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
            'package_value' => 40000.00,
        ]);
    }

    private function createVisaSubmission(Passenger $passenger, VisaSellingPrice $price, array $overrides = []): VisaSubmission
    {
        return VisaSubmission::create(array_merge([
            'passenger_id' => $passenger->id,
            'visa_selling_price_id' => $price->id,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_changing_booking_package_syncs_visa_submission_price_id(): void
    {
        $booking = $this->createBooking($this->deps['packageA']);
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceA']);

        $this->actingAs($this->user)
            ->put(route('bookings.update', $booking->id), [
                'package_id' => $this->deps['packageB']->id,
            ])
            ->assertRedirect();

        $this->assertEquals(
            $this->deps['priceB']->id,
            $visa->fresh()->visa_selling_price_id
        );
    }

    public function test_changing_booking_package_syncs_issued_submission(): void
    {
        $booking = $this->createBooking($this->deps['packageA']);
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceA'], [
            'net_visa_cost' => 1000.00,
            'agent_commission' => 100.00,
            'additional_cost' => 50.00,
            'final_cost' => 1150.00,
            'status' => 'issued',
        ]);

        $this->actingAs($this->user)
            ->put(route('bookings.update', $booking->id), [
                'package_id' => $this->deps['packageB']->id,
            ])
            ->assertRedirect();

        $this->assertEquals(
            $this->deps['priceB']->id,
            $visa->fresh()->visa_selling_price_id
        );
    }

    public function test_booking_update_without_package_change_keeps_visa_price_id(): void
    {
        $booking = $this->createBooking($this->deps['packageA']);
        $passenger = $this->createPassenger($booking);
        // Stale price id (price C) — must NOT be touched when package_id is unchanged.
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceC']);

        $this->actingAs($this->user)
            ->put(route('bookings.update', $booking->id), [
                'remarks' => 'updated remarks',
            ])
            ->assertRedirect();

        $this->assertEquals(
            $this->deps['priceC']->id,
            $visa->fresh()->visa_selling_price_id
        );
    }

    public function test_package_swap_recalculates_visa_profit(): void
    {
        $booking = $this->createBooking($this->deps['packageA']);
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger, $this->deps['priceA'], [
            'net_visa_cost' => 1000.00,
            'agent_commission' => 100.00,
            'additional_cost' => 50.00,
            'final_cost' => 1150.00,
            'status' => 'issued',
        ]);

        // 2000 - 1150 = 850 (price A)
        $this->assertEquals(850.0, (float) $passenger->fresh()->visa_profit);

        $this->actingAs($this->user)
            ->put(route('bookings.update', $booking->id), [
                'package_id' => $this->deps['packageB']->id,
            ])
            ->assertRedirect();

        // 2500 - 1150 = 1350 (price B)
        $this->assertEquals(1350.0, (float) $passenger->fresh()->visa_profit);
    }
}
