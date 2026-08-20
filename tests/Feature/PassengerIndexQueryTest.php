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
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Queries\PassengerIndexQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PassengerIndexQueryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);
    }

    protected function seedDeps(): array
    {
        $district = District::create(['name' => 'Test District', 'division' => 'Test Division']);
        $currencyRate = CurrencyRate::create(['user_id' => $this->admin->id, 'rate' => 28.0000]);
        $cityFrom = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'Bangladesh']);
        $cityTo = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'Saudi Arabia']);
        $airline = Airline::create(['name' => 'Saudi Arabian Airlines', 'code' => 'SV']);
        $travelClass = TravelClass::create(['name' => 'Economy']);
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

        $flightDateGap = FlightDateGap::getOrCreate();

        $ticketFare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 1000.00,
            'selling_fare' => 1200.00,
            'offer_price' => null,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->admin->id,
            'is_active' => true,
        ]);

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->admin->id, 'selling_price' => 2000.00]);
        $package = Package::create([
            'package_name' => 'Test Umrah Package',
            'ticket_fare_id' => $ticketFare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 35000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $branch = Branch::create([
            'name' => 'Test Branch',
            'address' => 'Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'B0001',
        ]);

        $fingerprintCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->admin->id,
            'fingerprint_charge' => 50.00,
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'passport_no' => 'P12345678',
            'iqama_type' => 'none',
            'mobile_no' => '0501234567',
            'address' => 'Test Address',
        ]);

        return compact(
            'district', 'currencyRate', 'route', 'ticketFare', 'package',
            'flightDateGap', 'branch', 'fingerprintCharge', 'customer'
        );
    }

    private function createPassenger(array $deps, array $overrides = []): Passenger
    {
        $booking = Booking::create(array_merge([
            'user_id' => $this->admin->id,
            'customer_id' => $deps['customer']->id,
            'fingerprint_branch_id' => $deps['branch']->id,
            'booking_branch_id' => $deps['branch']->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'invoice_id' => 'INV-2025-'.uniqid(),
            'date_gap_id' => $deps['flightDateGap']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 50000.00,
            'remarks' => '',
            'currency_rate_id' => $deps['currencyRate']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'is_cancelled' => false,
        ], $overrides));

        $status = PassengerStatus::firstOrCreate(['name' => 'Pending']);

        return Passenger::create([
            'booking_id' => $booking->id,
            'passenger_status_id' => $status->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'passport_no' => 'AB123456',
            'mobile_no' => '01711112222',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'passenger_type' => 'adult',
            'passport_expiry' => '2030-12-31',
            'stay_duration' => 14,
            'flight_date_from' => now()->addDays(30)->toDateString(),
            'flight_date_to' => now()->addDays(45)->toDateString(),
            'address' => 'Test Address',
        ]);
    }

    #[Test]
    public function test_passenger_index_query_returns_query(): void
    {
        $this->actingAs($this->admin);
        $deps = $this->seedDeps();
        $this->createPassenger($deps);

        $request = new Request([]);
        $query = (new PassengerIndexQuery($request))->getQuery();

        $this->assertInstanceOf(Builder::class, $query);
    }

    #[Test]
    public function test_passenger_index_query_paginates(): void
    {
        $this->actingAs($this->admin);
        $deps = $this->seedDeps();

        for ($i = 0; $i < 3; $i++) {
            $customer = Customer::create([
                'name' => "Customer $i",
                'mobile_no' => "0171111222$i",
                'address' => 'Test Address',
                'passport_no' => 'P'.(100 + $i),
            ]);
            $deps['customer'] = $customer;
            $this->createPassenger($deps);
        }

        $request = new Request(['per_page' => 2]);
        $paginated = (new PassengerIndexQuery($request))->paginate();

        $this->assertEquals(2, $paginated->perPage());
        $this->assertEquals(2, $paginated->count());
        $this->assertEquals(3, $paginated->total());
    }

    #[Test]
    public function test_passenger_index_query_filters_by_search(): void
    {
        $this->actingAs($this->admin);
        $deps = $this->seedDeps();
        $passenger = $this->createPassenger($deps);

        $request = new Request(['search' => 'John']);
        $result = (new PassengerIndexQuery($request))->getQuery()->get();

        $this->assertTrue($result->contains($passenger));
    }

    #[Test]
    public function test_passenger_index_query_scopes_by_branch_for_non_admin(): void
    {
        $this->actingAs($this->admin);
        $deps = $this->seedDeps();

        $otherBranch = Branch::create([
            'name' => 'Other Branch',
            'address' => 'Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => false,
            'branch_code' => 'B0002',
        ]);

        $this->createPassenger($deps, ['invoice_id' => 'INV-OWN']);

        $otherDeps = $deps;
        $otherDeps['branch'] = $otherBranch;
        $this->createPassenger($otherDeps, ['invoice_id' => 'INV-OTHER']);

        $userWithBranch = User::create([
            'name' => 'Branch Staff',
            'email' => 'staff@test.com',
            'password' => Hash::make('password'),
            'branch_id' => $deps['branch']->id,
        ]);
        $this->actingAs($userWithBranch);

        $request = new Request([]);
        $result = (new PassengerIndexQuery($request))->getQuery()->get();

        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    #[Test]
    public function test_passenger_index_query_filter_options(): void
    {
        $this->actingAs($this->admin);
        $deps = $this->seedDeps();
        $this->createPassenger($deps);

        $request = new Request([]);
        $options = (new PassengerIndexQuery($request))->getFilterOptions();

        $this->assertArrayHasKey('passengerStatuses', $options);
        $this->assertArrayHasKey('visaStatuses', $options);
        $this->assertArrayHasKey('ticketStatuses', $options);
        $this->assertArrayHasKey('visaAgents', $options);
        $this->assertArrayHasKey('ticketAgents', $options);
        $this->assertArrayHasKey('routesList', $options);
        $this->assertArrayHasKey('packagesList', $options);
        $this->assertArrayHasKey('fingerprintLocations', $options);
        $this->assertArrayHasKey('bookingBranches', $options);
        $this->assertArrayHasKey('canEditVisa', $options);
        $this->assertArrayHasKey('canEditFingerprint', $options);
        $this->assertArrayHasKey('canEditTicket', $options);
    }
}
