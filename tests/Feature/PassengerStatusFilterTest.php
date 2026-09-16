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
use App\Models\Fingerprint;
use App\Models\FingerprintCharge;
use App\Models\FingerprintDetail;
use App\Models\FlightDateGap;
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaAgent;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassengerStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        StayDurationLimit::getOrCreate();
        FlightDateGap::getOrCreate();
    }

    private function createUser(): User
    {
        $branch = Branch::create([
            'name' => 'Main Admin Branch',
            'address' => 'Admin Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::create(['name' => 'admin']));

        return $user;
    }

    private function createPrerequisites(User $user): array
    {
        $district = District::create(['name' => 'Test District', 'division' => 'Test Division']);

        $branch = Branch::create([
            'name' => 'Test Branch',
            'address' => 'Test Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'TB01',
        ]);

        $cityCode = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'Bangladesh']);
        $cityCode2 = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'Saudi Arabia']);

        $airline = Airline::create(['name' => 'Saudi Arabian Airlines', 'code' => 'SV']);
        $travelClass = TravelClass::create(['name' => 'Economy']);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);

        $route = Route::create([
            'airline_id' => $airline->id,
            'route_type' => 'round',
            'flight_type' => 'direct',
            'from_city_id' => $cityCode->id,
            'to_city_id' => $cityCode2->id,
            'return_city_id' => $cityCode->id,
            'additional_gap' => null,
        ]);

        CurrencyRate::create(['user_id' => $user->id, 'rate' => 1.0]);

        $visaPrice = VisaSellingPrice::create(['user_id' => $user->id, 'selling_price' => 2000.00]);

        $ticketFare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'offer_price' => null,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $package = Package::create([
            'package_name' => 'Test Umrah Package',
            'ticket_fare_id' => $ticketFare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 35000.00,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'passport_no' => 'TEST123456',
            'mobile_no' => '0501234567',
            'iqama_type' => 'none',
            'address' => 'Test Address',
        ]);

        $fingerprintCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $user->id,
            'fingerprint_charge' => 50.00,
        ]);

        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);
        Bank::create([
            'name' => 'Test Bank',
            'description' => 'Test Bank Description',
            'currency' => 'SAR',
            'location' => 'KSA',
        ]);

        return [
            'user' => $user, 'branch' => $branch, 'district' => $district,
            'customer' => $customer, 'package' => $package,
            'fingerprintCharge' => $fingerprintCharge,
            'ticketFare' => $ticketFare, 'route' => $route,
            'visaPrice' => $visaPrice, 'cityCode' => $cityCode,
            'airline' => $airline, 'airlineClass' => $airlineClass, 'travelClass' => $travelClass,
        ];
    }

    private function createBooking(array $deps): Booking
    {
        return Booking::create([
            'user_id' => $deps['user']->id,
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'fingerprint_location' => 'office',
            'booking_branch_id' => $deps['branch']->id,
            'fingerprint_branch_id' => $deps['branch']->id,
            'pax_qty' => 1,
            'date_gap_id' => FlightDateGap::first()->id,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 35000,
            'invoice_id' => 'INV-TEST-'.uniqid(),
        ]);
    }

    private function createPassenger(Booking $booking, array $deps, ?string $manualStatus = null): Passenger
    {
        $statusId = $manualStatus
            ? PassengerStatus::firstOrCreate(['name' => $manualStatus])->id
            : null;

        return Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'passport_no' => 'PASS'.uniqid(),
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'passport_expiry' => '2030-12-31',
            'mobile_no' => '0501234567',
            'service_required' => 'all',
            'stay_duration' => 14,
            'flight_date_from' => '2025-02-10',
            'flight_date_to' => '2025-02-20',
            'address' => 'Test Address',
            'ticket_fare_id' => $deps['ticketFare']->id,
            'passenger_status_id' => $statusId,
        ]);
    }

    private function getFilteredPassengerIds(User $user, array $query): array
    {
        $this->actingAs($user);

        $response = $this->getJson('/api/bookings/passengers?'.http_build_query($query));
        $response->assertOk();

        return collect($response->json('data'))->pluck('id')->all();
    }

    private function createVisaSubmission(User $user, Passenger $passenger, array $deps): VisaSubmission
    {
        $visaAgent = VisaAgent::create([
            'name' => 'Visa Agent',
            'address' => 'Addr',
            'contacts' => '011',
        ]);

        return VisaSubmission::create([
            'passenger_id' => $passenger->id,
            'visa_agent_id' => $visaAgent->id,
            'visa_selling_price_id' => $deps['visaPrice']->id,
            'status' => 'pending',
        ]);
    }

    // ─── Manual Status Tests ───────────────────────────────────────

    public function test_manual_status_hold_filters_correctly(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $holdPassenger = $this->createPassenger($booking, $deps, 'Hold');
        $cancelPassenger = $this->createPassenger($booking, $deps, 'Cancel');

        $holdStatus = PassengerStatus::where('name', 'Hold')->first();
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $holdStatus->id]);

        $this->assertContains($holdPassenger->id, $ids);
        $this->assertNotContains($cancelPassenger->id, $ids);
    }

    public function test_manual_status_cancel_filters_correctly(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $cancelPassenger = $this->createPassenger($booking, $deps, 'Cancel');
        $holdPassenger = $this->createPassenger($booking, $deps, 'Hold');

        $cancelStatus = PassengerStatus::where('name', 'Cancel')->first();
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $cancelStatus->id]);

        $this->assertContains($cancelPassenger->id, $ids);
        $this->assertNotContains($holdPassenger->id, $ids);
    }

    // ─── Computed Status: Visa Submitted ───────────────────────────

    public function test_computed_visa_submitted_filters_correctly(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);
        $visaSubmission = $this->createVisaSubmission($user, $passenger, $deps);
        $visaSubmission->update(['status' => 'submitted']);

        $status = PassengerStatus::firstOrCreate(['name' => 'Visa Submitted']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertContains($passenger->id, $ids);
    }

    public function test_computed_visa_submitted_excludes_ticket_issued(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);
        $this->createVisaSubmission($user, $passenger, $deps)->update(['status' => 'submitted']);

        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['ticketFare']->id,
            'status' => 'issued',
        ]);

        $status = PassengerStatus::firstOrCreate(['name' => 'Visa Submitted']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertNotContains($passenger->id, $ids);
    }

    // ─── Computed Status: Visa Issued ──────────────────────────────

    public function test_computed_visa_issued_filters_correctly(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);
        $this->createVisaSubmission($user, $passenger, $deps)->update(['status' => 'issued']);

        $status = PassengerStatus::firstOrCreate(['name' => 'Visa Issued']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertContains($passenger->id, $ids);
    }

    public function test_computed_visa_issued_excludes_ticket_issued(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);
        $this->createVisaSubmission($user, $passenger, $deps)->update(['status' => 'issued']);

        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['ticketFare']->id,
            'status' => 'issued',
        ]);

        $status = PassengerStatus::firstOrCreate(['name' => 'Visa Issued']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertNotContains($passenger->id, $ids);
    }

    // ─── Computed Status: Ticket Issued ────────────────────────────

    public function test_computed_ticket_issued_filters_correctly(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);
        $this->createVisaSubmission($user, $passenger, $deps)->update(['status' => 'issued']);

        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['ticketFare']->id,
            'status' => 'issued',
        ]);

        $status = PassengerStatus::firstOrCreate(['name' => 'Ticket Issued']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertContains($passenger->id, $ids);
    }

    public function test_computed_ticket_issued_requires_visa_issued(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);

        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['ticketFare']->id,
            'status' => 'issued',
        ]);

        $status = PassengerStatus::firstOrCreate(['name' => 'Ticket Issued']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertNotContains($passenger->id, $ids);
    }

    // ─── Computed Status: Processing (Visa Cancelled) ──────────────

    public function test_computed_processing_filters_visa_cancelled(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);
        $this->createVisaSubmission($user, $passenger, $deps)->update(['status' => 'cancelled']);

        $status = PassengerStatus::firstOrCreate(['name' => 'Processing']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertContains($passenger->id, $ids);
    }

    // ─── Computed Status: Fingerprint Done ─────────────────────────

    public function test_computed_fingerprint_done_filters_correctly(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);

        $fingerprint = Fingerprint::create([
            'booking_id' => $booking->id,
            'deadline' => now()->addDays(7),
            'cost' => 100,
        ]);

        FingerprintDetail::create([
            'fingerprint_id' => $fingerprint->id,
            'passenger_id' => $passenger->id,
            'status' => 'approved',
        ]);

        $status = PassengerStatus::firstOrCreate(['name' => 'Fingerprint Done']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertContains($passenger->id, $ids);
    }

    public function test_computed_fingerprint_done_excludes_visa_submitted(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);

        $fingerprint = Fingerprint::create([
            'booking_id' => $booking->id,
            'deadline' => now()->addDays(7),
            'cost' => 100,
        ]);

        FingerprintDetail::create([
            'fingerprint_id' => $fingerprint->id,
            'passenger_id' => $passenger->id,
            'status' => 'approved',
        ]);

        $this->createVisaSubmission($user, $passenger, $deps)->update(['status' => 'submitted']);

        $status = PassengerStatus::firstOrCreate(['name' => 'Fingerprint Done']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertNotContains($passenger->id, $ids);
    }

    // ─── Issue 4: Computed Status Filter Mismatches ─────────────────

    public function test_computed_ticket_issued_excludes_refunded_ticket(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);
        $this->createVisaSubmission($user, $passenger, $deps)->update(['status' => 'issued']);

        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['ticketFare']->id,
            'status' => 'issued',
        ]);

        $passenger->update(['ticket_status' => 'issued']);

        IssuedTicket::where('passenger_id', $passenger->id)->update(['status' => 'refunded']);

        $status = PassengerStatus::firstOrCreate(['name' => 'Ticket Issued']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertNotContains($passenger->id, $ids);
    }

    public function test_computed_visa_submitted_excludes_refunded_ticket(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);
        $this->createVisaSubmission($user, $passenger, $deps)->update(['status' => 'submitted']);

        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['ticketFare']->id,
            'status' => 'issued',
        ]);

        $passenger->update(['ticket_status' => 'issued']);

        IssuedTicket::where('passenger_id', $passenger->id)->update(['status' => 'refunded']);

        $status = PassengerStatus::firstOrCreate(['name' => 'Visa Submitted']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertContains($passenger->id, $ids);
    }

    public function test_computed_filter_excludes_manual_status_override(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps, 'Delivered');
        $this->createVisaSubmission($user, $passenger, $deps)->update(['status' => 'issued']);

        $status = PassengerStatus::firstOrCreate(['name' => 'Visa Issued']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertNotContains($passenger->id, $ids);
    }

    public function test_fingerprint_done_excludes_cancelled_visa(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);

        $fingerprint = Fingerprint::create([
            'booking_id' => $booking->id,
            'deadline' => now()->addDays(7),
            'cost' => 100,
        ]);

        FingerprintDetail::create([
            'fingerprint_id' => $fingerprint->id,
            'passenger_id' => $passenger->id,
            'status' => 'approved',
        ]);

        $this->createVisaSubmission($user, $passenger, $deps)->update(['status' => 'cancelled']);

        $status = PassengerStatus::firstOrCreate(['name' => 'Fingerprint Done']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertNotContains($passenger->id, $ids);
    }

    public function test_fingerprint_done_includes_approved_without_visa(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $user->update(['branch_id' => $deps['branch']->id]);
        $booking = $this->createBooking($deps);

        $passenger = $this->createPassenger($booking, $deps);

        $fingerprint = Fingerprint::create([
            'booking_id' => $booking->id,
            'deadline' => now()->addDays(7),
            'cost' => 100,
        ]);

        FingerprintDetail::create([
            'fingerprint_id' => $fingerprint->id,
            'passenger_id' => $passenger->id,
            'status' => 'approved',
        ]);

        $status = PassengerStatus::firstOrCreate(['name' => 'Fingerprint Done']);
        $ids = $this->getFilteredPassengerIds($user, ['passenger_status' => $status->id]);

        $this->assertContains($passenger->id, $ids);
    }
}
