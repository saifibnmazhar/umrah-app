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
use App\Models\RefundedTicket;
use App\Models\ReIssueRefundReason;
use App\Models\Role;
use App\Models\Route;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Models\TicketRequest;
use App\Models\TransactionType;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * IATA Refund / Customer Refund validation on both refund forms:
 * - POST  /bookings/{booking}/passengers/{passenger}/refund (bookings index modal)
 * - PUT   /ticket-requests/{id}/process-refund (refunds confirmation modal)
 *
 * Server: required|numeric|min:0|max:<net_fare> must hold on both endpoints.
 * Client: inline required/negative/net-fare checks + 422 error mapping in both views.
 */
class RefundAmountValidationTest extends TestCase
{
    use RefreshDatabase;

    private const NET_FARE = 24000.00;

    private User $user;

    private array $deps;

    private Booking $booking;

    private Passenger $passenger;

    private IssuedTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $this->user = User::create([
            'name' => 'Admin User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

        $this->deps = $this->seedPrerequisites();
        $this->booking = $this->createBooking($this->deps);
        $this->passenger = $this->addPassenger($this->deps, $this->booking);
        $this->ticket = $this->createIssuedTicket();

        $this->actingAs($this->user);
    }

    private function seedPrerequisites(): array
    {
        $district = District::create(['name' => 'D', 'division' => 'Div']);
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
            'additional_gap' => null,
        ]);
        FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();
        CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 28.0000]);
        TransactionType::create(['name' => 'Initial Payment', 'type' => 'debit']);
        TransactionType::create(['name' => 'Ticket Refund - Re-issue', 'type' => 'debit']);
        $visaPrice = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => 2000.00]);
        $fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30),
            'effective_to' => now()->addDays(30),
            'net_fare' => self::NET_FARE,
            'selling_fare' => 30000.00,
            'offer_price' => null,
            'child_fare_percentage' => 50.00,
            'infant_fare_percentage' => 20.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);
        $package = Package::create([
            'package_name' => 'Pkg',
            'ticket_fare_id' => $fare->id,
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000.00,
            'offer_price' => 36000.00,
            'service_charge' => 500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
        $fingerprintCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->user->id,
            'fingerprint_charge' => 300.00,
        ]);
        $reason = ReIssueRefundReason::create([
            'reason_of' => 'refund',
            'name' => 'Cancelled Flight',
            'default_payment_by' => 'customer',
        ]);

        return compact('district', 'visaPrice', 'fare', 'package', 'fingerprintCharge', 'reason');
    }

    private function createBooking(array $deps): Booking
    {
        $branch = $this->user->branch;
        $customer = Customer::create([
            'name' => 'Cust',
            'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);
        $booking = Booking::create([
            'user_id' => $this->user->id,
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
            'user_id' => $this->user->id,
            'total_amount' => 40000.00,
            'paid_amount' => 0,
            'balance' => 40000.00,
            'status' => 'pending',
        ]);

        return $booking;
    }

    private function addPassenger(array $deps, Booking $booking): Passenger
    {
        return Passenger::create([
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
            'booking_service_charge' => $deps['package']->service_charge ?? 0,
        ]);
    }

    private function createIssuedTicket(): IssuedTicket
    {
        return IssuedTicket::create([
            'passenger_id' => $this->passenger->id,
            'booking_id' => $this->booking->id,
            'user_id' => $this->user->id,
            'ticket_fare_id' => $this->deps['fare']->id,
            'selling_fare' => 30000.00,
            'offer_price' => 26000.00,
            'net_fare' => self::NET_FARE,
            'status' => 'issued',
            'issue_type' => 'regular',
            'issued_date' => now()->toDateString(),
            'pnr' => 'PNR123',
            'ticket_number' => 'TKT123',
        ]);
    }

    private function createPendingRefundRequest(): TicketRequest
    {
        return TicketRequest::create([
            'user_id' => $this->user->id,
            'request_branch_id' => $this->user->branch_id,
            'booking_id' => $this->booking->id,
            'passenger_id' => $this->passenger->id,
            'issued_ticket_id' => $this->ticket->id,
            'request_type' => 'refund',
            'status' => 'pending',
            'requested_at' => now(),
        ]);
    }

    private function refundStorePayload(array $overrides = []): array
    {
        return array_merge([
            'issued_ticket_id' => $this->ticket->id,
            'refund_date' => now()->toDateString(),
            'reason_id' => $this->deps['reason']->id,
            'iata_refund' => 1000,
            'customer_refund' => 800,
            'service_charge' => 200,
            'payment_by' => 'customer',
        ], $overrides);
    }

    private function processRefundPayload(array $overrides = []): array
    {
        return array_merge([
            'reason_id' => $this->deps['reason']->id,
            'iata_refund' => 1000,
            'customer_refund' => 800,
            'service_charge' => 200,
            'payment_by' => 'customer',
        ], $overrides);
    }

    private function postRefund(array $overrides = [])
    {
        return $this->postJson(
            route('bookings.passengers.refund', [$this->booking, $this->passenger]),
            $this->refundStorePayload($overrides)
        );
    }

    private function putProcessRefund(TicketRequest $request, array $overrides = [])
    {
        return $this->putJson(
            route('ticket-requests.process-refund', $request->id),
            $this->processRefundPayload($overrides)
        );
    }

    private function extractSlice(string $content, string $start, string $end): string
    {
        $s = strpos($content, $start);
        $this->assertNotFalse($s, "Start marker not found: {$start}");
        $e = strpos($content, $end, $s + strlen($start));
        $this->assertNotFalse($e, "End marker not found: {$end}");

        return substr($content, $s, $e - $s);
    }

    /*
    |--------------------------------------------------------------------------
    | Server: POST /bookings/{booking}/passengers/{passenger}/refund
    |--------------------------------------------------------------------------
    */

    public function test_refund_store_rejects_missing_iata_refund(): void
    {
        $this->postRefund(['iata_refund' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['iata_refund']);
    }

    public function test_refund_store_rejects_missing_customer_refund(): void
    {
        $this->postRefund(['customer_refund' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_refund']);
    }

    public function test_refund_store_rejects_negative_amounts(): void
    {
        $this->postRefund(['iata_refund' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['iata_refund']);

        $this->postRefund(['customer_refund' => -0.01])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_refund']);
    }

    public function test_refund_store_rejects_non_numeric_amounts(): void
    {
        $this->postRefund(['iata_refund' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['iata_refund']);

        $this->postRefund(['customer_refund' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_refund']);
    }

    public function test_refund_store_rejects_amounts_over_net_fare(): void
    {
        $this->postRefund(['iata_refund' => self::NET_FARE + 0.01])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['iata_refund']);

        $this->postRefund(['customer_refund' => self::NET_FARE + 0.01])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_refund']);
    }

    public function test_refund_store_accepts_zero_amounts(): void
    {
        $response = $this->postRefund([
            'iata_refund' => 0,
            'customer_refund' => 0,
            'service_charge' => 0,
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $refunded = RefundedTicket::latest('id')->first();
        $this->assertNotNull($refunded);
        $this->assertEqualsWithDelta(0, (float) $refunded->iata_refunded_amount, 0.001);
        $this->assertEqualsWithDelta(0, (float) $refunded->refund_to_customer, 0.001);
        $this->assertSame('refunded', $this->ticket->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Server: PUT /ticket-requests/{id}/process-refund
    |--------------------------------------------------------------------------
    */

    public function test_process_refund_rejects_missing_iata_refund_and_keeps_request_pending(): void
    {
        $request = $this->createPendingRefundRequest();

        $this->putProcessRefund($request, ['iata_refund' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['iata_refund']);

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_process_refund_rejects_missing_customer_refund(): void
    {
        $request = $this->createPendingRefundRequest();

        $this->putProcessRefund($request, ['customer_refund' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_refund']);

        $this->assertSame('pending', $request->fresh()->status);
    }

    public function test_process_refund_rejects_negative_amounts(): void
    {
        $request = $this->createPendingRefundRequest();
        $this->putProcessRefund($request, ['iata_refund' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['iata_refund']);

        $request2 = $this->createPendingRefundRequest();
        $this->putProcessRefund($request2, ['customer_refund' => -0.01])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_refund']);
    }

    public function test_process_refund_rejects_non_numeric_amounts(): void
    {
        $request = $this->createPendingRefundRequest();
        $this->putProcessRefund($request, ['iata_refund' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['iata_refund']);
    }

    public function test_process_refund_rejects_amounts_over_net_fare(): void
    {
        $request = $this->createPendingRefundRequest();
        $this->putProcessRefund($request, ['iata_refund' => self::NET_FARE + 0.01])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['iata_refund']);

        $request2 = $this->createPendingRefundRequest();
        $this->putProcessRefund($request2, ['customer_refund' => self::NET_FARE + 0.01])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_refund']);
    }

    public function test_process_refund_accepts_zero_amounts(): void
    {
        $request = $this->createPendingRefundRequest();

        $response = $this->putProcessRefund($request, [
            'iata_refund' => 0,
            'customer_refund' => 0,
            'service_charge' => 0,
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        $refunded = RefundedTicket::latest('id')->first();
        $this->assertNotNull($refunded);
        $this->assertEqualsWithDelta(0, (float) $refunded->iata_refunded_amount, 0.001);
        $this->assertEqualsWithDelta(0, (float) $refunded->refund_to_customer, 0.001);
        $this->assertSame('processed', $request->fresh()->status);
        $this->assertSame('refunded', $this->ticket->fresh()->status);
    }

    /*
    |--------------------------------------------------------------------------
    | Client: bookings index refund form
    |--------------------------------------------------------------------------
    */

    public function test_bookings_refund_submit_validates_iata_and_customer_amounts(): void
    {
        $content = file_get_contents(resource_path('views/bookings/index.blade.php'));
        $slice = $this->extractSlice($content, 'handleRefundSubmit() {', 'async handleTicketVoid(ticket) {');

        $this->assertStringContainsString("f.errors.iata_refund = 'IATA refund is required.'", $slice);
        $this->assertStringContainsString("f.errors.iata_refund = 'IATA refund cannot be negative.'", $slice);
        $this->assertStringContainsString("f.errors.iata_refund = 'IATA refund cannot exceed the net fare.'", $slice);
        $this->assertStringContainsString("f.errors.customer_refund = 'Customer refund is required.'", $slice);
        $this->assertStringContainsString("f.errors.customer_refund = 'Customer refund cannot be negative.'", $slice);
        $this->assertStringContainsString("f.errors.customer_refund = 'Customer refund cannot exceed the net fare.'", $slice);
        $this->assertStringContainsString('status === 422', $slice);
    }

    public function test_bookings_refund_form_has_inline_error_slots_and_markup(): void
    {
        $content = file_get_contents(resource_path('views/bookings/index.blade.php'));

        $this->assertStringContainsString('iata_refund: \'\'', $content);
        $this->assertStringContainsString('customer_refund: \'\'', $content);
        $this->assertStringContainsString('x-show="refundForm.errors.iata_refund"', $content);
        $this->assertStringContainsString('x-show="refundForm.errors.customer_refund"', $content);
        $this->assertStringContainsString('refundForm.errors.iata_refund ? \'border-red-500\'', $content);
        $this->assertStringContainsString('refundForm.errors.customer_refund ? \'border-red-500\'', $content);
    }

    public function test_bookings_refund_modal_defaults_amounts_to_empty(): void
    {
        $content = file_get_contents(resource_path('views/bookings/index.blade.php'));
        $slice = $this->extractSlice($content, 'openRefundModal(rowIndex, ticketIndex) {', 'closeRefundModal() {');

        $this->assertStringContainsString('f.iata_refund = \'\';', $slice);
        $this->assertStringContainsString('f.customer_refund = \'\';', $slice);
        $this->assertStringContainsString('f.iata_refund_bdt = \'\';', $slice);
        $this->assertStringContainsString('f.customer_refund_bdt = \'\';', $slice);
        $this->assertStringNotContainsString('f.iata_refund = 0;', $slice);
        $this->assertStringNotContainsString('f.customer_refund = 0;', $slice);
    }

    /*
    |--------------------------------------------------------------------------
    | Client: refunds confirmation form
    |--------------------------------------------------------------------------
    */

    public function test_process_refund_confirm_validates_iata_and_customer_amounts(): void
    {
        $content = file_get_contents(resource_path('views/refunds/confirmation.blade.php'));
        $slice = $this->extractSlice($content, 'function confirmProcess() {', 'function rejectRefund(');

        $this->assertStringContainsString("setFieldError('inputAgentRefundAmount', 'IATA refund is required.')", $slice);
        $this->assertStringContainsString("setFieldError('inputAgentRefundAmount', 'IATA refund cannot be negative.')", $slice);
        $this->assertStringContainsString("setFieldError('inputAgentRefundAmount', 'IATA refund cannot exceed the net fare.')", $slice);
        $this->assertStringContainsString("setFieldError('inputCustomerRefundAmount', 'Customer refund is required.')", $slice);
        $this->assertStringContainsString("setFieldError('inputCustomerRefundAmount', 'Customer refund cannot be negative.')", $slice);
        $this->assertStringContainsString("setFieldError('inputCustomerRefundAmount', 'Customer refund cannot exceed the net fare.')", $slice);
        $this->assertStringContainsString('clearFieldErrors();', $slice);
        $this->assertStringContainsString('status === 422', $slice);
    }

    public function test_process_refund_form_has_inline_error_helpers_and_clear_handlers(): void
    {
        $content = file_get_contents(resource_path('views/refunds/confirmation.blade.php'));

        $this->assertStringContainsString('function setFieldError(', $content);
        $this->assertStringContainsString('function clearFieldError(', $content);
        $this->assertStringContainsString('function clearFieldErrors(', $content);
        $this->assertStringContainsString('function focusFirstFieldError(', $content);
        $this->assertStringContainsString("clearFieldError('inputAgentRefundAmount')", $content);
        $this->assertStringContainsString("clearFieldError('inputCustomerRefundAmount')", $content);
        $this->assertStringContainsString('clearFieldErrors();', $this->extractSlice($content, 'function processConfirmation(', 'function closeProcessConfirmationModal('));
    }
}
