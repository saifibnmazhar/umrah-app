<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProfitLossReportFingerprintModalTest extends TestCase
{
    public function test_customer_tab_fingerprint_profit_cell_opens_breakdown(): void
    {
        $html = view('reports.profit-loss')->render();

        $this->assertStringContainsString('openFingerprintBreakdown(row)', $html);
    }

    public function test_fingerprint_modal_shows_effective_breakdown_fields(): void
    {
        $html = view('reports.profit-loss')->render();

        foreach ([
            'Fingerprint Location',
            'Fingerprint Charge',
            'Fingerprint Cost',
            'selectedFingerprint.profit',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function test_fingerprint_modal_shows_profit_not_effective_state(): void
    {
        $html = view('reports.profit-loss')->render();

        $this->assertStringContainsString('profit not effective', $html);
        $this->assertStringContainsString('selectedFingerprint.reason', $html);
        $this->assertStringContainsString('selectedFingerprint.effective', $html);
    }

    public function test_customer_tab_passenger_profit_cell_opens_breakdown(): void
    {
        $html = view('reports.profit-loss')->render();

        $this->assertStringContainsString('openPassengerProfitBreakdown(row)', $html);
    }

    public function test_passenger_profit_modal_lists_passengers_and_total(): void
    {
        $html = view('reports.profit-loss')->render();

        foreach ([
            'passengerProfitModalOpen',
            'selectedPassengerProfit.passengers',
            'selectedPassengerProfit.passenger_profit_total',
            'Total Passenger Profit',
            'p.effective',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function test_summary_cards_show_total_customer_and_passenger_counts(): void
    {
        $html = view('reports.profit-loss')->render();

        $this->assertStringContainsString('Total Customers', $html);
        $this->assertStringContainsString('filteredCustomers.length', $html);
        $this->assertStringContainsString('Total Passengers', $html);
        $this->assertStringContainsString('filteredPassengers.length', $html);
    }
}
