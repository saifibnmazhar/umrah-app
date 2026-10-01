<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReIssueDateValidationTest extends TestCase
{
    private function passengerIndexView(): string
    {
        return file_get_contents(resource_path('views/bookings/index.blade.php'));
    }

    private function confirmationView(): string
    {
        return file_get_contents(resource_path('views/re-issues/confirmation.blade.php'));
    }

    private function extractSlice(string $source, string $startNeedle, string $endNeedle): string
    {
        $start = strpos($source, $startNeedle);
        $end = strpos($source, $endNeedle);

        $this->assertNotFalse($start, "Start marker [{$startNeedle}] not found.");
        $this->assertNotFalse($end, "End marker [{$endNeedle}] not found.");
        $this->assertGreaterThan($start, $end, 'End marker occurs before start marker.');

        return substr($source, $start, $end - $start);
    }

    private function reIssueSubmitSlice(): string
    {
        return $this->extractSlice(
            $this->passengerIndexView(),
            'handleReIssueSubmit() {',
            'handleTicketFareRouteTypeChange() {'
        );
    }

    private function confirmProcessSlice(): string
    {
        return $this->extractSlice(
            $this->confirmationView(),
            'function confirmProcess() {',
            'function rejectReIssue('
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Form 1: Passenger index reissue form (bookings/index.blade.php)
    |--------------------------------------------------------------------------
    */

    public function test_passenger_index_reissue_requires_visible_inbound_outbound_dates(): void
    {
        $slice = $this->reIssueSubmitSlice();

        $this->assertStringContainsString("'Inbound date is required'", $slice);
        $this->assertStringContainsString("'Outbound date is required'", $slice);
    }

    public function test_passenger_index_reissue_date_validation_is_route_type_aware(): void
    {
        $slice = $this->reIssueSubmitSlice();

        $this->assertStringContainsString("!== 'One Way-Outbound'", $slice);
        $this->assertStringContainsString("!== 'One Way-Inbound'", $slice);
        $this->assertMatchesRegularExpression(
            "/showInboundDate\s*=\s*!form\.route_type\s*\|\|\s*form\.route_type\s*!==\s*'One Way-Outbound'/",
            $slice
        );
        $this->assertMatchesRegularExpression(
            "/showOutboundDate\s*=\s*!form\.route_type\s*\|\|\s*form\.route_type\s*!==\s*'One Way-Inbound'/",
            $slice
        );
    }

    public function test_passenger_index_reissue_validates_date_format(): void
    {
        $slice = $this->reIssueSubmitSlice();

        $this->assertStringContainsString('parseDDMMMYY(form.inbound_date)', $slice);
        $this->assertStringContainsString('parseDDMMMYY(form.outbound_date)', $slice);
        $this->assertStringContainsString("'Inbound date must be in DD-MMM-YY format'", $slice);
        $this->assertStringContainsString("'Outbound date must be in DD-MMM-YY format'", $slice);
    }

    public function test_passenger_index_reissue_date_fields_have_labels_and_inline_errors(): void
    {
        $view = $this->passengerIndexView();

        $this->assertStringContainsString('>Inbound Date *</label>', $view);
        $this->assertStringContainsString('>Outbound Date *</label>', $view);
        $this->assertStringContainsString('reIssueForm.errors.inbound_date', $view);
        $this->assertStringContainsString('reIssueForm.errors.outbound_date', $view);
    }

    /*
    |--------------------------------------------------------------------------
    | Form 2: Reissue process confirmation form (re-issues/confirmation.blade.php)
    |--------------------------------------------------------------------------
    */

    public function test_confirmation_form_gates_dates_on_field_visibility(): void
    {
        $slice = $this->confirmProcessSlice();

        $this->assertStringContainsString("getElementById('fieldUpDate').classList.contains('hidden')", $slice);
        $this->assertStringContainsString("getElementById('fieldDownDate').classList.contains('hidden')", $slice);
        $this->assertStringContainsString("'Inbound date is required'", $slice);
        $this->assertStringContainsString("'Outbound date is required'", $slice);
    }

    public function test_confirmation_form_validates_date_format(): void
    {
        $slice = $this->confirmProcessSlice();

        $this->assertStringContainsString("'Inbound date must be in DD-MMM-YY format'", $slice);
        $this->assertStringContainsString("'Outbound date must be in DD-MMM-YY format'", $slice);
        $this->assertStringContainsString('parseDDMMMYY(inboundRaw)', $slice);
        $this->assertStringContainsString('parseDDMMMYY(outboundRaw)', $slice);
    }

    public function test_confirmation_form_requires_issue_date_agent_payment_by_and_charge(): void
    {
        $slice = $this->confirmProcessSlice();

        $this->assertStringContainsString("'Issue date is required'", $slice);
        $this->assertStringContainsString("'Please select a ticket agent'", $slice);
        $this->assertStringContainsString("'Please select a payment method'", $slice);
        $this->assertStringContainsString("'Re-issue charge is required'", $slice);
    }

    public function test_confirmation_form_has_inline_error_helpers(): void
    {
        $view = $this->confirmationView();

        $this->assertStringContainsString('function setFieldError', $view);
        $this->assertStringContainsString('function clearFieldError', $view);
        $this->assertStringContainsString('function clearFieldErrors', $view);
        $this->assertStringContainsString('data-error-for', $view);
    }

    public function test_confirmation_form_date_labels_are_marked_required(): void
    {
        $view = $this->confirmationView();

        $this->assertStringContainsString('>Inbound Date *</label>', $view);
        $this->assertStringContainsString('>Outbound Date *</label>', $view);
    }
}
