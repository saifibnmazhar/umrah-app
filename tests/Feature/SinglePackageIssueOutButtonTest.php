<?php

namespace Tests\Feature;

use Tests\TestCase;

class SinglePackageIssueOutButtonTest extends TestCase
{
    public function test_index_blade_has_single_package_issue_out_logic(): void
    {
        $path = resource_path('views/bookings/index.blade.php');
        $html = file_get_contents($path);

        $this->assertStringContainsString('canShowInlineIssueOutSingle', $html);
        $this->assertStringContainsString('outbound_pending', $html);
        $this->assertStringContainsString('pending_outbound', $html);
        $this->assertStringContainsString('passenger_id', $html);
    }

    public function test_pending_outbound_created_with_null_fare_when_checkbox_checked(): void
    {
        $path = app_path('Http/Controllers/TicketIssueController.php');
        $code = file_get_contents($path);

        $this->assertStringContainsString("'ticket_fare_id' => null", $code);
        $this->assertStringNotContainsString("\$validated['ticket_fare_outbound_id'] ?? \$validated['ticket_fare_id']", $code);
    }

    public function test_outbound_pending_checkbox_becomes_readonly_after_save(): void
    {
        $path = resource_path('views/bookings/index.blade.php');
        $html = file_get_contents($path);

        $this->assertStringContainsString('outbound_pending_locked', $html);
        $this->assertStringContainsString('double_ticket_active || ticketFareForm.outbound_pending_locked', $html);
    }

    public function test_outbound_forms_require_ticket_selection_without_regular_fallback(): void
    {
        $index = file_get_contents(resource_path('views/bookings/index.blade.php'));
        $report = file_get_contents(resource_path('views/reports/pending-outbound.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/TicketIssueController.php'));

        $this->assertStringContainsString('Please select a ticket', $index);
        $this->assertStringContainsString('Please select a ticket', $report);
        $this->assertStringNotContainsString("ticket_fare_outbound_id' => \$validated['ticket_fare_id']", $controller);
    }

    public function test_backfill_pending_outbound_command_exists(): void
    {
        $path = app_path('Console/Commands/BackfillPendingOutbound.php');
        $this->assertFileExists($path);

        $code = file_get_contents($path);

        $this->assertStringContainsString('tickets:backfill-pending-outbound', $code);
        $this->assertStringContainsString('dry-run', $code);
        $this->assertStringContainsString('outbound_pending', $code);
        $this->assertStringContainsString('pending_outbound', $code);
        $this->assertStringContainsString("'ticket_fare_id' => null", $code);
    }
}
