<?php

namespace Tests\Feature;

use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Services\ProfitCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PassengerExtraChargeTest extends TestCase
{
    use RefreshDatabase;

    private function effectiveTicketOnlyPassenger(float $base, float $extra): Passenger
    {
        $passenger = new Passenger([
            'service_required' => 'ticket_only',
            'booking_service_charge' => $base,
            'extra_charge' => $extra,
        ]);
        $passenger->setRelation('allIssuedTickets', collect([
            new IssuedTicket(['status' => 'issued', 'issue_type' => 'regular']),
        ]));

        return $passenger;
    }

    private function serviceChargeOf(Passenger $passenger): float
    {
        $service = app(ProfitCalculationService::class);
        $method = new \ReflectionMethod($service, 'calculateServiceCharge');
        $method->setAccessible(true);

        return $method->invoke($service, $passenger);
    }

    public function test_passengers_table_has_extra_charge_column(): void
    {
        $this->assertTrue(Schema::hasColumn('passengers', 'extra_charge'));
    }

    public function test_passenger_model_allows_extra_charge(): void
    {
        $passenger = new Passenger(['extra_charge' => 100]);

        $this->assertContains('extra_charge', $passenger->getFillable());
        $this->assertSame(100.0, (float) $passenger->extra_charge);
        $this->assertArrayHasKey('extra_charge', $passenger->getCasts());
    }

    public function test_service_charge_includes_extra_charge_when_effective(): void
    {
        $passenger = $this->effectiveTicketOnlyPassenger(100, 50);

        $this->assertSame(150.0, $this->serviceChargeOf($passenger));
    }

    public function test_service_charge_is_zero_when_not_effective_despite_extra_charge(): void
    {
        $passenger = new Passenger([
            'service_required' => 'ticket_only',
            'booking_service_charge' => 100,
            'extra_charge' => 50,
        ]);
        $passenger->setRelation('allIssuedTickets', collect([]));

        $this->assertSame(0.0, $this->serviceChargeOf($passenger));
    }
}
