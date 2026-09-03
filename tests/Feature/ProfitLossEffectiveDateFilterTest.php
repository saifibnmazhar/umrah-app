<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProfitLossEffectiveDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(): User
    {
        $user = User::create([
            'name' => 'Effective Filter User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $user;
    }

    private function seedPrerequisites(User $user): array
    {
        $district = District::create(['name' => 'Test District', 'division' => 'Test Division']);

        $cityFrom = CityCode::create(['city_name' => 'Dhaka', 'code' => uniqid('D'), 'country' => 'Bangladesh']);
        $cityTo = CityCode::create(['city_name' => 'Riyadh', 'code' => uniqid('R'), 'country' => 'Saudi Arabia']);

        $airline = Airline::create(['name' => 'SV '.uniqid(), 'code' => substr(uniqid(), -2)]);
        $travelClass = TravelClass::create(['name' => 'Economy '.uniqid()]);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);

        $route = Route::create([
            'airline_id' => $airline->id,
            'route_type' => 'round',
            'flight_type' => 'direct',
            'from_city_id' => $cityFrom->id,
            'to_city_id' => $cityTo->id,
            'return_city_id' => $cityFrom->id,
            'additional_gap' => null,
        ]);

        FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();

        CurrencyRate::create(['user_id' => $user->id, 'rate' => 28.0000]);

        $visaPrice = VisaSellingPrice::create([
            'user_id' => $user->id,
            'selling_price' => 2000.00,
        ]);

        $fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 24000.00,
            'selling_fare' => 30000.00,
            'offer_price' => null,
            'child_fare_percentage' => 50.00,
            'infant_fare_percentage' => 20.00,
            'with_meal' => true,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $package = Package::create([
            'package_name' => 'Effective Filter Package',
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000.00,
            'service_charge' => 500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $fingerprintCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $user->id,
            'fingerprint_charge' => 300.00,
        ]);

        return compact('district', 'fare', 'package', 'fingerprintCharge');
    }

    private function createBookingWithPassenger(
        User $user,
        array $deps,
        Branch $branch,
        string $invoice,
        array $passengerOverrides = []
    ): array {
        $customer = Customer::create([
            'name' => 'Customer '.$invoice,
            'passport_no' => 'P'.$invoice,
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);

        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'booking_branch_id' => $branch->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'invoice_id' => $invoice,
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000.00,
            'remarks' => '',
            'is_cancelled' => false,
            'profit' => 100.00,
            'created_at' => now(),
        ]);

        Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 40000.00,
            'paid_amount' => 0,
            'balance' => 40000.00,
            'status' => 'pending',
        ]);

        $attrs = array_merge([
            'booking_id' => $booking->id,
            'first_name' => 'Pax'.$invoice,
            'last_name' => 'Test',
            'passport_no' => 'PP'.$invoice,
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
            'profit' => 100.00,
        ], $passengerOverrides);

        $passenger = Passenger::create($attrs);

        return [$booking, $passenger];
    }

    private function createBranch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'BR'.substr(md5(uniqid()), 0, 6),
        ]);
    }

    /** @test */
    public function data_passenger_tab_excludes_passengers_with_no_component_in_effective_range(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $branch = $this->createBranch('Branch');

        // Passenger A: visa effective 2024-01-15 (before from), ticket effective 2024-08-15 (after to)
        // No component in [2024-03-01, 2024-06-30] -> should NOT appear (Bug 1 false positive regression).
        $this->createBookingWithPassenger($user, $deps, $branch, 'INV-A', [
            'visa_profit' => 100.00,
            'visa_profit_effective_at' => '2024-01-15 10:00:00',
            'ticket_profit' => 50.00,
            'ticket_profit_effective_at' => '2024-08-15 10:00:00',
        ]);

        // Passenger B: visa effective 2024-04-10 (in range) -> should appear.
        $this->createBookingWithPassenger($user, $deps, $branch, 'INV-B', [
            'visa_profit' => 200.00,
            'visa_profit_effective_at' => '2024-04-10 10:00:00',
        ]);

        Auth::login($user);

        $response = $this->get(route('api.reports.profit-loss', [
            'tab' => 'passenger',
            'effective_date_from' => '2024-03-01',
            'effective_date_to' => '2024-06-30',
        ]));

        $response->assertOk();
        $invoices = collect($response->json('data'))->pluck('invoice_id');
        $this->assertCount(1, $invoices);
        $this->assertNotContains('INV-A', $invoices);
        $this->assertContains('INV-B', $invoices);
    }

    /** @test */
    public function data_passenger_tab_recomputes_total_profit_to_in_range_components(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $branch = $this->createBranch('Branch');

        // Passenger: visa effective in range (200), ticket effective out of range (50).
        $this->createBookingWithPassenger($user, $deps, $branch, 'INV-C', [
            'visa_profit' => 200.00,
            'visa_profit_effective_at' => '2024-04-10 10:00:00',
            'ticket_profit' => 50.00,
            'ticket_profit_effective_at' => '2024-09-01 10:00:00',
            'profit' => 250.00,
        ]);

        Auth::login($user);

        $response = $this->get(route('api.reports.profit-loss', [
            'tab' => 'passenger',
            'effective_date_from' => '2024-03-01',
            'effective_date_to' => '2024-06-30',
        ]));

        $response->assertOk();
        $rows = $response->json('data');
        $this->assertCount(1, $rows);
        $this->assertEquals(200.0, (float) $rows[0]['total_profit']);
    }

    /** @test */
    public function summary_recomputes_passenger_total_profit_from_in_range_components(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $branch = $this->createBranch('Branch');

        // Two passengers, visa profit in range, ticket profit out of range.
        $this->createBookingWithPassenger($user, $deps, $branch, 'INV-D', [
            'visa_profit' => 100.00,
            'visa_profit_effective_at' => '2024-04-10 10:00:00',
            'ticket_profit' => 1000.00,
            'ticket_profit_effective_at' => '2024-09-01 10:00:00',
            'profit' => 1100.00,
        ]);
        $this->createBookingWithPassenger($user, $deps, $branch, 'INV-E', [
            'visa_profit' => 50.00,
            'visa_profit_effective_at' => '2024-05-01 10:00:00',
            'ticket_profit' => 0.00,
            'ticket_profit_effective_at' => null,
            'profit' => 50.00,
        ]);

        Auth::login($user);

        $response = $this->get(route('api.reports.profit-loss.summary', [
            'effective_date_from' => '2024-03-01',
            'effective_date_to' => '2024-06-30',
        ]));

        $response->assertOk();
        $passengerSummary = $response->json('passenger');
        $this->assertEquals(2, (int) $passengerSummary['count']);
        // Option B: total_profit = in-range visa profits (150), not the stored full profits (1150).
        $this->assertEquals(150.0, (float) $passengerSummary['total_profit']);
        $this->assertEquals(150.0, (float) $passengerSummary['total_visa_profit']);
        $this->assertEquals(0.0, (float) $passengerSummary['total_ticket_profit']);
    }

    /** @test */
    public function print_filters_and_recomputes_passenger_totals_in_effective_mode(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $branch = $this->createBranch('Branch');

        $this->createBookingWithPassenger($user, $deps, $branch, 'INV-F', [
            'visa_profit' => 100.00,
            'visa_profit_effective_at' => '2024-01-15 10:00:00',
            'ticket_profit' => 50.00,
            'ticket_profit_effective_at' => '2024-08-15 10:00:00',
            'profit' => 150.00,
        ]);
        $this->createBookingWithPassenger($user, $deps, $branch, 'INV-G', [
            'visa_profit' => 300.00,
            'visa_profit_effective_at' => '2024-04-10 10:00:00',
            'ticket_profit' => 40.00,
            'ticket_profit_effective_at' => '2024-09-01 10:00:00',
            'profit' => 340.00,
        ]);

        Auth::login($user);

        $response = $this->get(route('report.profit-loss.print', [
            'type' => 'passenger',
            'effective_date_from' => '2024-03-01',
            'effective_date_to' => '2024-06-30',
        ]));

        $response->assertOk();
        $response->assertDontSee('INV-F');
        $response->assertSee('INV-G');
        // INV-G total should show only in-range visa profit (300), not the stored 340.
        $response->assertSeeText('SAR 300');
        $response->assertDontSeeText('SAR 340');
    }

    /** @test */
    public function view_sets_booking_date_defaults_on_load(): void
    {
        $html = view('reports.profit-loss')->render();

        $this->assertStringContainsString('booking_date_from = thirtyDaysAgo', $html);
        $this->assertStringContainsString('booking_date_to = today', $html);
        $this->assertStringContainsString('effective_date_from = thirtyDaysAgo', $html);
        $this->assertStringContainsString("activeDateFilter = 'effective'", $html);
    }
}
