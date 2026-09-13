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
use Tests\TestCase;

class PassengerServiceRequiredFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private District $district;

    private Package $package;

    private FingerprintCharge $fingerprintCharge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Test User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach(Role::create(['name' => 'Super Admin']));

        $this->district = District::create(['name' => 'Test District', 'division' => 'Test Division']);
        FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();
        CurrencyRate::create(['user_id' => $this->admin->id, 'rate' => 25.0000]);

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->admin->id, 'selling_price' => 2000.00]);
        $this->package = Package::create([
            'package_name' => 'Service Filter Package',
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000.00,
            'service_charge' => 500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
        $this->fingerprintCharge = FingerprintCharge::create([
            'district_id' => $this->district->id,
            'user_id' => $this->admin->id,
            'fingerprint_charge' => 300.00,
        ]);
    }

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::create([
            'name' => $roleName.' User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $roleName]));

        return $user;
    }

    private function makePassenger(string $serviceRequired, string $passportNo): Passenger
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
            'user_id' => $this->admin->id,
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
            'user_id' => $this->admin->id,
            'total_amount' => 40000.00,
            'paid_amount' => 40000.00,
            'balance' => 0,
            'status' => 'paid',
        ]);

        return Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Pax',
            'last_name' => $serviceRequired,
            'passport_no' => $passportNo,
            'mobile_no' => '0500000000',
            'date_of_birth' => '1990-01-01',
            'passenger_type' => 'adult',
            'passport_expiry' => '2030-12-31',
            'stay_duration' => 14,
            'service_required' => $serviceRequired,
            'flight_date_from' => now()->addDays(5)->toDateString(),
            'flight_date_to' => now()->addDays(15)->toDateString(),
            'ticket_status' => 'pending',
            'visa_status' => 'pending',
            'address' => 'Addr',
            'package_value' => 25000,
        ]);
    }

    private function passportNos($paginator): array
    {
        return collect($paginator->items())->pluck('passport_no')->all();
    }

    public function test_visa_filter_shows_visa_and_all_passengers(): void
    {
        $visa = $this->makePassenger('visa_only', 'PPVISA01');
        $ticket = $this->makePassenger('ticket_only', 'PPTICK01');
        $all = $this->makePassenger('all', 'PPALL001');

        $response = $this->actingAs($this->admin)->get(route('bookings.index', [
            'tab' => 'passenger',
            'service_required' => 'visa_only',
        ]));

        $response->assertOk();
        $nos = $this->passportNos($response->viewData('passengers'));

        $this->assertContains($visa->passport_no, $nos);
        $this->assertContains($all->passport_no, $nos);
        $this->assertNotContains($ticket->passport_no, $nos);
        $this->assertSame('visa_only', $response->viewData('selectedServiceRequired'));
    }

    public function test_ticket_filter_shows_ticket_and_all_passengers(): void
    {
        $visa = $this->makePassenger('visa_only', 'PPVISA02');
        $ticket = $this->makePassenger('ticket_only', 'PPTICK02');
        $all = $this->makePassenger('all', 'PPALL002');

        $response = $this->actingAs($this->admin)->get(route('bookings.index', [
            'tab' => 'passenger',
            'service_required' => 'ticket_only',
        ]));

        $response->assertOk();
        $nos = $this->passportNos($response->viewData('passengers'));

        $this->assertContains($ticket->passport_no, $nos);
        $this->assertContains($all->passport_no, $nos);
        $this->assertNotContains($visa->passport_no, $nos);
    }

    public function test_all_filter_shows_everything(): void
    {
        $visa = $this->makePassenger('visa_only', 'PPVISA03');
        $ticket = $this->makePassenger('ticket_only', 'PPTICK03');
        $all = $this->makePassenger('all', 'PPALL003');

        $response = $this->actingAs($this->admin)->get(route('bookings.index', [
            'tab' => 'passenger',
            'service_required' => 'all',
        ]));

        $response->assertOk();
        $nos = $this->passportNos($response->viewData('passengers'));

        $this->assertContains($visa->passport_no, $nos);
        $this->assertContains($ticket->passport_no, $nos);
        $this->assertContains($all->passport_no, $nos);
    }

    public function test_visa_staff_defaults_to_visa_filter(): void
    {
        $visa = $this->makePassenger('visa_only', 'PPVISA04');
        $ticket = $this->makePassenger('ticket_only', 'PPTICK04');
        $all = $this->makePassenger('all', 'PPALL004');
        $user = $this->makeUserWithRole('Visa Staff');

        $response = $this->actingAs($user)->get(route('bookings.index', ['tab' => 'passenger']));

        $response->assertOk();
        $nos = $this->passportNos($response->viewData('passengers'));

        $this->assertContains($visa->passport_no, $nos);
        $this->assertContains($all->passport_no, $nos);
        $this->assertNotContains($ticket->passport_no, $nos);
        $this->assertSame('visa_only', $response->viewData('selectedServiceRequired'));
    }

    public function test_ticket_staff_defaults_to_ticket_filter(): void
    {
        $visa = $this->makePassenger('visa_only', 'PPVISA05');
        $ticket = $this->makePassenger('ticket_only', 'PPTICK05');
        $all = $this->makePassenger('all', 'PPALL005');
        $user = $this->makeUserWithRole('Ticket Staff');

        $response = $this->actingAs($user)->get(route('bookings.index', ['tab' => 'passenger']));

        $response->assertOk();
        $nos = $this->passportNos($response->viewData('passengers'));

        $this->assertContains($ticket->passport_no, $nos);
        $this->assertContains($all->passport_no, $nos);
        $this->assertNotContains($visa->passport_no, $nos);
        $this->assertSame('ticket_only', $response->viewData('selectedServiceRequired'));
    }

    public function test_other_roles_default_to_all(): void
    {
        $visa = $this->makePassenger('visa_only', 'PPVISA06');
        $ticket = $this->makePassenger('ticket_only', 'PPTICK06');
        $all = $this->makePassenger('all', 'PPALL006');

        $response = $this->actingAs($this->admin)->get(route('bookings.index', ['tab' => 'passenger']));

        $response->assertOk();
        $nos = $this->passportNos($response->viewData('passengers'));

        $this->assertContains($visa->passport_no, $nos);
        $this->assertContains($ticket->passport_no, $nos);
        $this->assertContains($all->passport_no, $nos);
        $this->assertSame('all', $response->viewData('selectedServiceRequired'));
    }

    public function test_explicit_param_overrides_role_default(): void
    {
        $visa = $this->makePassenger('visa_only', 'PPVISA07');
        $ticket = $this->makePassenger('ticket_only', 'PPTICK07');
        $all = $this->makePassenger('all', 'PPALL007');
        $user = $this->makeUserWithRole('Visa Staff');

        $response = $this->actingAs($user)->get(route('bookings.index', [
            'tab' => 'passenger',
            'service_required' => 'all',
        ]));

        $response->assertOk();
        $nos = $this->passportNos($response->viewData('passengers'));

        $this->assertContains($visa->passport_no, $nos);
        $this->assertContains($ticket->passport_no, $nos);
        $this->assertContains($all->passport_no, $nos);
    }

    public function test_blade_contains_service_required_dropdown(): void
    {
        $response = $this->actingAs($this->admin)->get(route('bookings.index', ['tab' => 'passenger']));

        $response->assertOk();
        $response->assertSee('Service Required', false);
        $response->assertSee('service_required', false);
        $response->assertSee('Excluding Ticket Only', false);
        $response->assertSee('Excluding Visa Only', false);
        $response->assertSee('>All</option>', false);
        $response->assertDontSee('Visa + Ticket', false);
    }
}
