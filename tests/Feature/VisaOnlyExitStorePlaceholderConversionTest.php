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
use App\Models\IssuedTicket;
use App\Models\IssuedTicketLog;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Role;
use App\Models\Route as FlightRoute;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Rules\FlightDateSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The real store-created visa_only placeholder workflow.
 *
 * BookingController::store() (:1601-1611) and addPassenger() (:2600-2610)
 * create one fare-less IssuedTicket row even for visa_only passengers. When
 * service_required is then changed to 'all'/'ticket_only', the handler must
 * recognise that row as a placeholder — NOT as a genuine ticket — and convert
 * it in place rather than taking the historic-snapshot early return.
 */
class VisaOnlyExitStorePlaceholderConversionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private District $district;

    private FingerprintCharge $fpCharge;

    private TicketFare $fare;

    private Package $package;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser();
        $this->district = District::create(['name' => 'D', 'division' => 'Div']);
        $this->fpCharge = FingerprintCharge::create([
            'district_id' => $this->district->id,
            'user_id' => $this->user->id,
            'fingerprint_charge' => 50.00,
        ]);

        CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 1.0]);
        StayDurationLimit::getOrCreate();
        FlightDateGap::getOrCreate();
        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);
        PassengerStatus::firstOrCreate(['name' => 'Processing'], ['color' => '#000000']);

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $this->fare = $this->createTicketFare();
        $this->customer = Customer::create([
            'name' => 'Cust', 'passport_no' => 'T'.substr(uniqid(), -6), 'mobile_no' => '0501',
            'iqama_type' => 'none', 'address' => 'A',
        ]);

        $this->package = Package::create([
            'package_name' => 'Pkg '.uniqid(),
            'ticket_fare_id' => $this->fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 30000.00,
            'offer_price' => 28000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
    }

    private function createUser(): User
    {
        $branch = Branch::create([
            'name' => 'Main Branch', 'address' => 'Addr', 'contacts' => '0123456789',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'MAIN01',
        ]);
        $user = User::create([
            'name' => 'Test User', 'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'is_active' => true, 'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'Super Admin']));

        return $user;
    }

    private function createTicketFare(array $overrides = []): TicketFare
    {
        $suffix = substr(uniqid(), -6);
        $airline = Airline::create(['name' => 'SV'.$suffix, 'code' => 'S'.$suffix]);
        $airlineClass = AirlineClass::create([
            'airline_id' => $airline->id,
            'class_id' => TravelClass::create(['name' => 'Economy'.$suffix])->id,
        ]);
        $route = FlightRoute::create([
            'airline_id' => $airline->id, 'route_type' => 'round', 'flight_type' => 'direct',
            'from_city_id' => CityCode::create(['city_name' => 'Dhaka'.$suffix, 'code' => 'D'.$suffix, 'country' => 'BD'])->id,
            'to_city_id' => CityCode::create(['city_name' => 'Riyadh'.$suffix, 'code' => 'R'.$suffix, 'country' => 'SA'])->id,
            'return_city_id' => CityCode::where('code', 'D'.$suffix)->first()->id, 'additional_gap' => null,
        ]);

        return TicketFare::create(array_merge([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30)->toDateString(),
            'effective_to' => now()->addYear()->toDateString(),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'offer_price' => null,
            'child_fare_percentage' => 70.00,
            'infant_fare_percentage' => 30.00,
            'with_meal' => false,
            'is_active' => true,
            'user_id' => $this->user->id,
        ], $overrides));
    }

    private function storePayload(Package $package, string $service = 'visa_only'): array
    {
        return [
            'customer_id' => $this->customer->id,
            'district_id' => $this->district->id,
            'fingerprint_charge_id' => $this->fpCharge->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $package->id,
            'passengers' => [[
                'first_name' => 'John',
                'last_name' => 'Doe',
                'passport_no' => 'PP'.substr(uniqid(), -8),
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'passport_expiry' => '2030-12-31',
                'mobile_no' => '0501234567',
                'service_required' => $service,
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
        ];
    }

    /** Drives the real BookingController::store() and returns the passenger. */
    private function storeVisaOnlyPassenger(Package $package): Passenger
    {
        $this->actingAs($this->user);
        $this->post(route('bookings.store'), $this->storePayload($package))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return Passenger::latest('id')->firstOrFail();
    }

    private function editServiceRequired(Passenger $passenger, string $service): void
    {
        $this->actingAs($this->user);
        $this->put(route('passengers.update', $passenger->id), [
            'first_name' => $passenger->first_name,
            'last_name' => $passenger->last_name,
            'passport_no' => $passenger->passport_no,
            'date_of_birth' => '1990-01-01',
            'stay_duration' => 14,
            'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
            'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
            'service_required' => $service,
            'address' => 'Addr',
        ])->assertRedirect();
    }

    private function doubleTicketPackage(): Package
    {
        $inbound = $this->createTicketFare();
        $outbound = $this->createTicketFare(['selling_fare' => 24000.00]);

        return Package::create([
            'package_name' => 'Double Pkg '.uniqid(),
            'ticket_fare_id' => $inbound->id,
            'ticket_fare_inbound_id' => $inbound->id,
            'ticket_fare_outbound_id' => $outbound->id,
            'visa_selling_price_id' => $this->package->visaSellingPrice->id,
            'regular_price' => 30000.00,
            'offer_price' => 28000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => true,
        ]);
    }

    /** Confirms the store() placeholder fingerprint documented in the handler. */
    private function assertStorePlaceholderShape(Passenger $passenger): void
    {
        $rows = IssuedTicket::where('passenger_id', $passenger->id)->get();
        $this->assertCount(1, $rows, 'store() creates exactly one placeholder row');
        $row = $rows->first();
        $this->assertNull($row->issue_type);
        $this->assertFalse((bool) $row->outbound_pending);
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->ticket_number);
        $this->assertNull($row->pnr);
        $this->assertNull($row->issued_date);
        $this->assertNull($row->ticket_agent_id);
        $this->assertEquals(0.0, (float) $row->net_fare);
        $this->assertEquals(0.0, (float) $row->selling_fare);
        $this->assertEquals(0.0, (float) $row->offer_price);
        $this->assertSame(0, IssuedTicketLog::where('issued_ticket_id', $row->id)->count());
    }

    public function test_store_placeholder_becomes_regular_row_on_single_ticket_exit(): void
    {
        $passenger = $this->storeVisaOnlyPassenger($this->package);
        $this->assertStorePlaceholderShape($passenger);

        $placeholderId = IssuedTicket::where('passenger_id', $passenger->id)->value('id');

        $this->editServiceRequired($passenger, 'all');

        $rows = IssuedTicket::where('passenger_id', $passenger->id)->get();
        $this->assertCount(1, $rows, 'placeholder must be reused, never duplicated');
        $row = $rows->first();
        $this->assertSame($placeholderId, $row->id, 'the SAME store-created row is converted in place');
        $this->assertNull($row->issue_type, 'single-ticket Regular row carries no issue_type');
        $this->assertTrue((bool) $row->outbound_pending, 'Regular row flagged outbound_pending');
        $this->assertSame($this->fare->id, $row->ticket_fare_id, 'correct fare FK assigned');
        $this->assertEqualsWithDelta(28000.00, (float) $row->selling_fare, 0.001, 'historic snapshot at anchor');
        $this->assertEquals(0.0, (float) $row->offer_price);
        $this->assertSame('pending', $row->status, 'status untouched');

        // Protected issuance fields remain untouched.
        $this->assertNull($row->ticket_number);
        $this->assertNull($row->pnr);
        $this->assertNull($row->issued_date);
        $this->assertNull($row->ticket_agent_id);
        $this->assertEquals(0.0, (float) $row->net_fare);
        $this->assertNull($row->baggage_inbound);
        $this->assertNull($row->baggage_outbound);

        $fresh = $passenger->fresh();
        $this->assertSame($this->fare->id, $fresh->ticket_fare_id, 'base FK assigned from package');
        $this->assertNull($fresh->ticket_fare_inbound_id);
        $this->assertNull($fresh->ticket_fare_outbound_id);
        $this->assertEqualsWithDelta(31500.00, (float) $fresh->package_value, 0.001, 'fare 28000 + visa 2000 + service 1500');
    }

    public function test_store_placeholder_becomes_regular_and_pending_outbound_on_double_ticket_exit(): void
    {
        $package = $this->doubleTicketPackage();
        $passenger = $this->storeVisaOnlyPassenger($package);
        $this->assertStorePlaceholderShape($passenger);

        $placeholderId = IssuedTicket::where('passenger_id', $passenger->id)->value('id');

        $this->editServiceRequired($passenger, 'all');

        $rows = IssuedTicket::where('passenger_id', $passenger->id)->orderBy('id')->get();
        $this->assertCount(2, $rows, 'exactly one Regular + one companion');

        $regular = $rows->firstWhere(fn ($r) => is_null($r->issue_type));
        $companion = $rows->firstWhere(fn ($r) => $r->issue_type === 'pending_outbound');
        $this->assertNotNull($regular, 'Regular row must exist');
        $this->assertNotNull($companion, 'pending_outbound companion must exist');
        $this->assertSame($placeholderId, $regular->id, 'placeholder reused as the Regular row');

        $this->assertTrue((bool) $regular->outbound_pending, 'flag lives on the Regular row only');
        $this->assertFalse((bool) $companion->outbound_pending, 'companion must not be flagged');
        $this->assertNull($regular->ticket_fare_id, 'double-ticket Regular row carries no fare source');
        $this->assertNull($companion->ticket_fare_id, 'companion carries no fare source');
        $this->assertEqualsWithDelta(28000.00, (float) $regular->selling_fare, 0.001, 'inbound leg snapshot');
        $this->assertEqualsWithDelta(24000.00, (float) $companion->selling_fare, 0.001, 'outbound leg snapshot');
        $this->assertSame('pending', $regular->status);
        $this->assertSame('pending', $companion->status);

        $fresh = $passenger->fresh();
        $this->assertSame($package->ticket_fare_inbound_id, $fresh->ticket_fare_inbound_id);
        $this->assertSame($package->ticket_fare_outbound_id, $fresh->ticket_fare_outbound_id);
        $this->assertNull($fresh->ticket_fare_id, 'no base fare FK for double ticket');
        $this->assertEqualsWithDelta(55500.00, (float) $fresh->package_value, 0.001, '28000 + 24000 + 2000 + 1500');
    }

    public function test_store_placeholder_conversion_is_idempotent_across_reruns(): void
    {
        $passenger = $this->storeVisaOnlyPassenger($this->package);
        $placeholderId = IssuedTicket::where('passenger_id', $passenger->id)->value('id');

        $this->editServiceRequired($passenger, 'all');
        $this->editServiceRequired($passenger, 'ticket_only');
        $this->editServiceRequired($passenger, 'all');

        $rows = IssuedTicket::where('passenger_id', $passenger->id)->get();
        $this->assertCount(1, $rows, 'reruns must not create duplicate rows');
        $this->assertSame($placeholderId, $rows->first()->id, 'same row throughout');
        $this->assertTrue((bool) $rows->first()->outbound_pending);
        $this->assertEqualsWithDelta(31500.00, (float) $passenger->fresh()->package_value, 0.001);
    }

    public function test_store_placeholder_double_ticket_conversion_is_idempotent(): void
    {
        $package = $this->doubleTicketPackage();
        $passenger = $this->storeVisaOnlyPassenger($package);

        $this->editServiceRequired($passenger, 'all');
        $this->editServiceRequired($passenger, 'ticket_only');
        $this->editServiceRequired($passenger, 'all');

        $rows = IssuedTicket::where('passenger_id', $passenger->id)->get();
        $this->assertCount(2, $rows, 'reruns must not duplicate the Regular or companion row');
        $this->assertSame(1, $rows->where('issue_type', null)->count(), 'exactly one Regular row');
        $this->assertSame(1, $rows->where('issue_type', 'pending_outbound')->count(), 'exactly one companion');
    }

    public function test_genuine_issued_ticket_is_preserved_and_not_converted(): void
    {
        $passenger = $this->storeVisaOnlyPassenger($this->package);

        // Turn the placeholder into a GENUINE issued ticket the way the app does.
        $ticket = IssuedTicket::where('passenger_id', $passenger->id)->first();
        $ticket->update([
            'issue_type' => 'regular',
            'status' => 'issued',
            'ticket_number' => 'TK-7001',
            'pnr' => 'PNR7001',
            'issued_date' => now()->subDay()->toDateString(),
            'net_fare' => 25000.00,
        ]);
        $ticket->logAction('issued', [], $ticket->toArray());

        $this->editServiceRequired($passenger, 'all');

        $rows = IssuedTicket::where('passenger_id', $passenger->id)->get();
        $this->assertCount(1, $rows, 'genuine ticket must never be duplicated');
        $row = $rows->first();
        $this->assertSame('issued', $row->status, 'protected status untouched');
        $this->assertSame('TK-7001', $row->ticket_number, 'protected ticket_number untouched');
        $this->assertSame('PNR7001', $row->pnr, 'protected pnr untouched');
        $this->assertEquals(25000.00, (float) $row->net_fare, 'protected net_fare untouched');
        $this->assertSame(1, $row->logs()->count(), 'no new log rows written by the handler');
    }

    public function test_voided_ticket_is_not_mistaken_for_a_placeholder(): void
    {
        $passenger = $this->storeVisaOnlyPassenger($this->package);

        // Simulate a void: status back to 'pending', but the void log and the
        // restored issuance fields still prove it is a genuine row.
        $ticket = IssuedTicket::where('passenger_id', $passenger->id)->first();
        $ticket->update([
            'issue_type' => 'regular',
            'status' => 'issued',
            'ticket_number' => 'TK-8001',
            'issued_date' => now()->subDay()->toDateString(),
            'net_fare' => 25000.00,
        ]);
        $ticket->logAction('issued', [], $ticket->toArray());
        $ticket->update(['status' => 'pending']);
        $ticket->logAction('void', $ticket->toArray(), $ticket->toArray());

        $this->editServiceRequired($passenger, 'all');

        $rows = IssuedTicket::where('passenger_id', $passenger->id)->get();
        $this->assertCount(1, $rows, 'voided ticket must not be duplicated');
        $row = $rows->first();
        $this->assertSame('TK-8001', $row->ticket_number, 'voided row protected fields survive');
        $this->assertSame(2, $row->logs()->count(), 'handler wrote no new logs');
        $this->assertFalse((bool) $row->outbound_pending, 'voided row flag not forced to true');
    }

    public function test_zero_ticket_passenger_still_gets_a_fresh_regular_row(): void
    {
        $passenger = $this->storeVisaOnlyPassenger($this->package);
        IssuedTicket::where('passenger_id', $passenger->id)->forceDelete();

        $this->editServiceRequired($passenger, 'all');

        $rows = IssuedTicket::where('passenger_id', $passenger->id)->get();
        $this->assertCount(1, $rows, 'no-row case still creates a Regular row');
        $row = $rows->first();
        $this->assertNull($row->issue_type);
        $this->assertTrue((bool) $row->outbound_pending);
        $this->assertSame($this->fare->id, $row->ticket_fare_id);
        $this->assertEqualsWithDelta(28000.00, (float) $row->selling_fare, 0.001);
    }

    public function test_add_passenger_placeholder_is_converted_the_same_way(): void
    {
        $this->actingAs($this->user);
        $this->post(route('bookings.store'), $this->storePayload($this->package))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $booking = Booking::latest('id')->firstOrFail();

        $this->post(route('bookings.passengers.store', $booking->id), [
            'first_name' => 'Jane',
            'last_name' => 'Roe',
            'passport_no' => 'PP'.substr(uniqid(), -8),
            'date_of_birth' => '1992-02-02',
            'gender' => 'female',
            'passport_expiry' => '2031-12-31',
            'mobile_no' => '0501111111',
            'service_required' => 'visa_only',
            'stay_duration' => 14,
            'flight_date_from' => FlightDateSlot::validPairForTesting()[0],
            'flight_date_to' => FlightDateSlot::validPairForTesting()[1],
            'address' => 'Addr',
        ])->assertOk();

        $passenger = Passenger::latest('id')->first();
        $this->assertStorePlaceholderShape($passenger);
        $placeholderId = IssuedTicket::where('passenger_id', $passenger->id)->value('id');

        $this->editServiceRequired($passenger, 'all');

        $rows = IssuedTicket::where('passenger_id', $passenger->id)->get();
        $this->assertCount(1, $rows, 'addPassenger placeholder reused, never duplicated');
        $this->assertSame($placeholderId, $rows->first()->id, 'same row converted in place');
        $this->assertTrue((bool) $rows->first()->outbound_pending);
        $this->assertSame($this->fare->id, $rows->first()->ticket_fare_id);
    }
}
