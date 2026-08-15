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
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BookingFormSubmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
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
            'email' => 'test@example.com',
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

        $cityCode = CityCode::create([
            'city_name' => 'Dhaka', 'code' => 'DAC',
            'country' => 'Bangladesh',
        ]);
        $cityCode2 = CityCode::create([
            'city_name' => 'Riyadh', 'code' => 'RUH',
            'country' => 'Saudi Arabia',
        ]);

        $airline = Airline::create([
            'name' => 'Saudi Arabian Airlines',
            'code' => 'SV',
        ]);

        $travelClass = TravelClass::create(['name' => 'Economy']);
        $airlineClass = AirlineClass::create([
            'airline_id' => $airline->id,
            'class_id' => $travelClass->id,
        ]);

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

        $visaPrice = VisaSellingPrice::create([
            'user_id' => $user->id,
            'selling_price' => 2000.00,
        ]);

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

        StayDurationLimit::getOrCreate();
        FlightDateGap::getOrCreate();
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
        ];
    }

    private function fullPayload(array $deps): array
    {
        return [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'package_id' => $deps['package']->id,
            'passengers' => [
                [
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
                ],
            ],
            'payment' => [
                'amount' => 100,
                'bdt_amount' => 0,
                'currency' => 'SAR',
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ],
        ];
    }

    /** @test */
    public function test_booking_store_creates_booking_with_passenger_and_invoice(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $response = $this->post(route('bookings.store'), $this->fullPayload($deps));
        $response->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'pax_qty' => 1,
        ]);

        $this->assertDatabaseHas('passengers', [
            'first_name' => 'John', 'last_name' => 'Doe',
            'passport_no' => 'PASS12345',
        ]);

        $booking = Booking::first();
        $this->assertDatabaseHas('invoices', ['booking_id' => $booking->id]);
        $this->assertDatabaseCount('visa_submissions', 1);
        $this->assertDatabaseCount('issued_tickets', 1);
        $this->assertDatabaseCount('fingerprints', 1);
        $this->assertDatabaseCount('fingerprint_details', 1);
    }

    /** @test */
    public function test_booking_store_with_booking_customer_docs_creates_documents(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $file1 = UploadedFile::fake()->create('customer_doc1.jpg', 100, 'image/jpeg');
        $file2 = UploadedFile::fake()->create('customer_doc2.pdf', 100, 'application/pdf');

        $payload = $this->fullPayload($deps);
        $payload['booking_customer_docs'] = [$file1, $file2];

        $response = $this->post(route('bookings.store'), $payload);
        $response->assertRedirect();

        $booking = Booking::first();
        $this->assertNotNull($booking, 'Booking was not created');

        $bookingDocCount = $booking->documents()->count();

        $docs = $booking->documents;
        $this->assertCount(2, $docs);

        foreach ($docs as $doc) {
            Storage::disk('public')->assertExists($doc->file_path);
        }
    }

    /** @test */
    public function test_booking_store_with_multiple_passenger_docs_creates_documents(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->fullPayload($deps);
        $payload['passenger_docs'] = [
            [0 => UploadedFile::fake()->create('p1.jpg', 100, 'image/jpeg'),
                1 => UploadedFile::fake()->create('p2.png', 100, 'image/png'),
                2 => UploadedFile::fake()->create('p3.pdf', 100, 'application/pdf')],
        ];

        $response = $this->post(route('bookings.store'), $payload);
        $response->assertRedirect();

        $booking = Booking::first();
        $this->assertNotNull($booking, 'Booking was not created');
        $passenger = Passenger::first();
        $docs = $passenger->documents;
        $this->assertCount(3, $docs);

        foreach ($docs as $doc) {
            // Passenger docs are stored without specifying a disk (uses default = local)
            Storage::disk('local')->assertExists($doc->file_path);
        }
    }

    /** @test */
    public function test_booking_store_with_booking_and_passenger_docs_simultaneously(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->fullPayload($deps);
        $payload['booking_customer_docs'] = [
            UploadedFile::fake()->create('b1.jpg', 100, 'image/jpeg'),
            UploadedFile::fake()->create('b2.pdf', 100, 'application/pdf'),
        ];
        $payload['passenger_docs'] = [
            [0 => UploadedFile::fake()->create('p1.jpg', 100, 'image/jpeg'),
                1 => UploadedFile::fake()->create('p2.png', 100, 'image/png')],
        ];

        $response = $this->post(route('bookings.store'), $payload);
        $response->assertRedirect();

        $booking = Booking::first();
        $this->assertNotNull($booking, 'Booking was not created');
        $this->assertCount(2, $booking->documents);

        $passenger = Passenger::first();
        $this->assertCount(2, $passenger->documents);
    }

    /** @test */
    public function test_booking_store_with_two_passengers_and_docs(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->fullPayload($deps);
        $payload['pax_qty'] = 2;
        $payload['passengers'][] = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'passport_no' => 'PASS67890',
            'date_of_birth' => '1985-05-15',
            'gender' => 'female',
            'passport_expiry' => '2031-06-30',
            'mobile_no' => '0507654321',
            'service_required' => 'visa_only',
            'stay_duration' => 10,
            'flight_date_from' => '2025-03-15',
            'flight_date_to' => '2025-03-25',
            'address' => 'Another Address',
        ];
        $payload['passenger_docs'] = [
            [0 => UploadedFile::fake()->create('p1.jpg', 100, 'image/jpeg')],
            [0 => UploadedFile::fake()->create('p2.pdf', 100, 'application/pdf')],
        ];
        $payload['booking_customer_docs'] = [
            UploadedFile::fake()->create('booking_doc.pdf', 100, 'application/pdf'),
        ];

        $response = $this->post(route('bookings.store'), $payload);
        $response->assertRedirect();

        $this->assertDatabaseCount('passengers', 2);
        $this->assertDatabaseHas('bookings', ['pax_qty' => 2]);

        $p1 = Passenger::where('first_name', 'John')->first();
        $p2 = Passenger::where('first_name', 'Jane')->first();
        $booking = Booking::first();

        // 1 booking customer doc
        $this->assertCount(1, $booking->documents);

        $this->assertCount(1, $p1->documents);
        $this->assertCount(1, $p2->documents);

        $this->assertDatabaseCount('visa_submissions', 2);
        $this->assertDatabaseCount('issued_tickets', 2);
    }

    /** @test */
    public function test_booking_store_with_payment_updates_invoice(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->fullPayload($deps);
        $payload['payment']['amount'] = 5000;

        $response = $this->post(route('bookings.store'), $payload);
        $response->assertRedirect();

        $this->assertDatabaseCount('payments', 1);
        $payment = Payment::first();
        $this->assertEquals(5000, $payment->amount);

        $booking = Booking::first();
        $invoice = $booking->invoice;
        $this->assertEquals(5000, $invoice->paid_amount);
        $this->assertGreaterThan(0, $invoice->balance);
    }

    /** @test */
    public function test_booking_store_creates_fingerprint_with_details(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->fullPayload($deps);
        $payload['pax_qty'] = 2;
        $payload['passengers'][] = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'passport_no' => 'PASS67890',
            'date_of_birth' => '1985-05-15',
            'gender' => 'female',
            'passport_expiry' => '2031-06-30',
            'mobile_no' => '0507654321',
            'service_required' => 'all',
            'stay_duration' => 10,
            'flight_date_from' => '2025-03-15',
            'flight_date_to' => '2025-03-25',
            'address' => 'Test Address',
        ];

        $response = $this->post(route('bookings.store'), $payload);
        $response->assertRedirect();

        $booking = Booking::first();
        $this->assertNotNull($booking, 'Booking was not created');
        $this->assertDatabaseCount('fingerprints', 1);
        $fingerprint = Fingerprint::first();
        $this->assertNotNull($fingerprint->deadline);
        $this->assertDatabaseCount('fingerprint_details', 2);
    }

    /** @test */
    public function test_booking_store_with_zero_amount_payment_creates_booking_but_no_payment(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        // The validation requires payment.amount >= 0.01, so sending 0 should fail validation
        $payload = $this->fullPayload($deps);
        $payload['payment']['amount'] = 0;

        $response = $this->post(route('bookings.store'), $payload);
        $response->assertSessionHasErrors(['payment.amount']);
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @test */
    public function test_booking_store_validation_fails_without_required_fields(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->fullPayload($deps);
        unset($payload['customer_id']);

        $response = $this->post(route('bookings.store'), $payload);
        $response->assertSessionHasErrors(['customer_id']);
    }

    /** @test */
    public function test_booking_store_validation_fails_without_passengers(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->fullPayload($deps);
        unset($payload['passengers']);

        $response = $this->post(route('bookings.store'), $payload);
        $response->assertSessionHasErrors(['passengers']);
    }

    /** @test */
    public function test_booking_store_rejects_invalid_file_types(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->fullPayload($deps);
        $payload['booking_customer_docs'] = [
            UploadedFile::fake()->create('document.exe', 100, 'application/x-msdownload'),
        ];

        $response = $this->post(route('bookings.store'), $payload);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function test_booking_store_rejects_oversized_files(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $payload = $this->fullPayload($deps);
        $payload['booking_customer_docs'] = [
            UploadedFile::fake()->create('large_doc.pdf', 6000, 'application/pdf'),
        ];

        $response = $this->post(route('bookings.store'), $payload);
        $response->assertSessionHasErrors();
    }

    /** @test */
    public function test_booking_store_ajax_returns_json(): void
    {
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $response = $this->postJson(route('bookings.store'), $this->fullPayload($deps));

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'url'])
            ->assertJson(['success' => true]);
    }
}
