<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Role;
use App\Models\StayDurationLimit;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingIndexDiscountButtonTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach(Role::create(['name' => 'Super Admin']));

        $district = District::create(['name' => 'D', 'division' => 'Div']);
        FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();
        CurrencyRate::create(['user_id' => $this->admin->id, 'rate' => 25.0000]);

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->admin->id, 'selling_price' => 2000.00]);
        $package = Package::create([
            'package_name' => 'P',
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000.00,
            'service_charge' => 500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
        $charge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->admin->id,
            'fingerprint_charge' => 300.00,
        ]);
        $branch = Branch::create([
            'name' => 'B'.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'BR'.substr(uniqid(), -6),
        ]);
        $customer = Customer::create([
            'name' => 'C',
            'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);
        $booking = Booking::create([
            'user_id' => $this->admin->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $district->id,
            'package_id' => $package->id,
            'fingerprint_charge_id' => $charge->id,
            'booking_branch_id' => $branch->id,
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
        Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $this->admin->id,
            'total_amount' => 40000.00,
            'paid_amount' => 10000.00,
            'balance' => 30000.00,
            'status' => 'pending',
        ]);
    }

    public function test_discount_button_renders_in_booking_index_total_column_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('bookings.index', ['tab' => 'booking']));

        $response->assertOk();
        $response->assertSee('<button type="button" data-role="index-discount-btn"', false);
        $response->assertSee('id="indexDiscountModal"', false);
    }

    public function test_discount_button_hidden_for_non_admin(): void
    {
        $staff = User::create([
            'name' => 'Staff',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $staff->roles()->attach(Role::create(['name' => 'Auditor']));

        $response = $this->actingAs($staff)->get(route('bookings.index', ['tab' => 'booking']));

        $response->assertOk();
        $response->assertDontSee('<button type="button" data-role="index-discount-btn"', false);
    }
}
