<?php

namespace Tests\Unit;

use App\Concerns\FiltersBookingStatus;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FiltersBookingStatusTest extends TestCase
{
    use FiltersBookingStatus;

    protected function setUp(): void
    {
        parent::setUp();

        // Drop existing migration-created tables to define our own schema.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('cancelled_bookings');
        Schema::dropIfExists('bookings');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_id')->unique();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
        });
        Schema::create('cancelled_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained();
            $table->string('status');
            $table->timestamps();
        });

        // Temporarily unguard so we can create minimal records.
        Booking::unguarded(fn () => null);
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('cancelled_bookings');
        Schema::dropIfExists('bookings');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function booking(string $invoiceId, bool $isCancelled = false): Booking
    {
        return Booking::create([
            'invoice_id' => $invoiceId,
            'is_cancelled' => $isCancelled,
        ]);
    }

    private function cancelledBooking(Booking $booking, string $status): void
    {
        DB::table('cancelled_bookings')->insert([
            'booking_id' => $booking->id,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function apply(Builder $query, ?string $status): Builder
    {
        return $this->scopeBookingStatus($query, $status);
    }

    public function test_active_excludes_cancelled(): void
    {
        $this->booking('A001', false);
        $this->booking('A002', true);

        $this->assertSame(1, $this->apply(Booking::query(), 'active')->count());
    }

    public function test_cancellation_processing(): void
    {
        $b1 = $this->booking('A001', true);
        $this->booking('A002', false);

        $this->cancelledBooking($b1, 'cancellation processing');

        $this->assertSame(1, $this->apply(Booking::query(), 'cancellation_processing')->count());
    }

    public function test_cancelled_includes_no_cancelled_booking_record(): void
    {
        $this->booking('A001', true);
        $this->booking('A002', true);

        $this->assertSame(2, $this->apply(Booking::query(), 'cancelled')->count());
    }

    public function test_cancelled_excludes_processing(): void
    {
        $b1 = $this->booking('A001', true);
        $b2 = $this->booking('A002', true);
        $this->booking('A003', true);

        $this->cancelledBooking($b1, 'cancellation processing');
        $this->cancelledBooking($b2, 'cancelled');

        $this->assertSame(2, $this->apply(Booking::query(), 'cancelled')->count());
    }

    public function test_null_status_returns_all(): void
    {
        $this->booking('A001', false);
        $this->booking('A002', true);

        $this->assertSame(2, $this->apply(Booking::query(), null)->count());
    }

    public function test_unrecognized_status_returns_all(): void
    {
        $this->booking('A001', false);
        $this->booking('A002', true);

        $this->assertSame(2, $this->apply(Booking::query(), 'unknown')->count());
    }

    public function test_trait_has_both_scope_methods(): void
    {
        $this->assertTrue(method_exists($this, 'scopeBookingStatus'));
        $this->assertTrue(method_exists($this, 'scopeBookingStatusViaBooking'));
    }
}
