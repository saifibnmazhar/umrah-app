<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TicketRequest;
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Rules\FlightDateSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketRequestProcessAdditionalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $deps;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->deps = $this->createPrerequisites();
    }

    private function createUser(): User
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::create(['name' => 'Ticket Admin']));

        return $user;
    }

    private function createPrerequisites(): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
        $c1 = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'BD']);
        $c2 = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'SA']);
        $airline = Airline::create(['name' => 'SV', 'code' => 'SV']);
        $travelClass = TravelClass::create(['name' => 'Economy']);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);
        $route = Route::create([
            'airline_id' => $airline->id,
            'route_type' => 'round',
            'flight_type' => 'direct',
            'from_city_id' => $c1->id,
            'to_city_id' => $c2->id,
            'return_city_id' => $c1->id,
        ]);

        CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 1.0]);
        StayDurationLimit::getOrCreate();
        FlightDateGap::getOrCreate();
        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);
        PassengerStatus::firstOrCreate(['name' => 'Processing'], ['color' => '#000']);
        Bank::create(['name' => 'B', 'description' => 'd', 'currency' => 'SAR', 'location' => 'KSA']);

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->user->id,
            'fingerprint_charge' => 50.00,
        ]);

        $fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $package = Package::create([
            'package_name' => 'Pkg',
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $customer = Customer::create([
            'name' => 'Cust',
            'passport_no' => 'T1',
            'mobile_no' => '0501',
            'iqama_type' => 'none',
            'address' => 'A',
        ]);

        return compact('district', 'customer', 'package', 'fpCharge', 'fare');
    }

    private function createBookingWithPassenger(): array
    {
        $this->actingAs($this->user);

        $this->post(route('bookings.store'), [
            'customer_id' => $this->deps['customer']->id,
            'district_id' => $this->deps['district']->id,
            'fingerprint_charge_id' => $this->deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $this->deps['package']->id,
            'passengers' => [[
                'first_name' => 'John',
                'last_name' => 'Doe',
                'passport_no' => 'PASS'.uniqid(),
                'date_of_birth' => '1990-01-15',
                'gender' => 'male',
                'passport_expiry' => '2030-12-31',
                'mobile_no' => '0501234567',
                'service_required' => 'all',
                'stay_duration' => 14,
                'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
                'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
                'address' => 'Addr',
            ]],
            'payment' => [
                'amount' => 100,
                'bdt_amount' => 0,
                'currency' => 'SAR',
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ],
        ])->assertRedirect();

        $passenger = Passenger::latest('id')->first();

        return [$passenger->booking, $passenger->fresh()];
    }

    private function createPendingAdditionalRequest($booking, $passenger): TicketRequest
    {
        return TicketRequest::create([
            'user_id' => $this->user->id,
            'request_branch_id' => $this->user->branch_id,
            'booking_id' => $booking->id,
            'passenger_id' => $passenger->id,
            'request_type' => 'additional',
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    }

    public function test_process_additional_stores_net_fare(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(route('ticket-requests.process-additional', $ticketRequest->id), [
            'ticket_fare_id' => $this->deps['fare']->id,
            'pnr' => 'PNR123',
            'ticket_number' => 'TKT123',
            'net_fare' => 1234.567890,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $issuedTicket = IssuedTicket::latest('id')->first();
        $this->assertNotNull($issuedTicket);
        $this->assertEqualsWithDelta(1234.567890, (float) $issuedTicket->net_fare, 0.000001);
        $this->assertSame('additional', $issuedTicket->issue_type);

        $this->assertDatabaseHas('ticket_requests', [
            'id' => $ticketRequest->id,
            'status' => 'processed',
            'result_issued_ticket_id' => $issuedTicket->id,
        ]);
    }

    public function test_process_additional_defaults_net_fare_to_zero_when_omitted(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(route('ticket-requests.process-additional', $ticketRequest->id), [
            'ticket_fare_id' => $this->deps['fare']->id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        $issuedTicket = IssuedTicket::latest('id')->first();
        $this->assertNotNull($issuedTicket);
        $this->assertEqualsWithDelta(0, (float) $issuedTicket->net_fare, 0.000001);
    }

    public function test_confirm_process_payload_includes_net_fare(): void
    {
        $view = file_get_contents(resource_path('views/tickets/add-confirmation.blade.php'));

        $this->assertStringContainsString('confirmProcess()', $view);
        $this->assertMatchesRegularExpression(
            '/net_fare\s*:\s*parseFloat\(\s*document\.getElementById\(\s*[\'"]inputNetFare[\'"]\s*\)\.value\s*\)/',
            $view
        );
    }
}
