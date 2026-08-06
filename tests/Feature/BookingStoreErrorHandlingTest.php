<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\CurrencyRate;
use App\Models\User;
use App\Services\CurrencyRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class BookingStoreErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private District $district;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'passport_no' => 'PASS12345',
            'iqama_type' => 'Iqama',
        ]);

        $this->district = District::create([
            'name' => 'Test District',
            'division' => 'Test Division',
        ]);

        FingerprintCharge::create([
            'district_id' => $this->district->id,
            'user_id' => $this->user->id,
            'fingerprint_charge' => 100,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'district_id' => $this->district->id,
            'fingerprint_charge_id' => FingerprintCharge::first()->id,
            'fingerprint_location' => 'office',
            'passengers' => [[
                'first_name' => 'John',
                'last_name' => 'Doe',
                'passport_no' => 'P87965421',
                'date_of_birth' => '1990-01-01',
                'service_required' => 'all',
            ]],
            'payment' => [
                'amount' => 1,
                'bdt_amount' => 0,
                'currency' => 'SAR',
                'payment_method' => 'cash',
            ],
        ];

        return array_replace_recursive($payload, $overrides);
    }

    public function test_validation_failure_returns_422_with_errors(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('bookings.store'), $this->validPayload([
                'passengers' => [],
            ]));

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors']);
    }

    public function test_unexpected_exception_returns_error_ref_and_rolls_back(): void
    {
        $this->app->instance(CurrencyRateService::class, new class extends CurrencyRateService {
            public function getRateForDate($date): ?CurrencyRate
            {
                throw new \RuntimeException('forced service failure');
            }
        });

        $response = $this->actingAs($this->user)
            ->postJson(route('bookings.store'), $this->validPayload());

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again.',
        ]);
        $response->assertJsonStructure(['error_ref']);

        $this->assertNotEmpty($response->json('error_ref'));
        $this->assertSame(0, Booking::count());
    }

    public function test_unexpected_exception_is_logged_with_error_ref(): void
    {
        Log::shouldReceive('error')
            ->with('Booking creation failed', \Mockery::on(fn($context) => isset($context['error_ref'])));

        $this->app->instance(CurrencyRateService::class, new class extends CurrencyRateService {
            public function getRateForDate($date): ?CurrencyRate
            {
                throw new \RuntimeException('forced service failure');
            }
        });

        $this->actingAs($this->user)
            ->postJson(route('bookings.store'), $this->validPayload());

        $this->assertSame(0, Booking::count());
    }
}