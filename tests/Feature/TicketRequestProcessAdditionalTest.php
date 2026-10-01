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
use App\Models\TicketAgent;
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

        $agent = TicketAgent::create(['name' => 'Agent A', 'address' => 'Addr', 'contacts' => '0123']);

        return compact('district', 'customer', 'package', 'fpCharge', 'fare', 'agent');
    }

    private function validAdditionalPayload(array $overrides = []): array
    {
        return array_merge([
            'ticket_fare_id' => $this->deps['fare']->id,
            'route_type' => 'round',
            'pnr' => 'PNR123',
            'ticket_number' => 'TKT123',
            'ticket_agent_id' => $this->deps['agent']->id,
            'issued_date' => now()->toDateString(),
            'inbound_date' => now()->toDateString(),
            'outbound_date' => now()->addDays(7)->toDateString(),
            'net_fare' => 1234.567890,
        ], $overrides);
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

        $response = $this->putJson(route('ticket-requests.process-additional', $ticketRequest->id), $this->validAdditionalPayload());

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

    public function test_process_additional_rejects_missing_net_fare(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(
            route('ticket-requests.process-additional', $ticketRequest->id),
            $this->validAdditionalPayload(['net_fare' => null])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['net_fare']);
        $this->assertDatabaseHas('ticket_requests', [
            'id' => $ticketRequest->id,
            'status' => 'pending',
        ]);
    }

    public function test_process_additional_rejects_negative_net_fare(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(
            route('ticket-requests.process-additional', $ticketRequest->id),
            $this->validAdditionalPayload(['net_fare' => -5])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['net_fare']);
    }

    public function test_process_additional_rejects_missing_pnr(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(
            route('ticket-requests.process-additional', $ticketRequest->id),
            $this->validAdditionalPayload(['pnr' => null])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['pnr']);
    }

    public function test_process_additional_rejects_missing_ticket_number(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(
            route('ticket-requests.process-additional', $ticketRequest->id),
            $this->validAdditionalPayload(['ticket_number' => null])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['ticket_number']);
    }

    public function test_process_additional_rejects_missing_ticket_agent(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(
            route('ticket-requests.process-additional', $ticketRequest->id),
            $this->validAdditionalPayload(['ticket_agent_id' => null])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['ticket_agent_id']);
    }

    public function test_process_additional_rejects_missing_route_type(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(
            route('ticket-requests.process-additional', $ticketRequest->id),
            $this->validAdditionalPayload(['route_type' => null])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['route_type']);
    }

    public function test_process_additional_requires_inbound_date_for_round_route(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(
            route('ticket-requests.process-additional', $ticketRequest->id),
            $this->validAdditionalPayload(['route_type' => 'round', 'inbound_date' => null])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['inbound_date']);
    }

    public function test_process_additional_requires_outbound_date_for_round_route(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(
            route('ticket-requests.process-additional', $ticketRequest->id),
            $this->validAdditionalPayload(['route_type' => 'round', 'outbound_date' => null])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['outbound_date']);
    }

    public function test_process_additional_requires_inbound_date_for_oneway_inbound_route(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(
            route('ticket-requests.process-additional', $ticketRequest->id),
            $this->validAdditionalPayload(['route_type' => 'oneway_inbound', 'inbound_date' => null])
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['inbound_date']);
    }

    public function test_process_additional_allows_missing_inbound_date_when_oneway_outbound(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(
            route('ticket-requests.process-additional', $ticketRequest->id),
            $this->validAdditionalPayload([
                'route_type' => 'oneway_outbound',
                'inbound_date' => null,
                'outbound_date' => now()->toDateString(),
            ])
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNull(IssuedTicket::latest('id')->first()->inbound_date);
    }

    public function test_process_additional_allows_missing_outbound_date_when_oneway_inbound(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassenger();
        $ticketRequest = $this->createPendingAdditionalRequest($booking, $passenger);

        $response = $this->putJson(
            route('ticket-requests.process-additional', $ticketRequest->id),
            $this->validAdditionalPayload([
                'route_type' => 'oneway_inbound',
                'outbound_date' => null,
                'inbound_date' => now()->toDateString(),
            ])
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNull(IssuedTicket::latest('id')->first()->outbound_date);
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

    public function test_confirm_process_payload_includes_route_type(): void
    {
        $view = file_get_contents(resource_path('views/tickets/add-confirmation.blade.php'));

        $this->assertMatchesRegularExpression(
            '/route_type\s*:\s*document\.getElementById\(\s*[\'"]inputRouteType[\'"]\s*\)\.value/',
            $view
        );
    }

    public function test_add_confirmation_gates_ticket_dropdown_on_all_three_filters(): void
    {
        $view = file_get_contents(resource_path('views/tickets/add-confirmation.blade.php'));

        $this->assertStringContainsString('hasCompleteFilters', $view);
        $this->assertStringContainsString('inputTicketType', $view);
        $this->assertStringContainsString('inputRouteType', $view);
        $this->assertStringContainsString('inputFlightType', $view);
        $this->assertStringContainsString('getElementById(\'inputTicketFare\').disabled', $view);
    }

    public function test_add_confirmation_shows_inline_field_errors(): void
    {
        $view = file_get_contents(resource_path('views/tickets/add-confirmation.blade.php'));

        $this->assertStringContainsString('setFieldError', $view);
        $this->assertStringContainsString('data-error-for', $view);
        $this->assertStringContainsString('clearFieldErrors', $view);
    }

    public function test_add_confirmation_maps_server_validation_errors_to_fields(): void
    {
        $view = file_get_contents(resource_path('views/tickets/add-confirmation.blade.php'));

        $this->assertStringContainsString('data.errors', $view);
        $this->assertStringContainsString('inputPnr', $view);
        $this->assertStringContainsString('inputTicketNumber', $view);
        $this->assertStringContainsString('inputAgent', $view);
        $this->assertStringContainsString('inputNetFare', $view);
    }
}
