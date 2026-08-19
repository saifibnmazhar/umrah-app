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
use App\Models\Role;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingIndexTableTest extends TestCase
{
    use RefreshDatabase;

    private $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->superAdmin = User::factory()->create(['email' => 'admin@test.com', 'password' => 'password']);
        $this->superAdmin->roles()->attach($superAdminRole);
        $this->actingAs($this->superAdmin);
    }

    private function seedPrerequisites(): array
    {
        $district = District::create(['name' => 'Test District', 'division' => 'Test Division']);
        $currencyRate = CurrencyRate::create(['user_id' => $this->superAdmin->id, 'rate' => 28.0000]);

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
            'user_id' => $this->superAdmin->id,
            'is_active' => true,
        ]);

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->superAdmin->id, 'selling_price' => 2000.00]);

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
            'user_id' => $this->superAdmin->id,
            'fingerprint_charge' => 50.00,
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'passport_no' => 'P12345678',
            'iqama_type' => 'none',
            'iqama_no' => 'IQAMA001',
            'mobile_no' => '0501234567',
            'address' => 'Test Address',
        ]);

        return compact(
            'district', 'currencyRate', 'route', 'ticketFare', 'package', 'visaPrice',
            'flightDateGap', 'branch', 'fingerprintCharge', 'customer'
        );
    }

    private function createBooking(array $deps, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->superAdmin->id,
            'customer_id' => $deps['customer']->id,
            'fingerprint_branch_id' => $deps['branch']->id,
            'booking_branch_id' => $deps['branch']->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
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
            'is_cancelled' => false,
        ], $overrides));
    }

    #[Test]
    public function test_bookings_index_renders_livewire_component(): void
    {
        $response = $this->get('/bookings');
        $response->assertOk();
        $response->assertSee('Booking');
        $response->assertSeeLivewire('booking.booking-index-table');
    }

    #[Test]
    public function test_booking_index_table_renders_empty_state(): void
    {
        Livewire::test('booking.booking-index-table')
            ->assertSee('No bookings found');
    }

    #[Test]
    public function test_booking_index_table_shows_booking(): void
    {
        $deps = $this->seedPrerequisites();
        $booking = $this->createBooking($deps);

        Livewire::test('booking.booking-index-table')
            ->assertSee('INV-2025-')
            ->assertSee('Test Customer')
            ->assertSee('Active')
            ->assertSee('Total Booking');
    }

    #[Test]
    public function test_booking_index_table_has_filter_inputs(): void
    {
        Livewire::test('booking.booking-index-table')
            ->assertSee('Search by Mobile or Invoice No')
            ->assertSee('Fingerprint Location')
            ->assertSee('Booking Status')
            ->assertSee('All Branches')
            ->assertSee('Clear');
    }

    #[Test]
    public function test_booking_index_table_filter_by_search(): void
    {
        $deps = $this->seedPrerequisites();
        $this->createBooking($deps, ['invoice_id' => 'INV-SEARCH-TEST']);

        Livewire::test('booking.booking-index-table')
            ->set('search', 'INV-SEARCH-TEST')
            ->assertSee('INV-SEARCH-TEST')
            ->assertDontSee('No bookings found');
    }

    #[Test]
    public function test_booking_index_table_filter_by_booking_status(): void
    {
        $deps = $this->seedPrerequisites();
        $this->createBooking($deps, ['is_cancelled' => true, 'invoice_id' => 'INV-CANCELLED']);

        Livewire::test('booking.booking-index-table')
            ->set('bookingStatus', 'cancelled')
            ->assertSee('Cancelled');
    }

    #[Test]
    public function test_booking_index_table_reset_filters(): void
    {
        $deps = $this->seedPrerequisites();
        $this->createBooking($deps, ['invoice_id' => 'INV-RESET-TEST']);

        Livewire::test('booking.booking-index-table')
            ->set('search', 'NONEXISTENT-12345')
            ->assertSee('No bookings found')
            ->call('resetFilters')
            ->assertSee('INV-RESET-TEST');
    }

    #[Test]
    public function test_booking_index_table_has_pagination(): void
    {
        $deps = $this->seedPrerequisites();
        for ($i = 0; $i < 15; $i++) {
            $this->createBooking($deps, ['invoice_id' => 'INV-PAGE-'.$i]);
        }

        Livewire::test('booking.booking-index-table')
            ->assertSee('Page');
    }
}
