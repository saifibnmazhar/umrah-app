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
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Payment;
use App\Models\RefundedTicket;
use App\Models\ReIssuedTicket;
use App\Models\ReIssueRefundReason;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketAgent;
use App\Models\TicketFare;
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatementReportTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function makeUser(string $role = 'Super Admin'): User
    {
        $user = User::create([
            'name' => $role.' User '.uniqid(),
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

        return $user;
    }

    private function baseDeps(User $user): array
    {
        $district = District::create(['name' => 'D '.uniqid(), 'division' => 'Div']);
        $cityFrom = CityCode::create(['city_name' => 'Dhaka', 'code' => 'D'.substr(uniqid(), -5), 'country' => 'Bangladesh']);
        $cityTo = CityCode::create(['city_name' => 'Riyadh', 'code' => 'R'.substr(uniqid(), -5), 'country' => 'Saudi']);
        $airline = Airline::create(['name' => 'SV '.uniqid(), 'code' => substr(uniqid(), -2)]);
        $travelClass = TravelClass::create(['name' => 'Eco '.uniqid()]);
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
        StayDurationLimit::getOrCreate();
        $currencyRate = CurrencyRate::create(['user_id' => $user->id, 'rate' => 28.0]);
        $visaPrice = VisaSellingPrice::create(['user_id' => $user->id, 'selling_price' => 2000.00]);
        $fareRegular = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(60),
            'effective_to' => now()->addDays(60),
            'net_fare' => 2700.00,
            'selling_fare' => 2820.00,
            'offer_price' => null,
            'child_fare_percentage' => 50.00,
            'infant_fare_percentage' => 20.00,
            'with_meal' => true,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $fareOffer = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'offer',
            'effective_from' => now()->subDays(60),
            'effective_to' => now()->addDays(60),
            'net_fare' => 2500.00,
            'selling_fare' => 2900.00,
            'offer_price' => 2600.00,
            'child_fare_percentage' => 50.00,
            'infant_fare_percentage' => 20.00,
            'with_meal' => true,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $package = Package::create([
            'package_name' => 'Pkg '.uniqid(),
            'ticket_fare_id' => $fareRegular->id,
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
        $branch = Branch::create([
            'name' => 'Br '.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'B'.substr(uniqid(), -6),
        ]);
        TransactionType::firstOrCreate(['name' => 'Initial Payment'], ['type' => 'debit']);
        $passengerStatusId = PassengerStatus::firstOrCreate(['name' => 'Processing'])->id;
        $agentA = TicketAgent::create(['name' => 'FLYBURJ', 'address' => 'A', 'contacts' => '1']);
        $agentB = TicketAgent::create(['name' => 'AGENTB', 'address' => 'B', 'contacts' => '2']);

        return compact('district', 'fareRegular', 'fareOffer', 'package', 'fingerprintCharge', 'branch', 'passengerStatusId', 'agentA', 'agentB', 'currencyRate', 'airline', 'airlineClass', 'flightDateGap');
    }

    private function makeBooking(User $user, array $deps, ?string $invoiceId = null, ?string $customerName = null): Booking
    {
        $this->seq++;
        $customer = Customer::create([
            'name' => $customerName ?? 'Customer '.$this->seq,
            'passport_no' => 'CP'.uniqid(),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $deps['branch']->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'booking_branch_id' => $deps['branch']->id,
            'invoice_id' => $invoiceId ?? 'INV-'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT),
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
        ]);
        Invoice::create([
            'booking_id' => $booking->id,
            'branch_id' => $deps['branch']->id,
            'user_id' => $user->id,
            'total_amount' => 50000.00,
            'paid_amount' => 0,
            'balance' => 50000.00,
            'status' => 'pending',
        ]);

        return $booking->fresh();
    }

    private function makePassenger(User $user, array $deps, Booking $booking, ?string $passport = null, ?string $firstName = null): Passenger
    {
        return Passenger::create([
            'booking_id' => $booking->id,
            'passenger_status_id' => $deps['passengerStatusId'],
            'first_name' => $firstName ?? 'Pax'.uniqid(),
            'last_name' => 'Last',
            'passport_no' => $passport ?? 'PP'.uniqid(),
            'mobile_no' => '0501234567',
            'date_of_birth' => '1990-01-01',
            'passenger_type' => 'adult',
            'passport_expiry' => '2030-12-31',
            'stay_duration' => 14,
            'service_required' => 'all',
            'flight_date_from' => '2026-04-01',
            'flight_date_to' => '2026-04-15',
            'ticket_status' => 'pending',
            'visa_status' => 'pending',
            'address' => 'Addr',
            'ticket_fare_id' => $deps['fareRegular']->id,
            'package_value' => 25000.00,
        ]);
    }

    private function makeTicket(User $user, array $deps, Booking $booking, Passenger $passenger, array $over = []): IssuedTicket
    {
        return IssuedTicket::create(array_merge([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_agent_id' => $deps['agentA']->id,
            'ticket_fare_id' => $deps['fareRegular']->id,
            'ticket_number' => '779-'.uniqid(),
            'pnr' => 'PNR'.substr(uniqid(), -5),
            'issued_date' => '2026-03-05',
            'inbound_date' => '2026-04-10',
            'outbound_date' => '2026-04-25',
            'selling_fare' => 2820.00,
            'net_fare' => 2700.00,
            'offer_price' => null,
            'issue_type' => 'regular',
            'status' => 'issued',
        ], $over));
    }

    private function makePayment(User $user, array $deps, Booking $booking, array $over = []): Payment
    {
        $payment = Payment::create(array_merge([
            'booking_id' => $booking->id,
            'branch_id' => $deps['branch']->id,
            'user_id' => $user->id,
            'ticket_agent_id' => $deps['agentA']->id,
            'payment_date' => '2026-03-06',
            'payment_method' => 'cash',
            'amount' => 1000.00,
            'bdt_amount' => 28000.00,
        ], $over));
        Voucher::create([
            'voucher_id' => $over['voucher_id'] ?? 'VCH-'.uniqid(),
            'booking_id' => $booking->id,
            'payment_id' => $payment->id,
            'branch_id' => $deps['branch']->id,
            'user_id' => $user->id,
            'transaction_type_id' => TransactionType::where('name', 'Initial Payment')->first()->id,
            'payment_date' => $over['payment_date'] ?? '2026-03-06',
            'payment_method' => 'cash',
            'amount' => $over['amount'] ?? 1000.00,
            'bdt_amount' => 28000.00,
        ]);

        return $payment->fresh();
    }

    private function apiParams(array $over = []): array
    {
        return array_merge([
            'date_type' => 'issue',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ], $over);
    }

    public function test_api_returns_all_four_categories(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);

        $booking = $this->makeBooking($user, $deps, 'INV-04499');
        $passenger = $this->makePassenger($user, $deps, $booking);
        $ticket = $this->makeTicket($user, $deps, $booking, $passenger, ['ticket_number' => '779-9461466422']);

        ReIssuedTicket::create([
            'user_id' => $user->id,
            'ticket_agent_id' => $deps['agentA']->id,
            'ticket_fare_id' => $deps['fareRegular']->id,
            'issued_ticket_id' => $ticket->id,
            'ticket_number' => '779-REISSUE1',
            'pnr' => $ticket->pnr,
            're_issue_date' => '2026-03-10',
            'inbound_date' => '2026-04-10',
            'outbound_date' => '2026-04-25',
            'selling_fare' => 2820.00,
            'net_fare' => 2700.00,
            'service_charge' => 100.00,
            'total_cost' => 500.00,
        ]);
        RefundedTicket::create([
            'user_id' => $user->id,
            'ticket_agent_id' => $deps['agentA']->id,
            'ticket_fare_id' => $deps['fareRegular']->id,
            'issued_ticket_id' => $ticket->id,
            'ticket_number' => '779-REFUND1',
            'pnr' => $ticket->pnr,
            'refund_date' => '2026-03-12',
            'inbound_date' => '2026-04-10',
            'outbound_date' => '2026-04-25',
            'selling_fare' => 2820.00,
            'net_fare' => 2700.00,
            'iata_refunded_amount' => 2500.00,
            'refund_to_customer' => 2600.00,
            'service_charge' => 50.00,
        ]);
        $this->makePayment($user, $deps, $booking, ['payment_date' => '2026-03-15', 'amount' => 1000.00]);

        $response = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams()));

        $response->assertOk();
        $categories = collect($response->json('rows'))->pluck('category')->sort()->values()->all();
        $this->assertEqualsCanonicalizing(['Ticket', 'Re-issue', 'Refund', 'Payment'], $categories);

        $row = collect($response->json('rows'))->firstWhere('category', 'Ticket');
        foreach (['date', 'category', 'ticket_no', 'reference_id', 'pax_name', 'customer_name', 'pnr', 'passport', 'sector', 'carrier_class_pay', 'flight_date', 'return_date', 'customer_amount', 'agent_fare', 'markup', 'customer_refund', 'iata_refund', 'payment_to_iata', 'balance', 'agent_name', 'staff_name'] as $key) {
            $this->assertArrayHasKey($key, $row, "missing payload key: {$key}");
        }
    }

    public function test_running_balance_paid_minus_payable(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);

        // Opening history before period: ticket net 2700 (delta -2700)
        $oldBooking = $this->makeBooking($user, $deps, 'INV-OLD');
        $oldPassenger = $this->makePassenger($user, $deps, $oldBooking);
        $this->makeTicket($user, $deps, $booking = $oldBooking, $oldPassenger, ['issued_date' => '2026-02-10', 'net_fare' => 2700.00, 'selling_fare' => 2820.00]);

        // Period: ticket (-2700) then payment (+1000)
        $booking = $this->makeBooking($user, $deps, 'INV-NEW');
        $passenger = $this->makePassenger($user, $deps, $booking);
        $this->makeTicket($user, $deps, $booking, $passenger, ['issued_date' => '2026-03-05', 'net_fare' => 2700.00, 'selling_fare' => 2820.00]);
        $this->makePayment($user, $deps, $booking, ['payment_date' => '2026-03-06', 'amount' => 1000.00]);

        $response = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams()));
        $response->assertOk();

        $summary = $response->json('summary');
        // opening = -2700, period deltas = -2700 + 1000 = -1700, closing = -4400
        $this->assertEquals(-2700.00, (float) $summary['opening_balance']);
        $this->assertEquals(-4400.00, (float) $summary['closing_balance']);

        $rows = collect($response->json('rows'));
        $last = $rows->last();
        $this->assertEquals((float) $summary['closing_balance'], (float) $last['balance']);
    }

    public function test_date_type_filters(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $booking = $this->makeBooking($user, $deps);
        $passenger = $this->makePassenger($user, $deps, $booking);
        $this->makeTicket($user, $deps, $booking, $passenger, [
            'issued_date' => '2026-03-05', 'inbound_date' => '2026-05-01', 'outbound_date' => '2026-05-20',
        ]);
        $this->makePayment($user, $deps, $booking, ['payment_date' => '2026-03-06', 'amount' => 500.00]);

        // Flight type window covers inbound date
        $flight = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams([
            'date_type' => 'flight', 'date_from' => '2026-05-01', 'date_to' => '2026-05-31',
        ])));
        $flight->assertOk();
        $this->assertCount(1, $flight->json('rows'));
        $this->assertEquals('Ticket', $flight->json('rows.0.category'));

        // Return type window covers outbound date
        $ret = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams([
            'date_type' => 'return', 'date_from' => '2026-05-01', 'date_to' => '2026-05-31',
        ])));
        $ret->assertOk();
        $this->assertCount(1, $ret->json('rows'));

        // Payment rows absent under flight/return
        foreach ([$flight, $ret] as $r) {
            $this->assertNotContains('Payment', collect($r->json('rows'))->pluck('category')->all());
        }

        // NULL inbound excluded under flight type
        $booking2 = $this->makeBooking($user, $deps);
        $p2 = $this->makePassenger($user, $deps, $booking2);
        $this->makeTicket($user, $deps, $booking2, $p2, ['issued_date' => '2026-05-02', 'inbound_date' => null, 'outbound_date' => null]);
        $flight2 = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams([
            'date_type' => 'flight', 'date_from' => '2026-05-01', 'date_to' => '2026-05-31',
        ])));
        $flight2->assertOk();
        $this->assertCount(1, $flight2->json('rows'));
    }

    public function test_date_range_cap(): void
    {
        $user = $this->makeUser('Super Admin');
        $response = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams([
            'date_from' => '2026-01-01', 'date_to' => '2026-05-01',
        ])));
        $response->assertStatus(422);
    }

    public function test_search_filter(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $booking = $this->makeBooking($user, $deps, 'INV-SEARCH01');
        $passenger = $this->makePassenger($user, $deps, $booking, 'A1234567', 'Md Kamal');
        $this->makeTicket($user, $deps, $booking, $passenger, ['ticket_number' => '779-9461466422', 'pnr' => 'JJUKMH']);

        foreach (['779-9461466422', 'JJUKMH', 'A1234567', 'INV-SEARCH01', 'Md Kamal'] as $term) {
            $r = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams(['search' => $term])));
            $r->assertOk();
            $this->assertNotEmpty($r->json('rows'), "search term {$term} returned no rows");
        }

        // Voucher search
        $payment = Payment::create([
            'booking_id' => $booking->id, 'branch_id' => $deps['branch']->id, 'user_id' => $user->id,
            'ticket_agent_id' => $deps['agentA']->id, 'payment_date' => '2026-03-07',
            'payment_method' => 'cash', 'amount' => 700.00, 'bdt_amount' => 19600.00,
        ]);
        Voucher::create([
            'voucher_id' => 'VCH-SEARCH99', 'booking_id' => $booking->id, 'payment_id' => $payment->id,
            'branch_id' => $deps['branch']->id, 'user_id' => $user->id,
            'transaction_type_id' => TransactionType::where('name', 'Initial Payment')->first()->id,
            'payment_date' => '2026-03-07', 'payment_method' => 'cash', 'amount' => 700.00, 'bdt_amount' => 19600.00,
        ]);
        $r = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams(['search' => 'VCH-SEARCH99'])));
        $r->assertOk();
        $this->assertNotEmpty(collect($r->json('rows'))->where('category', 'Payment')->all());
    }

    public function test_agent_grouped_sections(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);

        $b1 = $this->makeBooking($user, $deps, 'INV-A1');
        $p1 = $this->makePassenger($user, $deps, $b1);
        $this->makeTicket($user, $deps, $b1, $p1, ['ticket_agent_id' => $deps['agentA']->id, 'net_fare' => 2700.00, 'selling_fare' => 2820.00]);

        $b2 = $this->makeBooking($user, $deps, 'INV-B1');
        $p2 = $this->makePassenger($user, $deps, $b2);
        $this->makeTicket($user, $deps, $b2, $p2, ['ticket_agent_id' => $deps['agentB']->id, 'net_fare' => 1000.00, 'selling_fare' => 1100.00]);

        $all = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams()));
        $all->assertOk();
        $this->assertArrayHasKey('sections', $all->json());
        $this->assertCount(2, $all->json('sections'));
        // per-agent isolation: agentB balance should be -1000, not mixed with A
        $sectionB = collect($all->json('sections'))->firstWhere('agent_name', 'AGENTB');
        $this->assertEquals(-1000.00, (float) $sectionB['closing_balance']);

        $single = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams(['agent_id' => $deps['agentA']->id])));
        $single->assertOk();
        $this->assertCount(1, $single->json('rows'));
        $this->assertEquals('FLYBURJ', $single->json('rows.0.agent_name'));
    }

    public function test_role_middleware(): void
    {
        $staff = $this->makeUser('Ticket Staff');
        $this->actingAs($staff)->get('/reports/statement')->assertForbidden();
        $this->actingAs($staff)->getJson('/api/reports/statement?'.http_build_query($this->apiParams()))->assertForbidden();

        // Guest is blocked by the auth group (redirect to login or 403 depending on handler).
        $guestStatus = $this->get('/reports/statement')->status();
        $this->assertContains($guestStatus, [302, 403]);
    }

    public function test_status_and_soft_delete_filters(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $booking = $this->makeBooking($user, $deps);
        $passenger = $this->makePassenger($user, $deps, $booking);
        $pending = $this->makeTicket($user, $deps, $booking, $passenger, ['status' => 'pending', 'ticket_number' => '779-PENDING']);
        $deleted = $this->makeTicket($user, $deps, $booking, $passenger, ['ticket_number' => '779-DELETED']);
        $deleted->delete();
        $kept = $this->makeTicket($user, $deps, $booking, $passenger, ['ticket_number' => '779-KEPT']);

        $r = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams()));
        $r->assertOk();
        $nos = collect($r->json('rows'))->pluck('ticket_no')->all();
        $this->assertNotContains('779-PENDING', $nos);
        $this->assertNotContains('779-DELETED', $nos);
        $this->assertContains('779-KEPT', $nos);
    }

    public function test_created_at_fallback(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $booking = $this->makeBooking($user, $deps);
        $passenger = $this->makePassenger($user, $deps, $booking);
        $ticket = $this->makeTicket($user, $deps, $booking, $passenger);
        $reissue = ReIssuedTicket::create([
            'user_id' => $user->id, 'ticket_agent_id' => $deps['agentA']->id,
            'ticket_fare_id' => $deps['fareRegular']->id, 'issued_ticket_id' => $ticket->id,
            'ticket_number' => '779-NODATE', 'pnr' => 'XX1',
            're_issue_date' => null, 'selling_fare' => 2820.00, 'net_fare' => 2700.00,
            'service_charge' => 20.00, 'total_cost' => 200.00,
        ]);
        // created_at is not fillable — force it via query builder for the fallback path.
        ReIssuedTicket::where('id', $reissue->id)->update([
            'created_at' => '2026-03-11 10:00:00', 'updated_at' => '2026-03-11 10:00:00',
        ]);

        $r = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams()));
        $r->assertOk();
        $this->assertContains('779-NODATE', collect($r->json('rows'))->pluck('ticket_no')->all());
    }

    public function test_offer_aware_pricing(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $booking = $this->makeBooking($user, $deps);
        $passenger = $this->makePassenger($user, $deps, $booking);
        // Offer fare: Pay = offer_price 2600, markup = 2600 - 2500 = 100
        $this->makeTicket($user, $deps, $booking, $passenger, [
            'ticket_fare_id' => $deps['fareOffer']->id,
            'selling_fare' => 2900.00, 'net_fare' => 2500.00, 'offer_price' => 2600.00,
        ]);

        $r = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams()));
        $r->assertOk();
        $row = $r->json('rows.0');
        $this->assertEquals(2600.00, (float) $row['customer_amount']);
        $this->assertEquals(100.00, (float) $row['markup']);
        $this->assertEquals(2600.00, (float) $r->json('summary.total_sale_amount'));
        $this->assertEquals(100.00, (float) $r->json('summary.total_markup'));
    }

    public function test_reissue_row_joins_via_issued_ticket(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $booking = $this->makeBooking($user, $deps, 'INV-REJOIN', 'Join Customer');
        $passenger = $this->makePassenger($user, $deps, $booking, 'JOINPP1', 'Join Pax');
        $ticket = $this->makeTicket($user, $deps, $booking, $passenger, ['pnr' => 'JOINPNR', 'ticket_number' => '779-JOIN']);
        ReIssuedTicket::create([
            'user_id' => $user->id, 'ticket_agent_id' => $deps['agentA']->id,
            'ticket_fare_id' => $deps['fareRegular']->id, 'issued_ticket_id' => $ticket->id,
            'ticket_number' => '779-REJOIN', 'pnr' => 'JOINPNR2',
            're_issue_date' => '2026-03-10', 'selling_fare' => 2820.00, 'net_fare' => 2700.00,
            'service_charge' => 80.00, 'total_cost' => 400.00,
        ]);

        $r = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams()));
        $r->assertOk();
        $row = collect($r->json('rows'))->firstWhere('ticket_no', '779-REJOIN');
        $this->assertNotNull($row);
        $this->assertEquals('INV-REJOIN', $row['reference_id']);
        $this->assertEquals('Join Customer', $row['customer_name']);
        $this->assertStringContainsString('Join Pax', $row['pax_name']);
    }

    public function test_agent_fallback_on_write(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $reason = ReIssueRefundReason::create(['reason_of' => 're-issue', 'name' => 'R '.uniqid()]);

        $booking1 = $this->makeBooking($user, $deps);
        $passenger1 = $this->makePassenger($user, $deps, $booking1);
        $ticket1 = $this->makeTicket($user, $deps, $booking1, $passenger1, ['ticket_agent_id' => $deps['agentA']->id]);

        // Re-issue without explicit agent inherits source ticket's agent
        $reissueResponse = $this->actingAs($user)->postJson("/bookings/{$booking1->id}/passengers/{$passenger1->id}/re-issue", [
            'issued_ticket_id' => $ticket1->id,
            'ticket_number' => '779-FB1',
            're_issue_date' => '2026-03-10',
            'reason_id' => $reason->id,
            're_issue_charge' => 300.00,
        ]);
        $reissueResponse->assertSuccessful();
        $this->assertDatabaseHas('re_issued_tickets', [
            'ticket_number' => '779-FB1',
            'ticket_agent_id' => $deps['agentA']->id,
        ]);

        $booking2 = $this->makeBooking($user, $deps);
        $passenger2 = $this->makePassenger($user, $deps, $booking2);
        $ticket2 = $this->makeTicket($user, $deps, $booking2, $passenger2, ['ticket_agent_id' => $deps['agentA']->id, 'net_fare' => 2700.00]);

        // Refund without explicit agent inherits source ticket's agent
        $refundResponse = $this->actingAs($user)->postJson("/bookings/{$booking2->id}/passengers/{$passenger2->id}/refund", [
            'issued_ticket_id' => $ticket2->id,
            'ticket_number' => '779-FB2',
            'refund_date' => '2026-03-11',
            'reason_id' => $reason->id,
            'iata_refund' => 100.00,
            'customer_refund' => 90.00,
            'service_charge' => 10.00,
        ]);
        $refundResponse->assertSuccessful();
        $this->assertDatabaseHas('refunded_tickets', [
            'ticket_number' => '779-FB2',
            'ticket_agent_id' => $deps['agentA']->id,
        ]);
    }

    public function test_summary_formulas(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $booking = $this->makeBooking($user, $deps, 'INV-SUM');
        $passenger = $this->makePassenger($user, $deps, $booking);
        // Ticket: Pay 2820, net 2700, markup 120
        $ticket = $this->makeTicket($user, $deps, $booking, $passenger, [
            'selling_fare' => 2820.00, 'net_fare' => 2700.00, 'offer_price' => null,
        ]);
        ReIssuedTicket::create([
            'user_id' => $user->id, 'ticket_agent_id' => $deps['agentA']->id,
            'ticket_fare_id' => $deps['fareRegular']->id, 'issued_ticket_id' => $ticket->id,
            'ticket_number' => '779-SUM-RE', 'pnr' => 'SUMPNR',
            're_issue_date' => '2026-03-10', 'selling_fare' => 2820.00, 'net_fare' => 2700.00,
            'service_charge' => 100.00, 'total_cost' => 500.00,
        ]);
        RefundedTicket::create([
            'user_id' => $user->id, 'ticket_agent_id' => $deps['agentA']->id,
            'ticket_fare_id' => $deps['fareRegular']->id, 'issued_ticket_id' => $ticket->id,
            'ticket_number' => '779-SUM-RF', 'pnr' => 'SUMPNR',
            'refund_date' => '2026-03-12', 'selling_fare' => 2820.00, 'net_fare' => 2700.00,
            'iata_refunded_amount' => 2500.00, 'refund_to_customer' => 2600.00, 'service_charge' => 50.00,
        ]);
        $this->makePayment($user, $deps, $booking, ['payment_date' => '2026-03-15', 'amount' => 1000.00]);

        $r = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query($this->apiParams()));
        $r->assertOk();
        $s = $r->json('summary');
        foreach (['opening_balance', 'closing_balance', 'total_tickets', 'total_sale_amount', 'total_customer_refund', 'total_agent_fare', 'total_markup', 'total_agent_refund', 'total_reissue_cost', 'total_paid'] as $key) {
            $this->assertArrayHasKey($key, $s, "missing summary key: {$key}");
        }
        $this->assertEquals(1, $s['total_tickets']);
        $this->assertEquals(2820.00, (float) $s['total_sale_amount']);
        $this->assertEquals(2600.00, (float) $s['total_customer_refund']);
        $this->assertEquals(2700.00, (float) $s['total_agent_fare']);
        $this->assertEquals(120.00 + 100.00 + 50.00, (float) $s['total_markup']);
        $this->assertEquals(2500.00, (float) $s['total_agent_refund']);
        $this->assertEquals(500.00, (float) $s['total_reissue_cost']);
        $this->assertEquals(1000.00, (float) $s['total_paid']);
    }

    public function test_view_renders_two_row_layout(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $agents = TicketAgent::orderBy('name')->get();

        $html = view('reports.statement', ['agents' => $agents])->render();

        $this->assertStringContainsString('Issue Date', $html);
        $this->assertStringContainsString('Category', $html);
        $this->assertStringContainsString('Reference ID', $html);
        foreach (['opening_balance', 'closing_balance', 'total_tickets', 'total_sale_amount', 'total_customer_refund', 'total_agent_fare', 'total_markup', 'total_agent_refund', 'total_reissue_cost', 'total_paid'] as $key) {
            $this->assertStringContainsString($key, $html, "footer missing key: {$key}");
        }
    }

    public function test_view_merges_money_columns_with_rowspan(): void
    {
        $agents = TicketAgent::orderBy('name')->get();

        $html = view('reports.statement', ['agents' => $agents])->render();

        // Money headers are single tall cells with plain labels (no SAR in header).
        foreach (['Customer Amount', 'Agent Fare (Net)', 'MARKUP', 'Customer Refund', 'IATA Refund', 'Payment to IATA', 'Balance Agent'] as $label) {
            $this->assertStringContainsString($label, $html, "missing header: {$label}");
            $this->assertStringNotContainsString("{$label} (SAR)", $html, "header must not include SAR: {$label}");
        }
        $this->assertStringContainsString('rowspan="2"', $html, 'money cells must span both rows');
        $this->assertStringNotContainsString('>SAR</th>', $html, 'no bare SAR header cells');
        $this->assertStringNotContainsString('>SAR</td>', $html, 'no bare SAR body cells');
        // Non-money two-row headers stay.
        foreach (['Category', 'Reference ID', 'Customer Name', 'Passport', 'Carrier | Class | Pay', 'Return Date', 'Ticket Staff'] as $label) {
            $this->assertStringContainsString($label, $html, "missing sub-header: {$label}");
        }
    }
}
