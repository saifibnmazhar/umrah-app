<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Bank;
use App\Models\Booking;
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
use App\Models\ReIssuedTicket;
use App\Models\ReIssueRefundReason;
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

class TicketRequestInactiveFareTest extends TestCase
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
        TransactionType::create(['name' => 'Ticket Refund - Re-issue', 'type' => 'debit']);
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

        $inactiveFare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'offer',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 20000.00,
            'selling_fare' => 23000.00,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => false,
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

        $reason = ReIssueRefundReason::create([
            'reason_of' => 're-issue',
            'name' => 'Date Change',
            'default_payment_by' => 'customer',
        ]);

        $agent = TicketAgent::create(['name' => 'Agent A', 'address' => 'Addr', 'contacts' => '0123']);

        return compact('district', 'customer', 'package', 'fpCharge', 'fare', 'inactiveFare', 'reason', 'agent');
    }

    private function createBookingWithPassengerAndIssuedTicket(): array
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
        $issuedTicket = IssuedTicket::where('passenger_id', $passenger->id)->latest('id')->first();
        $issuedTicket->update(['status' => 'issued', 'net_fare' => 25000, 'selling_fare' => 28000]);

        return [$passenger->booking, $passenger->fresh(), $issuedTicket->fresh()];
    }

    private function createPendingRequest(Booking $booking, Passenger $passenger, string $type, ?int $issuedTicketId = null): TicketRequest
    {
        return TicketRequest::create([
            'user_id' => $this->user->id,
            'request_branch_id' => $this->user->branch_id,
            'booking_id' => $booking->id,
            'passenger_id' => $passenger->id,
            'issued_ticket_id' => $issuedTicketId,
            'request_type' => $type,
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    }

    private function reIssuePayload(TicketFare $fare): array
    {
        return [
            'reason_id' => $this->deps['reason']->id,
            're_issue_charge' => 100,
            'payment_by' => 'company',
            'payment_option' => 'customer_payment',
            'ticket_fare_id' => $fare->id,
        ];
    }

    public function test_process_additional_rejects_inactive_fare(): void
    {
        [$booking, $passenger] = $this->createBookingWithPassengerAndIssuedTicket();
        $ticketRequest = $this->createPendingRequest($booking, $passenger, 'additional');

        $ticketsBefore = IssuedTicket::count();

        $response = $this->putJson(route('ticket-requests.process-additional', $ticketRequest->id), [
            'ticket_fare_id' => $this->deps['inactiveFare']->id,
            'route_type' => 'round',
            'pnr' => 'PNR123',
            'ticket_number' => 'TKT123',
            'ticket_agent_id' => $this->deps['agent']->id,
            'issued_date' => now()->toDateString(),
            'inbound_date' => now()->toDateString(),
            'outbound_date' => now()->addDays(7)->toDateString(),
            'net_fare' => 500,
        ]);

        $response->assertStatus(400)->assertJson([
            'message' => 'The selected ticket is inactive and cannot be issued.',
        ]);

        $this->assertSame($ticketsBefore, IssuedTicket::count());
        $this->assertDatabaseHas('ticket_requests', [
            'id' => $ticketRequest->id,
            'status' => 'pending',
            'result_issued_ticket_id' => null,
        ]);
    }

    public function test_process_reissue_rejects_unrelated_inactive_fare(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithPassengerAndIssuedTicket();
        $ticketRequest = $this->createPendingRequest($booking, $passenger, 're_issue', $issuedTicket->id);

        $response = $this->putJson(
            route('ticket-requests.process-reissue', $ticketRequest->id),
            $this->reIssuePayload($this->deps['inactiveFare'])
        );

        $response->assertStatus(400)->assertJson([
            'message' => 'The selected ticket is inactive and cannot be re-issued.',
        ]);

        $this->assertSame(0, ReIssuedTicket::count());
        $this->assertDatabaseHas('ticket_requests', [
            'id' => $ticketRequest->id,
            'status' => 'pending',
        ]);
    }

    public function test_process_reissue_allows_inactive_source_fare(): void
    {
        [$booking, $passenger, $issuedTicket] = $this->createBookingWithPassengerAndIssuedTicket();
        $sourceFare = $issuedTicket->ticketFare;
        $sourceFare->update(['is_active' => false]);

        $ticketRequest = $this->createPendingRequest($booking, $passenger, 're_issue', $issuedTicket->id);

        $response = $this->putJson(
            route('ticket-requests.process-reissue', $ticketRequest->id),
            $this->reIssuePayload($sourceFare)
        );

        $response->assertOk()->assertJson(['success' => true]);

        $reIssued = ReIssuedTicket::latest('id')->first();
        $this->assertNotNull($reIssued);
        $this->assertSame($sourceFare->id, $reIssued->ticket_fare_id);
    }

    public function test_add_confirmation_disables_inactive_fares_in_dropdown(): void
    {
        $view = file_get_contents(resource_path('views/tickets/add-confirmation.blade.php'));

        $this->assertStringContainsString("f.is_active === false ? ' disabled' : ''", $view);
        $this->assertStringContainsString("'inputTicketFare'", $view);
    }

    public function test_re_issue_confirmation_disables_inactive_fares_in_dropdown(): void
    {
        $view = file_get_contents(resource_path('views/re-issues/confirmation.blade.php'));

        $this->assertStringContainsString("f.is_active === false ? ' disabled' : ''", $view);
    }

    public function test_ticket_fares_options_include_inactive_fares(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson(route('ticket-fares.options'));

        $response->assertOk();

        $fares = collect($response->json());
        $ids = $fares->pluck('id')->all();

        $this->assertContains($this->deps['fare']->id, $ids);
        $this->assertContains($this->deps['inactiveFare']->id, $ids);

        $inactiveEntry = $fares->firstWhere('id', $this->deps['inactiveFare']->id);
        $this->assertFalse((bool) ($inactiveEntry['is_active'] ?? null));
    }
}
