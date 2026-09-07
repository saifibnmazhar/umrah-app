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

class ProfitLossReportBranchFilterTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(): User
    {
        $user = User::create([
            'name' => 'Branch Filter Test User',
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
            'package_name' => 'Branch Filter Package',
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

        return compact('district', 'visaPrice', 'fare', 'package', 'fingerprintCharge');
    }

    private function createBooking(User $user, array $deps, Branch $branch, string $invoice, float $profit = 100.00): Booking
    {
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
            'profit' => $profit,
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

        Passenger::create([
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
            'profit' => 50.00,
        ]);

        return $booking;
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
    public function filters_endpoint_returns_all_branches(): void
    {
        $user = $this->setupUser();
        $this->createBranch('Branch Alpha');
        $this->createBranch('Branch Beta');
        Auth::login($user);

        $response = $this->get(route('api.reports.profit-loss.filters'));

        $response->assertOk();
        $branches = $response->json('branches');
        $this->assertCount(2, $branches);
    }

    /** @test */
    public function data_customer_tab_filters_by_branch(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $branchA = $this->createBranch('Branch A');
        $branchB = $this->createBranch('Branch B');
        $this->createBooking($user, $deps, $branchA, 'INV-A');
        $this->createBooking($user, $deps, $branchB, 'INV-B');
        Auth::login($user);

        $response = $this->get(route('api.reports.profit-loss', [
            'date_from' => now()->subDays(60)->toDateString(),
            'date_to' => now()->addDays(1)->toDateString(),
            'tab' => 'customer',
            'branch_id' => $branchA->id,
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('INV-A', $data[0]['invoice_id']);
        $this->assertEquals(1, $response->json('total'));
    }

    /** @test */
    public function data_passenger_tab_filters_by_branch(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $branchA = $this->createBranch('Branch A');
        $branchB = $this->createBranch('Branch B');
        $this->createBooking($user, $deps, $branchA, 'INV-A');
        $this->createBooking($user, $deps, $branchB, 'INV-B');
        Auth::login($user);

        $response = $this->get(route('api.reports.profit-loss', [
            'date_from' => now()->subDays(60)->toDateString(),
            'date_to' => now()->addDays(1)->toDateString(),
            'tab' => 'passenger',
            'branch_id' => $branchA->id,
        ]));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('INV-A', $data[0]['invoice_id']);
        $this->assertEquals(1, $response->json('total'));
    }

    /** @test */
    public function summary_filters_by_branch(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $branchA = $this->createBranch('Branch A');
        $branchB = $this->createBranch('Branch B');
        $this->createBooking($user, $deps, $branchA, 'INV-A');
        $this->createBooking($user, $deps, $branchB, 'INV-B');
        Auth::login($user);

        $response = $this->get(route('api.reports.profit-loss.summary', [
            'date_from' => now()->subDays(60)->toDateString(),
            'date_to' => now()->addDays(1)->toDateString(),
            'branch_id' => $branchA->id,
        ]));

        $response->assertOk();
        $this->assertEquals(1, $response->json('customer.count'));
        $this->assertEquals(1, $response->json('passenger.count'));
    }

    /** @test */
    public function without_branch_filter_returns_all(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $branchA = $this->createBranch('Branch A');
        $branchB = $this->createBranch('Branch B');
        $this->createBooking($user, $deps, $branchA, 'INV-A');
        $this->createBooking($user, $deps, $branchB, 'INV-B');
        Auth::login($user);

        $response = $this->get(route('api.reports.profit-loss', [
            'date_from' => now()->subDays(60)->toDateString(),
            'date_to' => now()->addDays(1)->toDateString(),
            'tab' => 'customer',
        ]));

        $response->assertOk();
        $this->assertEquals(2, $response->json('total'));
    }

    public function test_view_renders_branch_filter_dropdown_and_passs_branch_to_print(): void
    {
        $html = view('reports.profit-loss')->render();

        $this->assertStringContainsString('All Branches', $html);
        $this->assertStringContainsString('x-model="branchId"', $html);
        $this->assertStringContainsString('loadBranches', $html);
        $this->assertStringContainsString('/api/reports/profit-loss/filters', $html);
        $this->assertStringContainsString('&branch_id=\' + branchId', $html);
        $this->assertStringContainsString("params.set('branch_id', this.branchId)", $html);
    }

    public function test_print_filters_and_displays_branch_name(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $branchA = $this->createBranch('Branch Alfa Print');
        $branchB = $this->createBranch('Branch Beta Print');
        $this->createBooking($user, $deps, $branchA, 'INV-A');
        $this->createBooking($user, $deps, $branchB, 'INV-B');
        Auth::login($user);

        $response = $this->get(route('report.profit-loss.print', [
            'type' => 'customer',
            'date_from' => now()->subDays(60)->toDateString(),
            'date_to' => now()->addDays(1)->toDateString(),
            'branch_id' => $branchA->id,
        ]));

        $response->assertOk();
        $response->assertSee('Booking Branch: Branch Alfa Print');
        $response->assertSee('INV-A');
        $response->assertDontSee('INV-B');
    }
}
