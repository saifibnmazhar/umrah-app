<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\TicketFare;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * BookingController::update package-swap branches must keep the Regular row's
 * `outbound_pending` flag in sync with the live pending_outbound companion:
 *
 *   - swap -> double ticket: a live companion (any status) is rewritten, never
 *     duplicated, and the Regular row is flagged outbound_pending = true.
 *   - swap -> single ticket: the companion is removed and the flag is cleared.
 */
class BookingPackageSwapOutboundPendingTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private District $district;

    private FingerprintCharge $fpCharge;

    private TicketFare $singleFare;

    private TicketFare $inboundFare;

    private TicketFare $outboundFare;

    private Package $packageSingle;

    private Package $packageDouble;

    private Booking $booking;

    private Passenger $passenger;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->pvBranch();
        $this->user = $this->pvUser($branch);
        $this->district = $this->pvDistrict();
        $this->fpCharge = $this->pvFpCharge($this->district, $this->user);
        $visa = $this->pvVisaPrice($this->user, 2000.00);

        $this->singleFare = $this->pvTicketFare($this->user, ['selling_fare' => 28000.00, 'net_fare' => 25000.00]);
        $this->inboundFare = $this->pvTicketFare($this->user, ['selling_fare' => 28000.00, 'net_fare' => 25000.00]);
        $this->outboundFare = $this->pvTicketFare($this->user, ['selling_fare' => 24000.00, 'net_fare' => 21000.00]);

        $this->packageSingle = $this->pvPackage($this->user, $visa, [
            'ticket_fare_id' => $this->singleFare->id,
            'regular_price' => 30000.00,
            'offer_price' => 28000.00,
            'service_charge' => 1500.00,
        ]);

        $this->packageDouble = $this->pvPackage($this->user, $visa, [
            'is_double_ticket' => true,
            'ticket_fare_id' => $this->inboundFare->id,
            'ticket_fare_inbound_id' => $this->inboundFare->id,
            'ticket_fare_outbound_id' => $this->outboundFare->id,
            'regular_price' => 30000.00,
            'offer_price' => 28000.00,
            'service_charge' => 1500.00,
        ]);
    }

    private function makeBooking(Package $package): Booking
    {
        $this->booking = $this->pvBooking($package, $this->user, $this->fpCharge, $this->district, [
            'total_value' => 31500.00,
        ]);

        $this->invoice = app(InvoiceService::class)->createForBooking($this->booking);
        $this->invoice->updateQuietly(['total_amount' => 31500.00, 'paid_amount' => 0, 'balance' => 31500.00]);

        return $this->booking;
    }

    private function makePassenger(Booking $booking, array $overrides = []): Passenger
    {
        $this->passenger = $this->pvPassenger($booking, array_merge([
            'package_value' => 31500.00,
        ], $overrides));

        return $this->passenger;
    }

    private function makeRegularTicket(array $overrides = []): IssuedTicket
    {
        return IssuedTicket::create(array_merge([
            'booking_id' => $this->booking->id,
            'passenger_id' => $this->passenger->id,
            'user_id' => $this->user->id,
            'ticket_fare_id' => $this->singleFare->id,
            'status' => 'pending',
            'selling_fare' => 28000.00,
            'offer_price' => 0,
            'outbound_pending' => false,
        ], $overrides));
    }

    private function makeCompanion(array $overrides = []): IssuedTicket
    {
        return IssuedTicket::create(array_merge([
            'booking_id' => $this->booking->id,
            'passenger_id' => $this->passenger->id,
            'user_id' => $this->user->id,
            'ticket_fare_id' => null,
            'issue_type' => 'pending_outbound',
            'status' => 'pending',
            'selling_fare' => 24000.00,
            'offer_price' => 0,
            'outbound_pending' => false,
        ], $overrides));
    }

    private function performSwap(Package $target): void
    {
        $this->actingAs($this->user);
        $this->put(route('bookings.update', $this->booking->id), [
            'package_id' => $target->id,
        ])->assertRedirect();
    }

    public function test_swap_to_double_flags_regular_and_creates_exactly_one_companion(): void
    {
        $this->makeBooking($this->packageSingle);
        $this->makePassenger($this->booking, ['ticket_fare_id' => $this->singleFare->id]);
        $regular = $this->makeRegularTicket();

        $this->performSwap($this->packageDouble);

        $companions = IssuedTicket::where('passenger_id', $this->passenger->id)
            ->where('issue_type', 'pending_outbound')
            ->get();

        $this->assertCount(1, $companions, 'a swap to a double package creates exactly one companion');
        $this->assertTrue((bool) $regular->fresh()->outbound_pending, 'Regular row must advertise the live companion');
        $this->assertSame($this->inboundFare->id, $this->passenger->fresh()->ticket_fare_inbound_id);
        $this->assertSame($this->outboundFare->id, $this->passenger->fresh()->ticket_fare_outbound_id);
    }

    public function test_swap_to_double_rewrites_an_existing_issued_companion_without_duplicating(): void
    {
        $this->makeBooking($this->packageSingle);
        $this->makePassenger($this->booking, ['ticket_fare_id' => $this->singleFare->id]);
        $regular = $this->makeRegularTicket();
        $companion = $this->makeCompanion(['status' => 'issued', 'selling_fare' => 111.00]);

        $this->performSwap($this->packageDouble);

        $companions = IssuedTicket::where('passenger_id', $this->passenger->id)
            ->where('issue_type', 'pending_outbound')
            ->get();

        $this->assertCount(1, $companions, 'an existing companion (even issued) must block a new row');
        $this->assertSame($companion->id, $companions->first()->id, 'the existing companion is rewritten in place');
        $this->assertEqualsWithDelta(24000.00, (float) $companions->first()->selling_fare, 0.001, 'companion is re-priced');
        $this->assertTrue((bool) $regular->fresh()->outbound_pending, 'Regular row must advertise the live companion');
    }

    public function test_swap_to_single_removes_companion_and_clears_flag(): void
    {
        $this->makeBooking($this->packageDouble);
        $this->makePassenger($this->booking, [
            'ticket_fare_id' => null,
            'ticket_fare_inbound_id' => $this->inboundFare->id,
            'ticket_fare_outbound_id' => $this->outboundFare->id,
        ]);
        $regular = $this->makeRegularTicket([
            'ticket_fare_id' => null,
            'outbound_pending' => true,
        ]);
        $this->makeCompanion();

        $this->performSwap($this->packageSingle);

        $this->assertSame(
            0,
            IssuedTicket::where('passenger_id', $this->passenger->id)
                ->where('issue_type', 'pending_outbound')
                ->count(),
            'the companion is removed from the live set'
        );
        $this->assertSame(
            1,
            IssuedTicket::withTrashed()
                ->where('passenger_id', $this->passenger->id)
                ->where('issue_type', 'pending_outbound')
                ->count(),
            'the companion is soft-deleted, not hard-deleted'
        );
        $this->assertFalse((bool) $regular->fresh()->outbound_pending, 'the flag is cleared with the companion');
    }
}
