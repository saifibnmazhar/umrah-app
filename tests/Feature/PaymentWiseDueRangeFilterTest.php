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
use App\Models\Passenger;
use App\Models\Role;
use App\Models\StayDurationLimit;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaymentWiseDueRangeFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private District $district;

    private Package $package;

    private FingerprintCharge $fingerprintCharge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

        $this->district = District::create(['name' => 'Test District', 'division' => 'Test Division']);
        FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();
        CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 25.0000]);

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $this->package = Package::create([
            'package_name' => 'Due Range Package',
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000.00,
            'service_charge' => 500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
        $this->fingerprintCharge = FingerprintCharge::create([
            'district_id' => $this->district->id,
            'user_id' => $this->user->id,
            'fingerprint_charge' => 300.00,
        ]);
    }

    private function createPassengerWithBalance(float $balanceSar, string $passportNo): Passenger
    {
        $branch = Branch::create([
            'name' => 'Branch '.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'BR'.substr(uniqid(), -6),
        ]);

        $customer = Customer::create([
            'name' => 'Customer '.uniqid(),
            'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);

        $booking = Booking::create([
            'user_id' => $this->user->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $this->district->id,
            'package_id' => $this->package->id,
            'fingerprint_charge_id' => $this->fingerprintCharge->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'home',
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
            'user_id' => $this->user->id,
            'total_amount' => 40000.00,
            'paid_amount' => 40000.00 - $balanceSar,
            'balance' => $balanceSar,
            'status' => $balanceSar > 0 ? 'pending' : 'paid',
        ]);

        return Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Pax'.substr(uniqid(), -4),
            'last_name' => 'Test',
            'passport_no' => $passportNo,
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
            'package_value' => 25000.00,
        ]);
    }

    private function seedScenario(): array
    {
        // Rate is 25: BDT = SAR * 25. Threshold 1000 BDT = 40 SAR.
        return [
            'clear' => $this->createPassengerWithBalance(0, 'PP-CLEAR-'.substr(uniqid(), -4)),
            'below' => $this->createPassengerWithBalance(10, 'PP-BELOW-'.substr(uniqid(), -4)),
            'exact' => $this->createPassengerWithBalance(40, 'PP-EXACT-'.substr(uniqid(), -4)),
            'above' => $this->createPassengerWithBalance(60, 'PP-ABOVE-'.substr(uniqid(), -4)),
        ];
    }

    private function passportNos($data): array
    {
        $items = $data instanceof LengthAwarePaginator ? $data->items() : $data;

        return collect($items)->pluck('passport_no')->all();
    }

    public function test_due_below_1000_bdt_excludes_cleared_and_above(): void
    {
        $p = $this->seedScenario();

        $response = $this->actingAs($this->user)->getJson('/api/bookings/passengers?tab=passenger&payment_wise=due_below_1000');

        $response->assertOk();
        $nos = $this->passportNos($response->json('data'));

        $this->assertContains($p['below']->passport_no, $nos);
        $this->assertNotContains($p['clear']->passport_no, $nos);
        $this->assertNotContains($p['exact']->passport_no, $nos);
        $this->assertNotContains($p['above']->passport_no, $nos);
    }

    public function test_due_above_1000_bdt_includes_exact_boundary(): void
    {
        $p = $this->seedScenario();

        $response = $this->actingAs($this->user)->getJson('/api/bookings/passengers?tab=passenger&payment_wise=due_above_1000');

        $response->assertOk();
        $nos = $this->passportNos($response->json('data'));

        $this->assertContains($p['exact']->passport_no, $nos);
        $this->assertContains($p['above']->passport_no, $nos);
        $this->assertNotContains($p['clear']->passport_no, $nos);
        $this->assertNotContains($p['below']->passport_no, $nos);
    }

    public function test_filter_options_render_in_blade(): void
    {
        $this->seedScenario();

        $response = $this->actingAs($this->user)->get(route('bookings.index', ['tab' => 'passenger']));

        $response->assertOk();
        $response->assertSee('due_below_1000', false);
        $response->assertSee('due_above_1000', false);
    }
}
