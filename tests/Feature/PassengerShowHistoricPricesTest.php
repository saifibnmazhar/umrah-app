<?php

namespace Tests\Feature;

use App\Http\Controllers\PassengerController;
use App\Models\Booking;
use App\Models\CurrencyRate;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\TicketFare;
use App\Models\VisaSubmission;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesPackageValueFixtures;
use Tests\TestCase;

/**
 * Passenger show popup: Ticket Fare (historic inbound+outbound sum) and
 * Visa Price (visa_submissions.visa_selling_price_id row, with historic
 * package fallback) — never live rows.
 */
class PassengerShowHistoricPricesTest extends TestCase
{
    use CreatesPackageValueFixtures, RefreshDatabase;

    private $user;

    private $district;

    private $fpCharge;

    private Package $package;

    private TicketFare $fare;

    private TicketFare $inboundFare;

    private TicketFare $outboundFare;

    private Booking $booking;

    private Passenger $passenger;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = $this->pvBranch();
        $this->user = $this->pvUser($branch);
        $this->district = $this->pvDistrict();
        $this->fpCharge = $this->pvFpCharge($this->district, $this->user);
        CurrencyRate::create(['user_id' => $this->user->id, 'rate' => 32.00]);

        $visaPrice = $this->pvVisaPrice($this->user, 2000.00);

        $this->fare = $this->pvTicketFare($this->user);
        $this->pvLogFare($this->fare, 'created', null, [
            'selling_fare' => '28000.000000', 'offer_price' => null,
            'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00',
            'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);

        $this->inboundFare = $this->pvTicketFare($this->user);
        $this->pvLogFare($this->inboundFare, 'created', null, [
            'selling_fare' => '15000.000000', 'offer_price' => null,
            'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00',
            'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);

        $this->outboundFare = $this->pvTicketFare($this->user);
        $this->pvLogFare($this->outboundFare, 'created', null, [
            'selling_fare' => '13000.000000', 'offer_price' => null,
            'ticket_type' => 'regular',
            'child_fare_percentage' => '70.00',
            'infant_fare_percentage' => '30.00',
        ], '2026-07-01 10:00:00', $this->user);

        $this->package = $this->pvPackage($this->user, $visaPrice, [
            'ticket_fare_id' => $this->fare->id,
            'regular_price' => 30000.00,
        ]);

        $this->booking = $this->pvBooking(
            $this->package, $this->user, $this->fpCharge, $this->district,
            ['total_value' => 31500.00]
        );
        // Anchor follows the booking's creation era: without backdating the
        // booking, historicAnchor() floors at "now" and the replay would read
        // live rows instead of the anchor-era ones under test.
        $this->booking->created_at = '2026-07-10 09:00:00';
        $this->booking->saveQuietly();
        $this->passenger = $this->pvPassenger($this->booking, [
            'ticket_fare_id' => $this->fare->id,
            'package_value' => 31500.00,
        ]);
        $this->pvSetCreatedAt($this->passenger, '2026-07-10 10:00:00');

        VisaSubmission::withoutEvents(fn () => VisaSubmission::create([
            'passenger_id' => $this->passenger->id,
            'visa_selling_price_id' => $visaPrice->id,
            'status' => 'pending',
        ]));

        $invoice = app(InvoiceService::class)->createForBooking($this->booking);
        $invoice->updateQuietly(['total_amount' => 31500.00, 'paid_amount' => 0]);
    }

    private function showPrices(Passenger $passenger): array
    {
        $this->actingAs($this->user);
        $controller = app(PassengerController::class);
        $response = $controller->show($passenger->fresh([
            'booking', 'booking.package',
            'booking.package.visaSellingPrice',
            'ticketFare', 'ticketFareInbound', 'ticketFareOutbound',
            'visaSubmission.visaSellingPrice',
        ]));

        return $response->getData();
    }

    public function test_single_ticket_uses_creation_era_fare_not_live(): void
    {
        $this->fare->updateQuietly(['selling_fare' => 30000.00]);
        $this->pvLogFare($this->fare, 'updated',
            ['selling_fare' => '28000.000000'],
            ['selling_fare' => '30000.000000'],
            '2026-08-01 10:00:00', $this->user);

        $data = $this->showPrices($this->passenger);

        $this->assertEqualsWithDelta(
            28000.00, (float) $data['ticketFare'], 0.001,
            'anchor-era fare, not live 30000'
        );
    }

    public function test_double_ticket_historic_inbound_plus_outbound_sum(): void
    {
        $this->passenger->updateQuietly([
            'ticket_fare_id' => null,
            'ticket_fare_inbound_id' => $this->inboundFare->id,
            'ticket_fare_outbound_id' => $this->outboundFare->id,
        ]);

        $this->inboundFare->updateQuietly(['selling_fare' => 17000.00]);
        $this->pvLogFare($this->inboundFare, 'updated',
            ['selling_fare' => '15000.000000'],
            ['selling_fare' => '17000.000000'],
            '2026-08-01 10:00:00', $this->user);
        $this->outboundFare->updateQuietly(['selling_fare' => 15000.00]);
        $this->pvLogFare($this->outboundFare, 'updated',
            ['selling_fare' => '13000.000000'],
            ['selling_fare' => '15000.000000'],
            '2026-08-01 10:00:00', $this->user);

        $data = $this->showPrices($this->passenger);

        $this->assertEqualsWithDelta(
            28000.00, (float) $data['ticketFare'], 0.001,
            'historic 15000+13000, not live 32000'
        );
    }

    public function test_visa_price_from_submission_row_not_package_live(): void
    {
        $newPrice = $this->pvVisaPrice($this->user, 2500.00);
        $this->package->updateQuietly(['visa_selling_price_id' => $newPrice->id]);

        $data = $this->showPrices($this->passenger);

        $this->assertEqualsWithDelta(
            2000.00, (float) $data['visaCost'], 0.001,
            'submission row price, not live package price'
        );
    }

    public function test_visa_without_submission_falls_back_to_package_historic(): void
    {
        $this->passenger->visaSubmission()->delete();

        $oldPriceId = (int) $this->package->visa_selling_price_id;
        $priceB = $this->pvVisaPrice($this->user, 2500.00);
        $this->pvLogPackage($this->package, 'created', null,
            ['visa_selling_price_id' => $oldPriceId],
            '2026-07-01 10:00:00', $this->user);
        $this->pvLogPackage($this->package, 'updated',
            ['visa_selling_price_id' => $oldPriceId],
            ['visa_selling_price_id' => $priceB->id],
            '2026-08-01 10:00:00', $this->user);
        $this->package->updateQuietly(['visa_selling_price_id' => $priceB->id]);

        $data = $this->showPrices($this->passenger);

        $this->assertEqualsWithDelta(
            2000.00, (float) $data['visaCost'], 0.001,
            'anchor-era package price, not live 2500'
        );
    }

    public function test_ticket_only_shows_zero_visa(): void
    {
        $this->passenger->updateQuietly(['service_required' => 'ticket_only']);

        $data = $this->showPrices($this->passenger);

        $this->assertEqualsWithDelta(0.0, (float) $data['visaCost'], 0.001);
    }

    public function test_visa_only_shows_zero_ticket(): void
    {
        $this->passenger->updateQuietly(
            ['service_required' => 'visa_only', 'ticket_fare_id' => null]
        );

        $data = $this->showPrices($this->passenger);

        $this->assertEqualsWithDelta(0.0, (float) $data['ticketFare'], 0.001);
    }
}
