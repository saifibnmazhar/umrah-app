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
use App\Models\VisaUpdateLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatusChangeFilterQueryTest extends TestCase
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
        PassengerStatus::firstOrCreate(['name' => 'Processing'], ['color' => '#000000']);

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

    private function createBookingWithPassenger(array $deps): Booking
    {
        $user = $deps['user'];

        $booking = Booking::create([
            'user_id' => $user->id,
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

        Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'passport_no' => 'PASS12345',
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
            'passenger_status_id' => PassengerStatus::firstWhere('name', 'Processing')?->id,
        ]);

        return $booking;
    }

    private function createPassengerForBooking(Booking $booking, string $firstName, ?int $statusId, array $deps): Passenger
    {
        return Passenger::create([
            'booking_id' => $booking->id,
            'first_name' => $firstName,
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

        $response = $this->get(route('bookings.index', $query));
        $response->assertOk();

        $passengers = $response->viewData('passengers');
        $this->assertInstanceOf(LengthAwarePaginator::class, $passengers);

        return $passengers->getCollection()->pluck('id')->all();
    }

    public function test_visa_submitted_filter_returns_passenger_with_submitted_log(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $booking = $this->createBookingWithPassenger($deps);
        $passenger = Passenger::where('booking_id', $booking->id)->first();
        $user->update(['branch_id' => $deps['branch']->id]);

        $visaSubmission = $this->createVisaSubmission($deps, $passenger);
        // pending -> submitted produces a 'submitted' visa_update_log via the observer
        $this->actingAs($user);
        $visaSubmission->update(['status' => 'submitted']);

        $this->assertDatabaseHas('visa_update_logs', [
            'visa_submission_id' => $visaSubmission->id,
            'action' => 'submitted',
        ]);

        $ids = $this->getFilteredPassengerIds($user, ['status_change_action' => 'visa_submitted']);
        $this->assertContains($passenger->id, $ids);
    }

    public function test_visa_issued_filter_returns_passenger_with_issued_log(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $booking = $this->createBookingWithPassenger($deps);
        $passenger = Passenger::where('booking_id', $booking->id)->first();
        $user->update(['branch_id' => $deps['branch']->id]);

        $visaSubmission = $this->createVisaSubmission($deps, $passenger);
        $this->actingAs($user);
        $visaSubmission->update(['status' => 'submitted']);
        $visaSubmission->update(['status' => 'issued']);

        $this->assertDatabaseHas('visa_update_logs', [
            'visa_submission_id' => $visaSubmission->id,
            'action' => 'issued',
        ]);

        $ids = $this->getFilteredPassengerIds($user, ['status_change_action' => 'visa_issued']);
        $this->assertContains($passenger->id, $ids);
    }

    public function test_ticket_issued_filter_returns_passenger_with_issued_log(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $booking = $this->createBookingWithPassenger($deps);
        $passenger = Passenger::where('booking_id', $booking->id)->first();
        $user->update(['branch_id' => $deps['branch']->id]);

        $this->actingAs($user);
        $ticket = IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['ticketFare']->id,
            'status' => 'issued',
        ]);
        $ticket->logAction('issued', null, ['status' => 'issued']);

        $ids = $this->getFilteredPassengerIds($user, ['status_change_action' => 'ticket_issued']);
        $this->assertContains($passenger->id, $ids);
    }

    public function test_ticket_issued_filter_matches_log_via_new_data_status(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $booking = $this->createBookingWithPassenger($deps);
        $passenger = Passenger::where('booking_id', $booking->id)->first();
        $user->update(['branch_id' => $deps['branch']->id]);

        $this->actingAs($user);
        $ticket = IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'ticket_fare_id' => $deps['ticketFare']->id,
            'status' => 'issued',
        ]);
        // action differs from 'issued', but the log's new_data->status says 'issued'
        $ticket->logAction('edited', null, ['status' => 'issued']);

        $ids = $this->getFilteredPassengerIds($user, ['status_change_action' => 'ticket_issued']);
        $this->assertContains($passenger->id, $ids);
    }

    public function test_visa_submitted_filter_excludes_cancel_delivered_hold_passengers(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $booking = $this->createBookingWithPassenger($deps);
        $passenger = Passenger::where('booking_id', $booking->id)->first();
        $user->update(['branch_id' => $deps['branch']->id]);

        $vs1 = $this->createVisaSubmission($deps, $passenger);
        $this->actingAs($user);
        $vs1->update(['status' => 'submitted']);

        $cancelStatus = PassengerStatus::firstOrCreate(['name' => 'Cancel'], ['color' => '#ff0000']);
        $deliveredStatus = PassengerStatus::firstOrCreate(['name' => 'Delivered'], ['color' => '#00ff00']);
        $holdStatus = PassengerStatus::firstOrCreate(['name' => 'Hold'], ['color' => '#0000ff']);

        // Add a 'submitted' log directly (bypasses observer's status recompute) so the
        // passenger keeps its Cancel/Delivered/Hold status while still having a matching log.
        foreach ([$cancelStatus, $deliveredStatus, $holdStatus] as $status) {
            $excluded = $this->createPassengerForBooking($booking, 'Excluded', $status->id, $deps);
            $vs = $this->createVisaSubmission($deps, $excluded);
            // createVisaSubmission fires the observer's created hook which recomputes the
            // passenger status; re-assert the Cancel/Delivered/Hold status via a raw update.
            DB::table('passengers')
                ->where('id', $excluded->id)
                ->update(['passenger_status_id' => $status->id]);
            VisaUpdateLog::create([
                'visa_submission_id' => $vs->id,
                'user_id' => $user->id,
                'action' => 'submitted',
            ]);
        }

        $ids = $this->getFilteredPassengerIds($user, ['status_change_action' => 'visa_submitted']);
        $this->assertContains($passenger->id, $ids);
        $excludedNames = Passenger::whereIn('first_name', ['Excluded'])->pluck('id')->all();
        foreach ($excludedNames as $excludedId) {
            $this->assertNotContains($excludedId, $ids);
        }
    }

    public function test_visa_submitted_filter_includes_passenger_with_null_status(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);

        $booking = $this->createBookingWithPassenger($deps);
        $processingPassenger = Passenger::where('booking_id', $booking->id)->first();
        $this->actingAs($user);
        $this->createVisaSubmission($deps, $processingPassenger)->update(['status' => 'submitted']);

        $nullPassenger = $this->createPassengerForBooking($booking, 'NullStatus', null, $deps);
        $this->createVisaSubmission($deps, $nullPassenger)->update(['status' => 'submitted']);

        $user->update(['branch_id' => $deps['branch']->id]);

        $ids = $this->getFilteredPassengerIds($user, ['status_change_action' => 'visa_submitted']);
        $this->assertContains($processingPassenger->id, $ids);
        $this->assertContains($nullPassenger->id, $ids);
    }

    public function test_visa_submitted_filter_narrows_by_date_range(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $booking = $this->createBookingWithPassenger($deps);
        $passenger = Passenger::where('booking_id', $booking->id)->first();
        $user->update(['branch_id' => $deps['branch']->id]);

        $visaSubmission = $this->createVisaSubmission($deps, $passenger);
        $this->actingAs($user);
        $visaSubmission->update(['status' => 'submitted']);

        VisaUpdateLog::where('visa_submission_id', $visaSubmission->id)
            ->update(['created_at' => now()->subDays(30)]);

        // Range that excludes the log's date -> passenger should not appear
        $idsOut = $this->getFilteredPassengerIds($user, [
            'status_change_action' => 'visa_submitted',
            'status_change_from' => now()->subDays(10)->toDateString(),
            'status_change_to' => now()->toDateString(),
        ]);
        $this->assertNotContains($passenger->id, $idsOut);

        // Range that includes the log's date -> passenger should appear
        $idsIn = $this->getFilteredPassengerIds($user, [
            'status_change_action' => 'visa_submitted',
            'status_change_from' => now()->subDays(40)->toDateString(),
            'status_change_to' => now()->subDays(20)->toDateString(),
        ]);
        $this->assertContains($passenger->id, $idsIn);
    }

    private function createVisaSubmission(array $deps, Passenger $passenger): VisaSubmission
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
}
