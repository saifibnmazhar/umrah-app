<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Package;
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

class BookingMultiUploadTest extends TestCase
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
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $role = Role::create(['name' => 'admin']);
        $user->roles()->attach($role);

        return $user;
    }

    private function createPrerequisites(User $user): array
    {
        $district = District::create(['name' => 'Test District', 'division' => 'Test Division']);
        $branch = Branch::create(['name' => 'Test Branch', 'address' => 'Test Address', 'contacts' => '0123456789', 'location' => 'KSA', 'fingerprint_operation' => false]);
        $user->branch_id = $branch->id;
        $user->save();

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

        // Seed required data for booking store
        StayDurationLimit::getOrCreate();
        FlightDateGap::getOrCreate();
        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);

        // Package prerequisites (required NOT NULL FK on bookings table)
        $cityCode = CityCode::create(['city_name' => 'Jeddah', 'code' => 'JED', 'country' => 'Saudi Arabia']);
        $cityCode2 = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'Bangladesh']);
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
            'offer_price' => null,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);

        return [
            'district' => $district,
            'branch_id' => $branch->id,
            'customer' => $customer,
            'fingerprintCharge' => $fingerprintCharge,
            'package' => $package,
        ];
    }

    private function bookingStorePayload(array $deps): array
    {
        return [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'fingerprint_location' => 'office',
            'booking_branch_id' => $deps['branch_id'],
            'fingerprint_branch_id' => $deps['branch_id'],
            'package_id' => $deps['package']->id,
            'pax_qty' => 1,
            'passengers' => [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'passport_no' => 'PASS12345',
                    'date_of_birth' => '1990-01-01',
                    'address' => 'Test Address',
                ],
            ],
            'payment' => [
                'amount' => 100,
                'currency' => 'SAR',
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ],
        ];
    }

    /**
     * Test that multiple booking_customer_docs files are accepted by validation.
     * A 422 response means validation failed — so we assert status != 422.
     */
    public function test_booking_store_accepts_multiple_booking_customer_docs(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $file1 = UploadedFile::fake()->create('doc1.jpg', 100, 'image/jpeg');
        $file2 = UploadedFile::fake()->create('doc2.pdf', 100, 'application/pdf');

        $payload = $this->bookingStorePayload($deps);
        $payload['booking_customer_docs'] = [$file1, $file2];

        $response = $this->post(route('bookings.store'), $payload);

        // Should NOT be a 422 validation error (means files were accepted)
        $this->assertNotEquals(422, $response->getStatusCode(),
            'Validation failed — multiple booking_customer_docs not accepted. Response: '.$response->getContent());
    }

    /**
     * Test that multiple passenger_docs files are accepted by validation.
     */
    public function test_booking_store_accepts_multiple_passenger_docs(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $passengerDoc1 = UploadedFile::fake()->create('passenger1.jpg', 100, 'image/jpeg');
        $passengerDoc2 = UploadedFile::fake()->create('passenger2.png', 100, 'image/png');

        $payload = $this->bookingStorePayload($deps);
        $payload['passenger_docs'] = [
            [0 => $passengerDoc1, 1 => $passengerDoc2],
        ];

        $response = $this->post(route('bookings.store'), $payload);

        $this->assertNotEquals(422, $response->getStatusCode(),
            'Validation failed — multiple passenger_docs not accepted. Response: '.$response->getContent());
    }

    /**
     * Test that booking + passenger docs are accepted simultaneously by validation.
     */
    public function test_booking_store_accepts_both_booking_and_passenger_docs_simultaneously(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $bookingDoc1 = UploadedFile::fake()->create('booking1.jpg', 100, 'image/jpeg');
        $bookingDoc2 = UploadedFile::fake()->create('booking2.pdf', 100, 'application/pdf');
        $passengerDoc1 = UploadedFile::fake()->create('passenger1.jpg', 100, 'image/jpeg');
        $passengerDoc2 = UploadedFile::fake()->create('passenger2.png', 100, 'image/png');

        $payload = $this->bookingStorePayload($deps);
        $payload['booking_customer_docs'] = [$bookingDoc1, $bookingDoc2];
        $payload['passenger_docs'] = [
            [0 => $passengerDoc1, 1 => $passengerDoc2],
        ];

        $response = $this->post(route('bookings.store'), $payload);

        $this->assertNotEquals(422, $response->getStatusCode(),
            'Validation failed — multiple docs not accepted simultaneously. Response: '.$response->getContent());
    }

    /**
     * Test that large files (over 5MB) are rejected by validation.
     * Uses a file just over the 5120 KB validation limit.
     */
    public function test_booking_store_rejects_large_booking_customer_docs(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        // Create a file 200KB over the 5120 KB validation limit
        $largeFile = UploadedFile::fake()->create('large_doc.pdf', 5120 + 200, 'application/pdf');

        $payload = $this->bookingStorePayload($deps);
        $payload['booking_customer_docs'] = [$largeFile];

        $response = $this->post(route('bookings.store'), $payload);

        $response->assertSessionHasErrors(['booking_customer_docs.0']);
        $this->assertDatabaseCount('bookings', 0);
    }

    /**
     * Test that large passenger doc files are rejected by validation.
     */
    public function test_booking_store_rejects_large_passenger_docs(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $largeFile = UploadedFile::fake()->create('large_passenger.jpg', 5120 + 200, 'image/jpeg');

        $payload = $this->bookingStorePayload($deps);
        $payload['passenger_docs'] = [
            [0 => $largeFile],
        ];

        $response = $this->post(route('bookings.store'), $payload);

        $response->assertSessionHasErrors(['passenger_docs.0.0']);
        $this->assertDatabaseCount('bookings', 0);
    }

    /**
     * Test that exactly-at-limit files (just under 5MB) are accepted.
     */
    public function test_booking_store_accepts_at_limit_booking_customer_docs(): void
    {
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);
        $this->actingAs($user);

        $atLimitFile = UploadedFile::fake()->create('at_limit.pdf', 4000, 'application/pdf');

        $payload = $this->bookingStorePayload($deps);
        $payload['booking_customer_docs'] = [$atLimitFile];

        $response = $this->post(route('bookings.store'), $payload);

        $response->assertSessionHasNoErrors();
        // File under 5MB limit (5120 KB) should pass validation and create booking
        $this->assertDatabaseCount('bookings', 1);
    }
}
