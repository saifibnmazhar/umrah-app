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

class TicketAgentLedgerParityTest extends TestCase
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

        return compact('district', 'fareRegular', 'package', 'fingerprintCharge', 'branch', 'passengerStatusId', 'agentA', 'currencyRate', 'flightDateGap');
    }

    private function makeBooking(User $user, array $deps, ?string $invoiceId = null): Booking
    {
        $this->seq++;
        $customer = Customer::create([
            'name' => 'Customer '.$this->seq,
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

    private function makePassenger(User $user, array $deps, Booking $booking): Passenger
    {
        return Passenger::create([
            'booking_id' => $booking->id,
            'passenger_status_id' => $deps['passengerStatusId'],
            'first_name' => 'Pax'.uniqid(),
            'last_name' => 'Last',
            'passport_no' => 'PP'.uniqid(),
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

    private function seedFullLedger(User $user, array $deps): void
    {
        // Pre-range history (opening): ticket issued Feb, net 2700 → opening −2700.
        $booking = $this->makeBooking($user, $deps, 'INV-OPEN');
        $passenger = $this->makePassenger($user, $deps, $booking);
        $this->makeTicket($user, $deps, $booking, $passenger, ['issued_date' => '2026-02-10']);

        // In-range: ticket (Mar 5, net 2700), re-issue (Mar 10, cost 500),
        // refund (Mar 12, iata 2500), payment (Mar 15, amount 1000).
        $booking2 = $this->makeBooking($user, $deps, 'INV-PERIOD');
        $passenger2 = $this->makePassenger($user, $deps, $booking2);
        $ticket = $this->makeTicket($user, $deps, $booking2, $passenger2);
        ReIssuedTicket::create([
            'user_id' => $user->id, 'ticket_agent_id' => $deps['agentA']->id,
            'ticket_fare_id' => $deps['fareRegular']->id, 'issued_ticket_id' => $ticket->id,
            'ticket_number' => '779-RE', 'pnr' => $ticket->pnr,
            're_issue_date' => '2026-03-10', 'selling_fare' => 2820.00, 'net_fare' => 2700.00,
            'service_charge' => 100.00, 'total_cost' => 500.00,
        ]);
        RefundedTicket::create([
            'user_id' => $user->id, 'ticket_agent_id' => $deps['agentA']->id,
            'ticket_fare_id' => $deps['fareRegular']->id, 'issued_ticket_id' => $ticket->id,
            'ticket_number' => '779-RF', 'pnr' => $ticket->pnr,
            'refund_date' => '2026-03-12', 'selling_fare' => 2820.00, 'net_fare' => 2700.00,
            'iata_refunded_amount' => 2500.00, 'refund_to_customer' => 2600.00, 'service_charge' => 50.00,
        ]);
        $this->makePayment($user, $deps, $booking2, ['payment_date' => '2026-03-15', 'amount' => 1000.00]);
    }

    private function agentRow(array $payload, int $agentId): array
    {
        foreach ($payload['data'] as $row) {
            if ((int) $row['id'] === $agentId) {
                return $row;
            }
        }
        $this->fail('agent missing from ticket-agent payload');

        return [];
    }

    public function test_balance_includes_pre_range_opening(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $booking = $this->makeBooking($user, $deps, 'INV-OPEN');
        $passenger = $this->makePassenger($user, $deps, $booking);
        $this->makeTicket($user, $deps, $booking, $passenger, ['issued_date' => '2026-02-10']);

        $r = $this->actingAs($user)->getJson('/api/reports/ticket-agent?'.http_build_query([
            'date_from' => '2026-03-01', 'date_to' => '2026-03-31', 'agent_id' => $deps['agentA']->id,
        ]));
        $r->assertOk();
        $row = $this->agentRow($r->json(), $deps['agentA']->id);

        $this->assertEquals(-2700.00, (float) $row['due']);
    }

    public function test_balance_matches_statement_closing(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $this->seedFullLedger($user, $deps);

        $range = ['date_from' => '2026-03-01', 'date_to' => '2026-03-31', 'agent_id' => $deps['agentA']->id];
        $agent = $this->actingAs($user)->getJson('/api/reports/ticket-agent?'.http_build_query($range));
        $agent->assertOk();
        $row = $this->agentRow($agent->json(), $deps['agentA']->id);

        $stmt = $this->actingAs($user)->getJson('/api/reports/statement?'.http_build_query(
            ['date_type' => 'issue'] + $range
        ));
        $stmt->assertOk();
        $rows = $stmt->json('rows');
        $this->assertNotEmpty($rows);
        $closing = (float) end($rows)['balance'];

        // −2700 (opening) −2700 (ticket) −500 (re-issue) +2500 (refund) +1000 (payment).
        $this->assertEquals(-2400.00, $closing);
        $this->assertEquals($closing, (float) $row['due']);
        $this->assertEquals(3200.00, (float) $row['payable']);
        $this->assertEquals(3500.00, (float) $row['paid']);
    }

    public function test_refund_and_reissue_enter_balance(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $booking = $this->makeBooking($user, $deps, 'INV-RR');
        $passenger = $this->makePassenger($user, $deps, $booking);
        $ticket = $this->makeTicket($user, $deps, $booking, $passenger);
        ReIssuedTicket::create([
            'user_id' => $user->id, 'ticket_agent_id' => $deps['agentA']->id,
            'ticket_fare_id' => $deps['fareRegular']->id, 'issued_ticket_id' => $ticket->id,
            'ticket_number' => '779-RE2', 'pnr' => $ticket->pnr,
            're_issue_date' => '2026-03-10', 'selling_fare' => 2820.00, 'net_fare' => 2700.00,
            'service_charge' => 100.00, 'total_cost' => 500.00,
        ]);
        RefundedTicket::create([
            'user_id' => $user->id, 'ticket_agent_id' => $deps['agentA']->id,
            'ticket_fare_id' => $deps['fareRegular']->id, 'issued_ticket_id' => $ticket->id,
            'ticket_number' => '779-RF2', 'pnr' => $ticket->pnr,
            'refund_date' => '2026-03-12', 'selling_fare' => 2820.00, 'net_fare' => 2700.00,
            'iata_refunded_amount' => 2500.00, 'refund_to_customer' => 2600.00, 'service_charge' => 50.00,
        ]);

        $r = $this->actingAs($user)->getJson('/api/reports/ticket-agent?'.http_build_query([
            'date_from' => '2026-03-01', 'date_to' => '2026-03-31', 'agent_id' => $deps['agentA']->id,
        ]));
        $r->assertOk();
        $row = $this->agentRow($r->json(), $deps['agentA']->id);

        $this->assertEquals(-700.00, (float) $row['due']);
        $this->assertEquals(3200.00, (float) $row['payable']);
        $this->assertEquals(2500.00, (float) $row['paid']);
        $this->assertEquals(2500.00, (float) $row['totalRefundAmount']);
        $this->assertEquals(500.00, (float) $row['totalReissueCost']);
    }

    public function test_counts_are_date_filtered(): void
    {
        $user = $this->makeUser('Super Admin');
        $deps = $this->baseDeps($user);
        $booking = $this->makeBooking($user, $deps, 'INV-OLD');
        $passenger = $this->makePassenger($user, $deps, $booking);
        $this->makeTicket($user, $deps, $booking, $passenger, [
            'issued_date' => '2026-02-10', 'status' => 'refunded',
        ]);

        $r = $this->actingAs($user)->getJson('/api/reports/ticket-agent?'.http_build_query([
            'date_from' => '2026-03-01', 'date_to' => '2026-03-31', 'agent_id' => $deps['agentA']->id,
        ]));
        $r->assertOk();
        $row = $this->agentRow($r->json(), $deps['agentA']->id);

        $this->assertEquals(0, (int) $row['refundedTickets']);
    }
}
