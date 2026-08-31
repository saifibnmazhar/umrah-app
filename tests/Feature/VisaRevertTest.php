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
use App\Models\Fingerprint;
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
use App\Models\VisaAgent;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use App\Services\ProfitCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisaRevertTest extends TestCase
{
    use RefreshDatabase;

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
        $this->deps = $this->seedPrerequisites();
    }

    private function seedPrerequisites(): array
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

        CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 28.0000]);

        $visaPrice = VisaSellingPrice::create([
            'user_id' => $this->user->id,
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
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $package = Package::create([
            'package_name' => 'Revert Test Package',
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000.00,
            'service_charge' => 500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $fingerprintCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->user->id,
            'fingerprint_charge' => 300.00,
        ]);

        $visaAgent = VisaAgent::create([
            'name' => 'Visa Agent '.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
        ]);

        return compact('district', 'visaPrice', 'fare', 'package', 'fingerprintCharge', 'visaAgent');
    }

    private function createBooking(): Booking
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
            'district_id' => $this->deps['district']->id,
            'package_id' => $this->deps['package']->id,
            'fingerprint_charge_id' => $this->deps['fingerprintCharge']->id,
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
            'paid_amount' => 0,
            'balance' => 40000.00,
            'status' => 'pending',
        ]);

        Fingerprint::create([
            'booking_id' => $booking->id,
            'deadline' => now()->addDays(7),
            'cost' => 100.00,
        ]);

        return $booking;
    }

    private function createPassenger(Booking $booking, array $overrides = []): Passenger
    {
        return Passenger::create(array_merge([
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
            'package_value' => 25000.00,
        ], $overrides));
    }

    private function createVisaSubmission(Passenger $passenger, array $overrides = []): VisaSubmission
    {
        return VisaSubmission::create(array_merge([
            'passenger_id' => $passenger->id,
            'visa_selling_price_id' => $this->deps['visaPrice']->id,
            'visa_agent_id' => $this->deps['visaAgent']->id,
            'agent_commission' => 100.00,
            'net_visa_cost' => 1000.00,
            'additional_cost' => 50.00,
            'final_cost' => 1150.00,
            'visa_number' => 'VS-12345',
            'status' => 'issued',
            'is_cancelled' => false,
        ], $overrides));
    }

    private function setupIssuedScenario(): array
    {
        $booking = $this->createBooking();
        $passenger = $this->createPassenger($booking);
        $visa = $this->createVisaSubmission($passenger);

        return compact('booking', 'passenger', 'visa');
    }

    public function test_can_revert_issued_visa(): void
    {
        ['booking' => $booking, 'passenger' => $passenger] = $this->setupIssuedScenario();

        $response = $this->actingAs($this->user)->postJson(route('bookings.passengers.visa-revert', [$booking->id, $passenger->id]));

        $response->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('visa_submissions', [
            'id' => $passenger->visaSubmission->id,
            'status' => 'submitted',
            'visa_number' => null,
            'additional_cost' => null,
        ]);
    }

    public function test_revert_clears_visa_number(): void
    {
        ['booking' => $booking, 'passenger' => $passenger, 'visa' => $visa] = $this->setupIssuedScenario();
        $this->assertNotNull($visa->visa_number);

        $this->actingAs($this->user)->postJson(route('bookings.passengers.visa-revert', [$booking->id, $passenger->id]))->assertOk();

        $this->assertNull($passenger->visaSubmission->fresh()->visa_number);
    }

    public function test_revert_clears_additional_cost(): void
    {
        ['booking' => $booking, 'passenger' => $passenger, 'visa' => $visa] = $this->setupIssuedScenario();
        $this->assertNotNull($visa->additional_cost);

        $this->actingAs($this->user)->postJson(route('bookings.passengers.visa-revert', [$booking->id, $passenger->id]))->assertOk();

        $this->assertNull($passenger->visaSubmission->fresh()->additional_cost);
    }

    public function test_revert_recalculates_final_cost(): void
    {
        ['booking' => $booking, 'passenger' => $passenger, 'visa' => $visa] = $this->setupIssuedScenario();
        $this->assertEqualsWithDelta(1150.00, (float) $visa->final_cost, 0.001);

        $this->actingAs($this->user)->postJson(route('bookings.passengers.visa-revert', [$booking->id, $passenger->id]))->assertOk();

        $this->assertEqualsWithDelta(1100.00, (float) $passenger->visaSubmission->fresh()->final_cost, 0.001);
    }

    public function test_revert_preserves_agent_details(): void
    {
        ['booking' => $booking, 'passenger' => $passenger, 'visa' => $visa] = $this->setupIssuedScenario();

        $this->actingAs($this->user)->postJson(route('bookings.passengers.visa-revert', [$booking->id, $passenger->id]))->assertOk();

        $reverted = $passenger->visaSubmission->fresh();
        $this->assertEquals($visa->visa_agent_id, $reverted->visa_agent_id);
        $this->assertEquals($visa->commission_agent_id, $reverted->commission_agent_id);
        $this->assertEqualsWithDelta(100.00, (float) $reverted->agent_commission, 0.001);
        $this->assertEqualsWithDelta(1000.00, (float) $reverted->net_visa_cost, 0.001);
    }

    public function test_cannot_revert_submitted_visa(): void
    {
        $booking = $this->createBooking();
        $passenger = $this->createPassenger($booking);
        $this->createVisaSubmission($passenger, ['status' => 'submitted', 'visa_number' => null, 'additional_cost' => null]);

        $this->actingAs($this->user)->postJson(route('bookings.passengers.visa-revert', [$booking->id, $passenger->id]))
            ->assertStatus(422);
    }

    public function test_cannot_revert_pending_visa(): void
    {
        $booking = $this->createBooking();
        $passenger = $this->createPassenger($booking);
        $this->createVisaSubmission($passenger, ['status' => 'pending', 'visa_number' => null, 'additional_cost' => null]);

        $this->actingAs($this->user)->postJson(route('bookings.passengers.visa-revert', [$booking->id, $passenger->id]))
            ->assertStatus(422);
    }

    public function test_cannot_revert_cancelled_visa(): void
    {
        $booking = $this->createBooking();
        $passenger = $this->createPassenger($booking);
        $this->createVisaSubmission($passenger, ['status' => 'cancelled', 'visa_number' => null, 'additional_cost' => null]);

        $this->actingAs($this->user)->postJson(route('bookings.passengers.visa-revert', [$booking->id, $passenger->id]))
            ->assertStatus(422);
    }

    public function test_revert_triggers_profit_recalculation(): void
    {
        ['booking' => $booking, 'passenger' => $passenger] = $this->setupIssuedScenario();

        $breakdownBefore = app(ProfitCalculationService::class)->getPassengerProfitBreakdown($passenger);
        $this->assertGreaterThan(0, $breakdownBefore['visa_profit']);

        $this->actingAs($this->user)->postJson(route('bookings.passengers.visa-revert', [$booking->id, $passenger->id]))->assertOk();

        $breakdownAfter = app(ProfitCalculationService::class)->getPassengerProfitBreakdown($passenger->fresh());
        $this->assertEqualsWithDelta(0, $breakdownAfter['visa_profit'] ?? 0, 0.001);
    }

    public function test_revert_syncs_passenger_status(): void
    {
        ['booking' => $booking, 'passenger' => $passenger] = $this->setupIssuedScenario();
        $passenger->refresh();

        $this->actingAs($this->user)->postJson(route('bookings.passengers.visa-revert', [$booking->id, $passenger->id]))->assertOk();

        $this->assertNotSame('Visa Issued', $passenger->fresh()->computed_status);
    }
}
