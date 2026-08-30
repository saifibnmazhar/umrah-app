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
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProfitLossBreakdownDetailTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(): User
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $user;
    }

    private function seedPrerequisites(User $user): array
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

        CurrencyRate::create(['user_id' => $user->id, 'rate' => 28.0000]);

        $visaPrice = VisaSellingPrice::create([
            'user_id' => $user->id,
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
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $package = Package::create([
            'package_name' => 'Profit Test Package',
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

        return compact('district', 'visaPrice', 'fare', 'package', 'fingerprintCharge', 'route');
    }

    private function createBooking(User $user, array $deps): Booking
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
            'name' => 'Customer',
            'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);

        $booking = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'office',
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
            'user_id' => $user->id,
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

    private function addPassenger(User $user, array $deps, Booking $booking): Passenger
    {
        $passenger = Passenger::create([
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
        ]);

        VisaSubmission::create([
            'passenger_id' => $passenger->id,
            'visa_selling_price_id' => $deps['visaPrice']->id,
            'agent_commission' => 100.00,
            'net_visa_cost' => 1000.00,
            'additional_cost' => 50.00,
            'status' => 'issued',
            'is_cancelled' => false,
        ]);

        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'selling_fare' => 28000.00,
            'net_fare' => 27000.00,
            'issue_type' => 'regular',
            'status' => 'issued',
            'issued_date' => now(),
        ]);

        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['fare']->id,
            'selling_fare' => 20000.00,
            'net_fare' => 10000.00,
            'issue_type' => 'pending_outbound',
            'status' => 'issued',
            'issued_date' => now(),
        ]);

        return $passenger->refresh();
    }

    /** @test */
    public function profit_loss_payload_includes_detailed_breakdown_subsections(): void
    {
        $user = $this->setupUser();
        $deps = $this->seedPrerequisites($user);
        $booking = $this->createBooking($user, $deps);
        $passenger = $this->addPassenger($user, $deps, $booking);

        Auth::login($user);

        $response = $this->get(route('api.reports.profit-loss', [
            'date_from' => now()->subDays(60)->toDateString(),
            'date_to' => now()->addDays(1)->toDateString(),
        ]));

        $response->assertOk();
        $data = $response->json('passengers');
        $this->assertCount(1, $data);

        $breakdown = $data[0]['breakdown'];

        // Visa sub-section
        $this->assertArrayHasKey('visa', $breakdown);
        $this->assertEquals(2000.0, (float) $breakdown['visa']['selling_price']);
        $this->assertEquals(1000.0, (float) $breakdown['visa']['net_visa_cost']);
        $this->assertEquals(100.0, (float) $breakdown['visa']['agent_commission']);
        $this->assertEquals(50.0, (float) $breakdown['visa']['additional_cost']);
        $this->assertEquals(850.0, (float) $breakdown['visa']['profit']);

        // Ticket sub-section with per-ticket net fares (regular + pending_outbound)
        $this->assertArrayHasKey('ticket', $breakdown);
        $this->assertCount(2, $breakdown['ticket']['net_fares']);
        $this->assertEquals('regular', $breakdown['ticket']['net_fares'][0]['issue_type']);
        $this->assertEquals(27000.0, (float) $breakdown['ticket']['net_fares'][0]['net_fare']);
        $this->assertEquals('pending_outbound', $breakdown['ticket']['net_fares'][1]['issue_type']);
        $this->assertEquals(10000.0, (float) $breakdown['ticket']['net_fares'][1]['net_fare']);

        // Selling fare is the single package value (inbound + outbound)
        $this->assertEquals(30000.0, (float) $breakdown['ticket']['selling_fare']);
        $this->assertEquals(-7000.0, (float) $breakdown['ticket']['profit']);

        // Additional tickets subsection present (empty here)
        $this->assertArrayHasKey('additional_tickets', $breakdown);
        $this->assertEmpty($breakdown['additional_tickets']['items']);
    }
}
