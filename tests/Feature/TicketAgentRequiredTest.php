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
use App\Models\ReIssueRefundReason;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketAgent;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAgentRequiredTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = User::create([
            'name' => 'Agent Required User '.uniqid(),
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'Super Admin']));

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
        $fare = TicketFare::create([
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
            'ticket_fare_id' => $fare->id,
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
        $passengerStatusId = PassengerStatus::firstOrCreate(['name' => 'Processing'])->id;
        $agent = TicketAgent::create(['name' => 'Agent '.uniqid(), 'address' => 'Addr', 'contacts' => '0123']);
        $reason = ReIssueRefundReason::create(['reason_of' => 're-issue', 'name' => 'R '.uniqid()]);

        return compact('district', 'fare', 'package', 'fingerprintCharge', 'branch', 'passengerStatusId', 'currencyRate', 'flightDateGap', 'agent', 'reason');
    }

    private function makeBookingWithPassenger(User $user, array $deps): array
    {
        $customer = Customer::create([
            'name' => 'Customer '.uniqid(),
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
            'invoice_id' => 'INV-'.uniqid(),
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
        $passenger = Passenger::create([
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
            'ticket_fare_id' => $deps['fare']->id,
            'package_value' => 25000.00,
        ]);

        return [$booking, $passenger];
    }

    public function test_issue_and_edit_without_agent_return_422(): void
    {
        $user = $this->makeUser();
        $deps = $this->baseDeps($user);
        [$booking, $passenger] = $this->makeBookingWithPassenger($user, $deps);
        $ticket = IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'selling_fare' => 2820.00,
            'net_fare' => 2700.00,
            'issue_type' => 'regular',
            'status' => 'pending',
        ]);

        // Issue without agent → 422.
        $this->actingAs($user)->postJson(
            route('bookings.passengers.ticket-issue', [$booking->id, $passenger->id]),
            ['issued_ticket_id' => $ticket->id, 'issued_date' => now()->toDateString()]
        )->assertStatus(422)->assertJsonValidationErrors(['ticket_agent_id']);

        // Issue with agent → OK (control).
        $this->actingAs($user)->postJson(
            route('bookings.passengers.ticket-issue', [$booking->id, $passenger->id]),
            [
                'issued_ticket_id' => $ticket->id,
                'ticket_agent_id' => $deps['agent']->id,
                'issued_date' => now()->toDateString(),
            ]
        )->assertOk();

        // Edit without agent → 422.
        $this->actingAs($user)->putJson(
            route('bookings.passengers.ticket-edit', [$booking->id, $passenger->id]),
            ['issued_ticket_id' => $ticket->id, 'issued_date' => now()->toDateString()]
        )->assertStatus(422)->assertJsonValidationErrors(['ticket_agent_id']);
    }

    public function test_reissue_refund_of_legacy_null_agent_ticket_return_422(): void
    {
        $user = $this->makeUser();
        $deps = $this->baseDeps($user);

        // Legacy ticket: issued with NULL agent (pre-R2 data).
        [$booking1, $passenger1] = $this->makeBookingWithPassenger($user, $deps);
        $legacy1 = IssuedTicket::create([
            'passenger_id' => $passenger1->id,
            'booking_id' => $booking1->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'selling_fare' => 2820.00,
            'net_fare' => 2700.00,
            'issue_type' => 'regular',
            'status' => 'issued',
            'issued_date' => '2026-03-05',
        ]);

        $this->actingAs($user)->postJson(
            route('bookings.passengers.re-issue', [$booking1->id, $passenger1->id]),
            [
                'issued_ticket_id' => $legacy1->id,
                'ticket_number' => '779-NOAGENT-RE',
                're_issue_date' => '2026-03-10',
                'reason_id' => $deps['reason']->id,
                're_issue_charge' => 300.00,
            ]
        )->assertStatus(422);

        [$booking2, $passenger2] = $this->makeBookingWithPassenger($user, $deps);
        $legacy2 = IssuedTicket::create([
            'passenger_id' => $passenger2->id,
            'booking_id' => $booking2->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'selling_fare' => 2820.00,
            'net_fare' => 2700.00,
            'issue_type' => 'regular',
            'status' => 'issued',
            'issued_date' => '2026-03-05',
        ]);

        $this->actingAs($user)->postJson(
            route('bookings.passengers.refund', [$booking2->id, $passenger2->id]),
            [
                'issued_ticket_id' => $legacy2->id,
                'ticket_number' => '779-NOAGENT-RF',
                'refund_date' => '2026-03-11',
                'reason_id' => $deps['reason']->id,
                'iata_refund' => 100.00,
                'customer_refund' => 90.00,
                'service_charge' => 10.00,
            ]
        )->assertStatus(422);
    }
}
