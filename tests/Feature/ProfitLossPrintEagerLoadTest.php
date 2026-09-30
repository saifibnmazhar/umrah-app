<?php

namespace Tests\Feature;

use App\Http\Controllers\ProfitLossReportController;
use App\Models\IssuedTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\Feature\Concerns\CreatesProfitLossFixtures;
use Tests\TestCase;

/**
 * Part B: print eager-load constants + no issued_ticket_logs queries on the
 * effective paths (A4 + B3).
 */
class ProfitLossPrintEagerLoadTest extends TestCase
{
    use CreatesProfitLossFixtures, RefreshDatabase;

    public function test_print_and_data_constants_include_cancelled_booking_and_status(): void
    {
        $ref = new ReflectionClass(ProfitLossReportController::class);

        $data = $ref->getConstant('BOOKING_WITHS');
        $print = $ref->getConstant('PRINT_BOOKING_WITHS');
        $effective = $ref->getConstant('PRINT_EFFECTIVE_WITHS');

        $this->assertContains('cancelledBooking', $data);
        $this->assertContains('passengers.status', $data);
        $this->assertNotContains('fingerprintCharge', $data);

        foreach ([$print, $effective] as $constant) {
            $this->assertContains('cancelledBooking', $constant,
                'cancelledBooking must be eager in both print constants');
            $this->assertContains('passengers.status', $constant);
        }

        foreach (['ticketFare', 'reIssuedTickets', 'refundedTickets'] as $relation) {
            $this->assertContains('passengers.allIssuedTickets.'.$relation, $effective,
                'PRINT_EFFECTIVE_WITHS must eager-load allIssuedTickets.'.$relation);
        }
    }

    public function test_effective_paths_never_touch_issued_ticket_logs(): void
    {
        $this->seedProfitLossWorld();
        $booking = $this->fxBooking('INV-EFFLOG-1');
        $passenger = $this->fxPassenger($booking);
        $this->fxSetProfit($passenger, 1000.0, now()->subDays(2)->toDateTimeString());

        IssuedTicket::create([
            'passenger_id' => $passenger->id,
            'booking_id' => $booking->id,
            'user_id' => $this->fxUser->id,
            'ticket_fare_id' => $this->fxDeps['fare']->id,
            'selling_fare' => 30000.00,
            'offer_price' => 26000.00,
            'net_fare' => 24000.00,
            'status' => 'issued',
            'issue_type' => 'additional',
            'issued_date' => now()->toDateString(),
        ]);

        Auth::login($this->fxUser);

        $effective = [
            'effective_date_from' => now()->subDays(30)->toDateString(),
            'effective_date_to' => now()->toDateString(),
        ];

        $requests = [
            'summary' => fn () => $this->getJson(route('api.reports.profit-loss.summary', $effective)),
            'data' => fn () => $this->getJson(route('api.reports.profit-loss', $effective + ['tab' => 'passenger'])),
            'print' => fn () => $this->get(route('report.profit-loss.print', $effective + ['type' => 'passenger'])),
        ];

        foreach ($requests as $label => $request) {
            DB::enableQueryLog();
            $response = $request();
            DB::disableQueryLog();

            $response->assertOk();
            $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
            DB::flushQueryLog();

            $this->assertStringNotContainsString('issued_ticket_logs', $sql,
                "{$label} (effective mode) must not query issued_ticket_logs");
        }
    }
}
