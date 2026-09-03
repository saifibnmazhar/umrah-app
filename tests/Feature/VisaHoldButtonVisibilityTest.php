<?php

namespace Tests\Feature;

use App\Enums\VisaStatus;
use App\Models\Booking;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\VisaSubmission;
use App\Services\CurrencyRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class VisaHoldButtonVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private static function holdButtonCondition(): string
    {
        return "!['Hold', 'Cancel', 'Delivered'].includes(passengersTicketData[0]?.status)";
    }

    private function userWithVisaAdmin(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Visa Admin']);
        $user->roles()->attach($role);

        $this->actingAs($user);
    }

    private function makePassenger(string $statusName): Passenger
    {
        $booking = new Booking;
        $booking->setAttribute('id', 1);
        $booking->setAttribute('created_at', now());
        $booking->setAttribute('invoice_id', 'INV-1');
        $booking->setAttribute('pax_qty', 1);
        $booking->setAttribute('is_cancelled', false);
        $booking->setAttribute('discount_amount', 0);
        $booking->setAttribute('remarks', '');
        $booking->setRelation('customer', null);
        $booking->setRelation('documents', collect());
        $booking->setRelation('package', null);
        $booking->setRelation('fingerprint', null);
        $booking->setRelation('currencyRate', null);
        $booking->setRelation('invoice', null);

        $passenger = new Passenger;
        $passenger->setAttribute('id', 1);
        $passenger->setAttribute('booking_id', 1);
        $passenger->setAttribute('first_name', 'Test');
        $passenger->setAttribute('last_name', 'Pax');
        $passenger->setAttribute('passport_no', 'PASS123');
        $passenger->setAttribute('mobile_no', '');
        $passenger->setAttribute('passenger_type', 'adult');
        $passenger->setAttribute('service_required', 'all');
        $passenger->setAttribute('stay_duration', null);
        $passenger->setAttribute('package_value', 0);
        $passenger->setAttribute('profit', 0);
        $passenger->setAttribute('refund_payable', 0);
        $passenger->setAttribute('ticket_remarks', '');
        $passenger->setAttribute('is_visa_held', false);
        $passenger->setAttribute('is_ticket_held', false);
        $passenger->setAttribute('documents_count', 0);
        $passenger->setRelation('status', new PassengerStatus(['id' => 1, 'name' => $statusName]));
        $passenger->setRelation('booking', $booking);
        $passenger->setRelation('ticketFare', null);
        $passenger->setRelation('ticketFareInbound', null);
        $passenger->setRelation('ticketFareOutbound', null);
        $passenger->setRelation('allIssuedTickets', collect());
        $passenger->setRelation('fingerprintDetail', null);
        $passenger->setRelation('visaSubmission', null);

        $booking->setRelation('passengers', collect([$passenger]));

        return $passenger;
    }

    private function renderIndex(string $statusName, bool $canEditVisa = true): string
    {
        $this->userWithVisaAdmin();

        $passenger = $this->makePassenger($statusName);
        $paginator = new LengthAwarePaginator(collect([$passenger]), 1, 20, 1);

        return view('bookings.index', [
            'tab' => 'passenger',
            'bookings' => new LengthAwarePaginator(collect([]), 0, 20),
            'passengers' => $paginator,
            'passengerStatuses' => collect([
                (object) ['id' => 1, 'name' => $statusName],
            ]),
            'visaAgents' => collect([]),
            'ticketAgents' => collect([]),
            'canEditVisa' => $canEditVisa,
            'canFilterByVisaAgent' => true,
            'canFilterByTicketAgent' => true,
            'currencyRateService' => app(CurrencyRateService::class),
            'bookingBranches' => collect([]),
            'selectedBranchId' => null,
            'totalBookingCount' => 0,
            'totalBookingPassengerCount' => 0,
            'branchCounts' => collect([]),
            'allBookingCount' => 0,
            'selectedFingerprintStatus' => null,
            'selectedVisaStatus' => null,
            'selectedTicketStatus' => null,
            'selectedVisaAgentId' => null,
            'selectedBookingDateFrom' => null,
            'selectedBookingDateTo' => null,
            'selectedFingerprintLocation' => null,
            'selectedBookingStatus' => 'active',
            'selectedPassengerStatus' => null,
            'selectedRouteDisplay' => null,
            'routesList' => collect([]),
            'selectedPackageId' => null,
            'selectedTicketAgentId' => null,
            'selectedActualFlightFrom' => null,
            'selectedActualFlightTo' => null,
            'selectedReturnDateFrom' => null,
            'selectedReturnDateTo' => null,
            'selectedStatusChangeAction' => null,
            'selectedStatusChangeFrom' => null,
            'selectedStatusChangeTo' => null,
            'selectedPaymentWise' => null,
            'statusChangeOptions' => collect([
                (object) ['id' => 'visa_submitted', 'name' => 'Visa Submitted'],
                (object) ['id' => 'visa_issued', 'name' => 'Visa Issued'],
                (object) ['id' => 'ticket_issued', 'name' => 'Ticket Issued'],
            ]),
            'fingerprintStatuses' => [],
            'visaStatuses' => [],
            'ticketStatuses' => [],
            'fingerprintLocations' => [],
            'totalPassengerCount' => 0,
            'totalPackageValue' => 0,
            'totalDue' => 0,
            'totalPackageBdt' => 0,
            'totalDueBdt' => 0,
            'reIssueReasons' => collect([]),
        ])->render();
    }

    public function test_hold_button_hides_for_hold_status(): void
    {
        $html = $this->renderIndex('Hold');

        $this->assertStringContainsString(self::holdButtonCondition(), $html);
    }

    public function test_hold_button_hides_for_cancel_status(): void
    {
        $html = $this->renderIndex('Cancel');

        $this->assertStringContainsString(self::holdButtonCondition(), $html);
    }

    public function test_hold_button_hides_for_delivered_status(): void
    {
        $html = $this->renderIndex('Delivered');

        $this->assertStringContainsString(self::holdButtonCondition(), $html);
    }

    public function test_hold_button_renders_for_normal_status(): void
    {
        $html = $this->renderIndex('Processing');

        $this->assertStringContainsString(self::holdButtonCondition(), $html);
        $this->assertStringContainsString('@click="toggleVisaHold(0)"', $html);
    }

    public function test_hold_button_not_rendered_when_visa_edit_disallowed(): void
    {
        $html = $this->renderIndex('Processing', false);

        $this->assertStringNotContainsString('@click="toggleVisaHold(0)"', $html);
    }

    private function makePassengerWithIssuedVisa(string $statusName): Passenger
    {
        $booking = new Booking;
        $booking->setAttribute('id', 1);
        $booking->setAttribute('created_at', now());
        $booking->setAttribute('invoice_id', 'INV-1');
        $booking->setAttribute('pax_qty', 1);
        $booking->setAttribute('is_cancelled', false);
        $booking->setAttribute('discount_amount', 0);
        $booking->setAttribute('remarks', '');
        $booking->setRelation('customer', null);
        $booking->setRelation('documents', collect());
        $booking->setRelation('package', null);
        $booking->setRelation('fingerprint', null);
        $booking->setRelation('currencyRate', null);
        $booking->setRelation('invoice', null);

        $visaSubmission = new VisaSubmission;
        $visaSubmission->setAttribute('id', 1);
        $visaSubmission->setAttribute('status', VisaStatus::ISSUED);
        $visaSubmission->setRelation('visaAgent', null);
        $visaSubmission->setRelation('commissionAgent', null);
        $visaSubmission->setRelation('visaSellingPrice', null);
        $visaSubmission->setRelation('cancelledSubmissions', collect());

        $passenger = new Passenger;
        $passenger->setAttribute('id', 1);
        $passenger->setAttribute('booking_id', 1);
        $passenger->setAttribute('first_name', 'Test');
        $passenger->setAttribute('last_name', 'Pax');
        $passenger->setAttribute('passport_no', 'PASS123');
        $passenger->setAttribute('mobile_no', '');
        $passenger->setAttribute('passenger_type', 'adult');
        $passenger->setAttribute('service_required', 'all');
        $passenger->setAttribute('stay_duration', null);
        $passenger->setAttribute('package_value', 0);
        $passenger->setAttribute('profit', 0);
        $passenger->setAttribute('refund_payable', 0);
        $passenger->setAttribute('ticket_remarks', '');
        $passenger->setAttribute('is_visa_held', false);
        $passenger->setAttribute('is_ticket_held', false);
        $passenger->setAttribute('documents_count', 0);
        $passenger->setRelation('status', new PassengerStatus(['id' => 1, 'name' => $statusName]));
        $passenger->setRelation('booking', $booking);
        $passenger->setRelation('ticketFare', null);
        $passenger->setRelation('ticketFareInbound', null);
        $passenger->setRelation('ticketFareOutbound', null);
        $passenger->setRelation('allIssuedTickets', collect());
        $passenger->setRelation('fingerprintDetail', null);
        $passenger->setRelation('visaSubmission', $visaSubmission);

        $booking->setRelation('passengers', collect([$passenger]));

        return $passenger;
    }

    private function renderIndexWithIssuedVisa(string $statusName): string
    {
        $this->userWithVisaAdmin();

        $passenger = $this->makePassengerWithIssuedVisa($statusName);
        $paginator = new LengthAwarePaginator(collect([$passenger]), 1, 20, 1);

        return view('bookings.index', [
            'tab' => 'passenger',
            'bookings' => new LengthAwarePaginator(collect([]), 0, 20),
            'passengers' => $paginator,
            'passengerStatuses' => collect([
                (object) ['id' => 1, 'name' => $statusName],
            ]),
            'visaAgents' => collect([]),
            'ticketAgents' => collect([]),
            'canEditVisa' => true,
            'canFilterByVisaAgent' => true,
            'canFilterByTicketAgent' => true,
            'currencyRateService' => app(CurrencyRateService::class),
            'bookingBranches' => collect([]),
            'selectedBranchId' => null,
            'totalBookingCount' => 0,
            'totalBookingPassengerCount' => 0,
            'branchCounts' => collect([]),
            'allBookingCount' => 0,
            'selectedFingerprintStatus' => null,
            'selectedVisaStatus' => null,
            'selectedTicketStatus' => null,
            'selectedVisaAgentId' => null,
            'selectedBookingDateFrom' => null,
            'selectedBookingDateTo' => null,
            'selectedFingerprintLocation' => null,
            'selectedBookingStatus' => 'active',
            'selectedPassengerStatus' => null,
            'selectedRouteDisplay' => null,
            'routesList' => collect([]),
            'selectedPackageId' => null,
            'selectedTicketAgentId' => null,
            'selectedActualFlightFrom' => null,
            'selectedActualFlightTo' => null,
            'selectedReturnDateFrom' => null,
            'selectedReturnDateTo' => null,
            'selectedStatusChangeAction' => null,
            'selectedStatusChangeFrom' => null,
            'selectedStatusChangeTo' => null,
            'selectedPaymentWise' => null,
            'statusChangeOptions' => collect([
                (object) ['id' => 'visa_submitted', 'name' => 'Visa Submitted'],
                (object) ['id' => 'visa_issued', 'name' => 'Visa Issued'],
                (object) ['id' => 'ticket_issued', 'name' => 'Ticket Issued'],
            ]),
            'fingerprintStatuses' => [],
            'visaStatuses' => [],
            'ticketStatuses' => [],
            'fingerprintLocations' => [],
            'totalPassengerCount' => 0,
            'totalPackageValue' => 0,
            'totalDue' => 0,
            'totalPackageBdt' => 0,
            'totalDueBdt' => 0,
            'reIssueReasons' => collect([]),
        ])->render();
    }

    public function test_revert_button_excludes_delivered_status(): void
    {
        $html = $this->renderIndexWithIssuedVisa('Delivered');

        $revertTemplate = 'openVisaRevertModal(0)"';

        $this->assertStringContainsString($revertTemplate, $html, 'Revert button must be present in the DOM');

        $pos = strpos($html, $revertTemplate);
        $beforeRevert = substr($html, max(0, $pos - 300), 300);
        $this->assertStringContainsString("Delivered']", $beforeRevert);
    }

    public function test_revert_button_renders_for_normal_status(): void
    {
        $html = $this->renderIndexWithIssuedVisa('Processing');

        $this->assertStringContainsString('openVisaRevertModal(0)', $html);
    }
}
