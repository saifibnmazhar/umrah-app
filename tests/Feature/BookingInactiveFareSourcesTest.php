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
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Rules\FlightDateSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingInactiveFareSourcesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
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

        return compact('district', 'customer', 'package', 'fpCharge', 'fare', 'airline', 'airlineClass', 'route');
    }

    public function test_inactive_fare_referenced_by_issued_ticket_appears_in_ticket_fares_list(): void
    {
        $deps = $this->createPrerequisites();
        $this->actingAs($this->user);

        $this->post(route('bookings.store'), [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $deps['package']->id,
            'passengers' => [[
                'first_name' => 'John',
                'last_name' => 'Doe',
                'passport_no' => 'PASS12345',
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

        $passenger = Passenger::first();
        $issuedTicket = IssuedTicket::where('passenger_id', $passenger->id)
            ->whereNull('issue_type')
            ->first();
        $this->assertNotNull($issuedTicket);

        $deps['fare']->update(['is_active' => false]);
        $passenger->update(['ticket_fare_id' => null]);

        $response = $this->get(route('bookings.show', $passenger->booking_id));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString(
            '"id":'.$deps['fare']->id,
            $html,
            'Inactive fare referenced by IssuedTicket should appear in ticketFaresList'
        );
    }

    public function test_inactive_fare_referenced_by_passenger_inbound_appears_in_ticket_fares_list(): void
    {
        $deps = $this->createPrerequisites();
        $this->actingAs($this->user);

        $inboundFare = TicketFare::create([
            'airline_id' => $deps['airline']->id,
            'airline_classes_id' => $deps['airlineClass']->id,
            'route_id' => $deps['route']->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 22000.00,
            'selling_fare' => 25000.00,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $this->post(route('bookings.store'), [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $deps['package']->id,
            'passengers' => [[
                'first_name' => 'John',
                'last_name' => 'Doe',
                'passport_no' => 'PASS67890',
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

        $passenger = Passenger::first();
        $passenger->update(['ticket_fare_inbound_id' => $inboundFare->id]);
        $inboundFare->update(['is_active' => false]);

        $response = $this->get(route('bookings.show', $passenger->booking_id));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString(
            '"id":'.$inboundFare->id,
            $html,
            'Inactive fare referenced by Passenger.ticket_fare_inbound_id should appear in ticketFaresList'
        );
    }

    public function test_inactive_fare_referenced_by_passenger_outbound_appears_in_ticket_fares_list(): void
    {
        $deps = $this->createPrerequisites();
        $this->actingAs($this->user);

        $outboundFare = TicketFare::create([
            'airline_id' => $deps['airline']->id,
            'airline_classes_id' => $deps['airlineClass']->id,
            'route_id' => $deps['route']->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 23000.00,
            'selling_fare' => 26000.00,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $this->post(route('bookings.store'), [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $deps['package']->id,
            'passengers' => [[
                'first_name' => 'John',
                'last_name' => 'Doe',
                'passport_no' => 'PASS99999',
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

        $passenger = Passenger::first();
        $passenger->update(['ticket_fare_outbound_id' => $outboundFare->id]);
        $outboundFare->update(['is_active' => false]);

        $response = $this->get(route('bookings.show', $passenger->booking_id));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString(
            '"id":'.$outboundFare->id,
            $html,
            'Inactive fare referenced by Passenger.ticket_fare_outbound_id should appear in ticketFaresList'
        );
    }

    public function test_edit_pending_outbound_returns_pending_outbound_ticket(): void
    {
        $deps = $this->createPrerequisites();
        $this->actingAs($this->user);

        $outboundFare = TicketFare::create([
            'airline_id' => $deps['airline']->id,
            'airline_classes_id' => $deps['airlineClass']->id,
            'route_id' => $deps['route']->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 23000.00,
            'selling_fare' => 26000.00,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $ticketAgent = TicketAgent::create(['name' => 'Agent1', 'phone' => '0500', 'address' => 'Riyadh', 'contacts' => '0500']);

        $this->post(route('bookings.store'), [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $deps['package']->id,
            'passengers' => [[
                'first_name' => 'John',
                'last_name' => 'Doe',
                'passport_no' => 'PASS_OUT',
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

        $passenger = Passenger::first();
        $issuedTicket = IssuedTicket::where('passenger_id', $passenger->id)
            ->whereNull('issue_type')
            ->first();
        $this->assertNotNull($issuedTicket);

        $issueResponse = $this->postJson(route('bookings.passengers.ticket-issue', [
            'booking' => $passenger->booking_id,
            'passenger' => $passenger->id,
        ]), [
            'issued_ticket_id' => $issuedTicket->id,
            'ticket_number' => 'TKT001',
            'pnr' => 'PNR001',
            'ticket_fare_id' => $deps['fare']->id,
            'ticket_agent_id' => $ticketAgent->id,
            'issued_date' => now()->toDateString(),
            'outbound_pending' => true,
        ]);

        $issueResponse->assertOk()->assertJson(['success' => true]);

        $pendingOutbound = IssuedTicket::where('passenger_id', $passenger->id)
            ->where('issue_type', 'pending_outbound')
            ->first();
        $this->assertNotNull($pendingOutbound);
        $this->assertNull($pendingOutbound->ticket_fare_id);

        $editResponse = $this->putJson(route('bookings.passengers.ticket-edit', [
            'booking' => $passenger->booking_id,
            'passenger' => $passenger->id,
        ]), [
            'issued_ticket_id' => $pendingOutbound->id,
            'ticket_fare_id' => $outboundFare->id,
            'selling_fare' => 26000.00,
            'net_fare' => 23000.00,
        ]);

        $editResponse->assertOk()->assertJsonStructure([
            'success',
            'issued_ticket',
            'pending_outbound_ticket',
        ]);

        $editResponse->assertJsonPath('pending_outbound_ticket.ticket_fare_id', $outboundFare->id);
    }

    public function test_filtered_ticket_options_requires_all_three_fields_in_outbound_mode(): void
    {
        $deps = $this->createPrerequisites();
        $this->actingAs($this->user);

        $this->post(route('bookings.store'), [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $deps['package']->id,
            'passengers' => [[
                'first_name' => 'John',
                'last_name' => 'Doe',
                'passport_no' => 'PASS_FLT1',
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

        $response = $this->get(route('bookings.index'));
        $html = $response->getContent();

        $this->assertStringContainsString(
            'if (isOutbound',
            $html,
            'filteredTicketOptions must have outbound mode guard'
        );
        $this->assertStringNotContainsString(
            'if (rt && ft) {',
            $html,
            'filteredTicketOptions must NOT require both route_type and flight_type together'
        );
    }

    public function test_filtered_ticket_options_filters_route_and_flight_independently(): void
    {
        $this->actingAs($this->user);
        $response = $this->get(route('bookings.index'));
        $html = $response->getContent();

        $this->assertStringContainsString(
            'if (rt) {',
            $html,
            'filteredTicketOptions must filter by route_type independently'
        );
        $this->assertStringContainsString(
            'if (ft) {',
            $html,
            'filteredTicketOptions must filter by flight_type independently'
        );
        $this->assertStringNotContainsString(
            'if (rt && ft) {',
            $html,
            'filteredTicketOptions must NOT require both route_type and flight_type together'
        );
    }
}
