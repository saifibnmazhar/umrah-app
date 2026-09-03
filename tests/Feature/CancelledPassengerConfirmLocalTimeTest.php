<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CancelledPassenger;
use App\Models\Invoice;
use App\Models\Passenger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CancelledPassengerConfirmLocalTimeTest extends TestCase
{
    use DatabaseTransactions;

    private array $createdTables = [];

    protected function setUp(): void
    {
        parent::setUp();

        // layouts.app resolves the current currency rate on every render.
        if (! Schema::hasTable('currency_rates')) {
            Schema::create('currency_rates', function ($table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->decimal('rate', 10, 2);
                $table->timestamps();
            });
            $this->createdTables[] = 'currency_rates';
        }

        if (! Schema::hasTable('stay_duration_limits')) {
            Schema::create('stay_duration_limits', function ($table) {
                $table->id();
                $table->integer('min_days')->nullable();
                $table->integer('max_days')->nullable();
            });
            $this->createdTables[] = 'stay_duration_limits';
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTables as $table) {
            Schema::dropIfExists($table);
        }
        $this->createdTables = [];

        parent::tearDown();
    }

    public function test_confirm_page_renders_utc_iso_timestamp_for_browser_conversion(): void
    {
        $createdAt = Carbon::rawParse('2026-08-23 00:54:36', 'UTC');

        $booking = new Booking;
        $booking->id = 101;
        $booking->invoice_id = '(###)-127826';
        $booking->setRelation('customer', null);

        $invoice = new Invoice;
        $invoice->balance = '100';

        $passenger = new Passenger;
        $passenger->first_name = 'John';
        $passenger->last_name = 'Doe';

        $cancelled = new CancelledPassenger;
        $cancelled->id = 3;
        $cancelled->package_value = '3000';
        $cancelled->additional_ticket_value = '2000';
        $cancelled->total_passenger_due = '5000';
        $cancelled->refundable_amount = '100';
        $cancelled->created_at = $createdAt;
        $cancelled->setRelation('booking', $booking);
        $cancelled->setRelation('invoice', $invoice);
        $cancelled->setRelation('passenger', $passenger);
        $cancelled->setRelation('user', null);
        $cancelled->setRelation('cancellationBranch', null);

        $html = view('cancelled-passengers.confirm', [
            'cancelledPassenger' => $cancelled,
        ])->render();

        // The raw GMT timestamp must reach the browser as an unambiguous ISO
        // string so client-side JS can convert it to local time. A preformatted
        // server-time string cannot be converted reliably in the browser.
        $this->assertStringContainsString($createdAt->toIso8601String(), $html);
    }
}
