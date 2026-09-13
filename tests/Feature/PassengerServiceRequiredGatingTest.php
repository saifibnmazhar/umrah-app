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
use App\Models\Package;
use App\Models\Passenger;
use App\Models\Role;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaAgent;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassengerServiceRequiredGatingTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $role = Role::create(['name' => 'Super Admin']);
        $user->roles()->attach($role);

        return $user;
    }

    private function seedDeps(User $user): array
    {
        $district = District::create(['name' => 'D'.uniqid(), 'division' => 'Div']);
        $cityFrom = CityCode::create(['city_name' => 'Dhaka', 'code' => uniqid('D'), 'country' => 'BD']);
        $cityTo = CityCode::create(['city_name' => 'Riyadh', 'code' => uniqid('R'), 'country' => 'SA']);
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
        ]);
        FlightDateGap::getOrCreate();
        CurrencyRate::create(['user_id' => $user->id, 'rate' => 28.0]);
        $visaPrice = VisaSellingPrice::create(['user_id' => $user->id, 'selling_price' => 2000]);
        $fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 24000,
            'selling_fare' => 30000,
            'child_fare_percentage' => 50,
            'infant_fare_percentage' => 20,
            'with_meal' => true,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $package = Package::create([
            'package_name' => 'Pkg '.uniqid(),
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000,
            'service_charge' => 500,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
        $fpCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $user->id,
            'fingerprint_charge' => 300,
        ]);

        return compact('district', 'package', 'fpCharge', 'visaPrice', 'fare');
    }

    private function makeBooking(User $user, array $deps): Booking
    {
        $branch = Branch::create([
            'name' => 'B'.uniqid(),
            'address' => 'Addr',
            'contacts' => '0123',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'BR'.substr(uniqid(), -6),
        ]);
        $customer = Customer::create([
            'name' => 'C'.uniqid(),
            'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);

        return Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fpCharge']->id,
            'booking_branch_id' => $branch->id,
            'fingerprint_branch_id' => $branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'home',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000,
            'is_cancelled' => false,
        ]);
    }

    public function test_blade_exposes_service_required_and_messages(): void
    {
        $src = file_get_contents(resource_path('views/bookings/index.blade.php'));
        $this->assertStringContainsString('service_required', $src);
        $this->assertStringContainsString('font-bold text-slate-700">Ticket Only', $src);
        $this->assertStringContainsString('font-bold text-slate-700">Visa Only', $src);
        $this->assertStringContainsString("service_required) !== 'ticket_only'", $src);
        $this->assertStringContainsString("service_required !== 'visa_only'", $src);
    }

    public function test_visa_submit_rejected_for_ticket_only(): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user);
        $deps = $this->seedDeps($user);
        $booking = $this->makeBooking($user, $deps);
        $passenger = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Pax',
            'last_name' => 'Test',
            'passport_no' => 'PP'.substr(uniqid(), -8),
            'mobile_no' => '0500000000',
            'date_of_birth' => '1990-01-01',
            'passenger_type' => 'adult',
            'passport_expiry' => '2030-12-31',
            'stay_duration' => 14,
            'service_required' => 'ticket_only',
            'flight_date_from' => now()->addDays(5)->toDateString(),
            'flight_date_to' => now()->addDays(15)->toDateString(),
            'ticket_status' => 'pending',
            'visa_status' => 'pending',
            'address' => 'Addr',
            'package_value' => 25000,
        ]);
        $visaPrice = VisaSellingPrice::where('user_id', $user->id)->first();
        VisaSubmission::create(['passenger_id' => $passenger->id, 'visa_selling_price_id' => $visaPrice->id, 'status' => 'pending']);
        $agent = VisaAgent::create(['name' => 'VA'.uniqid(), 'address' => 'Addr', 'contacts' => '0123']);

        $this->postJson(route('bookings.passengers.visa-submit', [$booking->id, $passenger->id]), [
            'visa_agent_id' => $agent->id,
        ])->assertStatus(403);
    }

    public function test_ticket_issue_rejected_for_visa_only(): void
    {
        $user = $this->makeAdmin();
        $this->actingAs($user);
        $booking = $this->makeBooking($user, $this->seedDeps($user));
        $passenger = Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'Pax',
            'last_name' => 'Test',
            'passport_no' => 'PP'.substr(uniqid(), -8),
            'mobile_no' => '0500000000',
            'date_of_birth' => '1990-01-01',
            'passenger_type' => 'adult',
            'passport_expiry' => '2030-12-31',
            'stay_duration' => 14,
            'service_required' => 'visa_only',
            'flight_date_from' => now()->addDays(5)->toDateString(),
            'flight_date_to' => now()->addDays(15)->toDateString(),
            'ticket_status' => 'pending',
            'visa_status' => 'pending',
            'address' => 'Addr',
            'package_value' => 25000,
        ]);
        $ticket = IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->postJson(route('bookings.passengers.ticket-issue', [$booking->id, $passenger->id]), [
            'issued_ticket_id' => $ticket->id,
        ])->assertStatus(403);
    }
}
