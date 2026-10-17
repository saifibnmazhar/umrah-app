<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceUpdateLog;
use App\Models\Package;
use App\Models\Passenger;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * Test 4 (plan 17): a package swap re-derives ALL passengers from the
 * earliest era of the target package — a never-used target has no earlier
 * era, so its first era IS the swap moment (live prices), re-sums booking
 * totals and applies the invoice delta.
 */
class PackageSwapRecalculationTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private Package $packageA;

    private Package $packageB;

    private Booking $booking;

    private Passenger $passenger;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->pvBranch();
        $this->user = $this->pvUser($branch);
        $district = $this->pvDistrict();
        $fpCharge = $this->pvFpCharge($district, $this->user);
        $visaA = $this->pvVisaPrice($this->user, 2000.00);
        $visaB = $this->pvVisaPrice($this->user, 2500.00);

        $fare = $this->pvTicketFare($this->user);

        $this->packageA = $this->pvPackage($this->user, $visaA, [
            'ticket_fare_id' => $fare->id, 'regular_price' => 30000.00,
        ]);
        $this->packageB = $this->pvPackage($this->user, $visaB, [
            'ticket_fare_id' => $fare->id, 'regular_price' => 30500.00,
        ]);

        $this->booking = $this->pvBooking($this->packageA, $this->user, $fpCharge, $district, [
            'total_value' => 31500.00,
        ]);
        $this->passenger = $this->pvPassenger($this->booking, [
            'ticket_fare_id' => $fare->id,
            'package_value' => 31500.00, // 28000 + 2000 + 1500
        ]);
        $this->invoice = app(InvoiceService::class)->createForBooking($this->booking);
        $this->invoice->updateQuietly(['total_amount' => 31500.00, 'paid_amount' => 0]);
    }

    public function test_swap_recalculates_all_passengers_totals_and_invoice(): void
    {
        $this->actingAs($this->user);

        $this->put(route('bookings.update', $this->booking->id), [
            'package_id' => $this->packageB->id,
        ])->assertRedirect();

        // Never-used target: first era = the swap moment => live prices of
        // package B: 28000 + 2500 + 1500.
        $expected = 32000.00;

        $this->assertEqualsWithDelta($expected, (float) $this->passenger->fresh()->package_value, 0.001, 'package_value');
        $this->assertEqualsWithDelta($expected, (float) $this->booking->fresh()->total_value, 0.001, 'total_value (office => no fp charge)');
        $this->assertEqualsWithDelta($expected, (float) $this->invoice->fresh()->total_amount, 0.001, 'invoice total_amount');

        $this->assertTrue(
            InvoiceUpdateLog::where('invoice_id', $this->invoice->id)
                ->where('reason', 'booking_updated')
                ->exists(),
            'swap invoice delta must be audit-logged'
        );
    }

    public function test_swap_recalculates_multiple_passengers_all_at_now(): void
    {
        $fare = $this->packageA->ticketFare;
        $second = $this->pvPassenger($this->booking, [
            'ticket_fare_id' => $fare->id,
            'passenger_type' => 'child',
            'package_value' => 23100.00, // 28000*0.7 + 2000 + 1500
        ]);
        $this->booking->updateQuietly(['total_value' => 54600.00, 'pax_qty' => 2]);
        $this->invoice->updateQuietly(['total_amount' => 54600.00]);

        $this->actingAs($this->user);
        $this->put(route('bookings.update', $this->booking->id), [
            'package_id' => $this->packageB->id,
        ])->assertRedirect();

        $expectedAdult = 32000.00;               // 28000 + 2500 + 1500
        $expectedChild = 28000.00 * 0.7 + 4000;  // fare*70% + visa 2500 + service 1500

        $this->assertEqualsWithDelta($expectedAdult, (float) $this->passenger->fresh()->package_value, 0.001);
        $this->assertEqualsWithDelta($expectedChild, (float) $second->fresh()->package_value, 0.001);
        $this->assertEqualsWithDelta($expectedAdult + $expectedChild, (float) $this->booking->fresh()->total_value, 0.001);
        $this->assertEqualsWithDelta($expectedAdult + $expectedChild, (float) $this->invoice->fresh()->total_amount, 0.001);
    }

    public function test_booking_edit_without_swap_recalculates_nothing(): void
    {
        $this->actingAs($this->user);

        $this->put(route('bookings.update', $this->booking->id), ['remarks' => 'no swap'])
            ->assertRedirect();

        $this->assertEqualsWithDelta(31500.00, (float) $this->passenger->fresh()->package_value, 0.001);
        $this->assertEqualsWithDelta(31500.00, (float) $this->booking->fresh()->total_value, 0.001);
        $this->assertEqualsWithDelta(31500.00, (float) $this->invoice->fresh()->total_amount, 0.001);
    }
}
