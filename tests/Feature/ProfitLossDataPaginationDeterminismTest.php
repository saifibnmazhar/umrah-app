<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Concerns\CreatesProfitLossFixtures;
use Tests\TestCase;

/**
 * Part C: data() customer tab paginates in SQL with a deterministic order;
 * passenger tab is ordered by passengers.id.
 */
class ProfitLossDataPaginationDeterminismTest extends TestCase
{
    use CreatesProfitLossFixtures, RefreshDatabase;

    public function test_customer_tab_pagination_is_deterministic_across_calls(): void
    {
        $this->seedProfitLossWorld();

        for ($i = 0; $i < 30; $i++) {
            $booking = $this->fxBooking('INV-PAG-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT));
            $this->fxPassenger($booking);
        }

        Auth::login($this->fxUser);

        $fetch = fn (int $page) => $this->getJson(route('api.reports.profit-loss', [
            'tab' => 'customer',
            'page' => $page,
            'per_page' => 10,
        ]))->assertOk()->json();

        $page1a = $fetch(1);
        $page1b = $fetch(1);
        $page2 = $fetch(2);
        $page3 = $fetch(3);

        $this->assertSame(30, (int) $page1a['total']);
        $this->assertSame(3, (int) $page1a['last_page']);
        $this->assertCount(10, $page1a['data']);

        $invoices = fn (array $page) => array_column($page['data'], 'invoice_id');

        // Same request twice → byte-identical ordering.
        $this->assertSame($invoices($page1a), $invoices($page1b));

        // Deterministic descending bookings.id order (seeded order = ascending ids).
        $this->assertSame('INV-PAG-0029', $invoices($page1a)[0]);
        $this->assertSame('INV-PAG-0020', $invoices($page1a)[9]);
        $this->assertSame('INV-PAG-0019', $invoices($page2)[0]);
        $this->assertSame('INV-PAG-0000', $invoices($page3)[9]);

        // No overlap between pages.
        $overlap = array_intersect($invoices($page1a), $invoices($page2));
        $this->assertSame([], array_values($overlap));
    }

    public function test_passenger_tab_orders_by_id_and_repeats_cleanly(): void
    {
        $this->seedProfitLossWorld();

        for ($i = 0; $i < 12; $i++) {
            $booking = $this->fxBooking('INV-PP-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT));
            $this->fxPassenger($booking);
        }

        Auth::login($this->fxUser);

        $fetch = fn () => $this->getJson(route('api.reports.profit-loss', [
            'tab' => 'passenger',
            'page' => 1,
            'per_page' => 10,
        ]))->assertOk()->json();

        $first = $fetch();
        $second = $fetch();

        $ids = array_column($first['data'], 'id');
        $this->assertCount(10, $ids);
        $this->assertSame($ids, array_column($second['data'], 'id'));
        $this->assertSame($ids, array_values(array_unique($ids)));

        $sorted = $ids;
        sort($sorted);
        $this->assertSame($sorted, $ids, 'Passenger rows must be ordered by passengers.id ascending');
    }
}
