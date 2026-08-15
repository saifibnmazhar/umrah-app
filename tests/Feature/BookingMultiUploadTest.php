<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Role;
use App\Models\StayDurationLimit;
use App\Models\TransactionType;
use App\Models\User;
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
        Storage::fake('default');
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
        Branch::create(['name' => 'Test Branch', 'address' => 'Test Address', 'contacts' => '0123456789', 'location' => 'KSA', 'fingerprint_operation' => false]);
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

        return [
            'district' => $district,
            'customer' => $customer,
            'fingerprintCharge' => $fingerprintCharge,
        ];
    }

    private function bookingStorePayload(array $deps): array
    {
        return [
            'customer_id' => $deps['customer']->id,
            'district_id' => $deps['district']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'fingerprint_location' => 'Office',
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
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);

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
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);

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
        $this->withoutMiddleware();
        $user = $this->createUser();
        $deps = $this->createPrerequisites($user);

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
}
