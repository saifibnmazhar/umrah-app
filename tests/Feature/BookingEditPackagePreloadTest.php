<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingEditPackagePreloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('offices', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    private function makeBooking(?int $packageId): Booking
    {
        $booking = new Booking();
        $booking->id = 1;
        $booking->fill([
            'package_id' => $packageId,
            'fingerprint_location' => 'office',
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 0,
            'pax_qty' => 1,
            'remarks' => '',
        ]);
        $booking->setRelation('passengers', collect([]));
        $booking->setRelation('customer', null);
        $booking->setRelation('documents', collect([]));
        $booking->setRelation('payments', collect([]));

        return $booking;
    }

    public function test_blade_renders_preselected_package_id(): void
    {
        $booking = $this->makeBooking(5);

        $packages = collect([
            ['id' => 1, 'package_name' => 'Package A'],
            ['id' => 5, 'package_name' => 'Package E'],
        ]);

        $view = view('bookings.edit', [
            'booking' => $booking,
            'packages' => $packages,
            'districts' => collect([]),
            'offices' => collect([]),
            'ticketFares' => collect([]),
            'customers' => collect([]),
            'currentCurrencyRate' => null,
            'bookingBranches' => collect([]),
            'fingerprintBranches' => collect([]),
        ]);

        $html = $view->render();

        $this->assertStringContainsString('preSelectedPackageId: 5', $html);
    }

    public function test_blade_renders_null_when_no_package(): void
    {
        $booking = $this->makeBooking(null);

        $view = view('bookings.edit', [
            'booking' => $booking,
            'packages' => collect([]),
            'districts' => collect([]),
            'offices' => collect([]),
            'ticketFares' => collect([]),
            'customers' => collect([]),
            'currentCurrencyRate' => null,
            'bookingBranches' => collect([]),
            'fingerprintBranches' => collect([]),
        ]);

        $html = $view->render();

        $this->assertStringContainsString('preSelectedPackageId: null', $html);
    }

    public function test_blade_preselected_id_matches_attribute_value(): void
    {
        $booking = $this->makeBooking(42);

        $packages = collect([
            ['id' => 42, 'package_name' => 'Selected Package'],
        ]);

        $view = view('bookings.edit', [
            'booking' => $booking,
            'packages' => $packages,
            'districts' => collect([]),
            'offices' => collect([]),
            'ticketFares' => collect([]),
            'customers' => collect([]),
            'currentCurrencyRate' => null,
            'bookingBranches' => collect([]),
            'fingerprintBranches' => collect([]),
        ]);

        $html = $view->render();

        $this->assertStringContainsString('preSelectedPackageId: 42', $html);
        $this->assertStringContainsString('"package_id":42', $html);
    }
}