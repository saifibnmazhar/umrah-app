<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReIssueFormReadonlyFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
    }

    private function createUser(): User
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Addr',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::create(['name' => 'Ticket Admin']));

        return $user;
    }

    private function bookingsIndexHtml(): string
    {
        $this->actingAs($this->user);

        return $this->get(route('bookings.index'))->getContent();
    }

    private function confirmationSource(): string
    {
        return (string) file_get_contents(resource_path('views/re-issues/confirmation.blade.php'));
    }

    private function methodBody(string $html, string $start, string $end): string
    {
        $i = strpos($html, $start);
        $this->assertNotFalse($i, "start marker not found: {$start}");
        $j = strpos($html, $end, $i);
        $this->assertNotFalse($j, "end marker not found: {$end}");

        return substr($html, $i, $j - $i);
    }

    public function test_reissue_modal_locks_four_selector_fields(): void
    {
        $html = $this->bookingsIndexHtml();

        $this->assertStringContainsString(':disabled="!!reIssueForm.ticket_type"', $html,
            'Re-Issue ticket type must be readonly');
        $this->assertStringContainsString(':disabled="!!reIssueForm.route_type"', $html,
            'Re-Issue route type must be readonly');
        $this->assertStringContainsString(':disabled="!!reIssueForm.flight_type"', $html,
            'Re-Issue flight type must be readonly');
        $this->assertStringContainsString(':disabled="!!reIssueForm.ticket_option"', $html,
            'Re-Issue ticket select must be readonly');
    }

    public function test_edit_modal_locks_four_selector_fields_for_reissued_edits(): void
    {
        $html = $this->bookingsIndexHtml();

        $this->assertStringContainsString(':disabled="isEditingReIssued && !!ticketFareForm.ticket_type"', $html,
            'Edit ticket type must be readonly for re-issued edits');
        $this->assertStringContainsString(':disabled="ticketFareForm.isOutboundMode || (isEditingReIssued && !!ticketFareForm.route_type)"', $html,
            'Edit route type must be readonly for re-issued edits');
        $this->assertStringContainsString(':disabled="isEditingReIssued && !!ticketFareForm.flight_type"', $html,
            'Edit flight type must be readonly for re-issued edits');
        $this->assertStringContainsString(':disabled="isEditingReIssued && !!ticketFareForm.ticket_option"', $html,
            'Edit ticket select must be readonly for re-issued edits');
    }

    public function test_reissue_modal_fares_are_sourced_from_issued_ticket(): void
    {
        $html = $this->bookingsIndexHtml();
        $body = $this->methodBody($html, 'openReIssueModal(rowIndex, ticket) {', 'closeReIssueModal() {');

        $this->assertStringContainsString('this.reIssueForm.selling_fare = ticket.selling_fare', $body,
            'selling fare must be sourced from the issued ticket');
        $this->assertStringContainsString('this.reIssueForm.offer_price = ticket.offer_price', $body,
            'offer price must be sourced from the issued ticket');
        $this->assertStringNotContainsString('fareSrc.selling_fare', $body,
            'selling fare must not be sourced from the latest re-issued snapshot');
        $this->assertStringNotContainsString('fareSrc.offer_price', $body,
            'offer price must not be sourced from the latest re-issued snapshot');
    }

    public function test_edit_modal_fares_are_sourced_from_issued_ticket(): void
    {
        $html = $this->bookingsIndexHtml();

        $inboundBody = $this->methodBody($html, 'openTicketFareModal(rowIndex, ticket = null) {', 'openTicketInfoModal(');
        $this->assertStringContainsString('this.ticketFareForm.selling_fare = lit.selling_fare', $inboundBody,
            'inbound edit selling fare must be sourced from the issued ticket');
        $this->assertStringContainsString('this.ticketFareForm.offer_price = lit.offer_price', $inboundBody,
            'inbound edit offer price must be sourced from the issued ticket');
        $this->assertStringNotContainsString('this.ticketFareForm.selling_fare = src.selling_fare', $inboundBody,
            'inbound edit selling fare must not be sourced from the latest re-issued snapshot');
        $this->assertStringNotContainsString('this.ticketFareForm.offer_price = src.offer_price', $inboundBody,
            'inbound edit offer price must not be sourced from the latest re-issued snapshot');
        $this->assertStringContainsString('this.ticketFareForm.net_fare = src.net_fare', $inboundBody,
            'net fare sourcing must remain unchanged');

        $outboundBody = $this->methodBody($html, 'openOutboundEditTicketFareModal(rowIndex) {', 'openTicketFareModal(rowIndex, ticket = null) {');
        $handleAt = strpos($outboundBody, 'this.handleTicketOptionChange();');
        $reapplyAt = strrpos($outboundBody, 'this.ticketFareForm.selling_fare = poit.selling_fare');
        $this->assertNotFalse($handleAt, 'handleTicketOptionChange call not found in outbound edit');
        $this->assertNotFalse($reapplyAt, 'issued-ticket selling fare re-apply not found in outbound edit');
        $this->assertGreaterThan($handleAt, $reapplyAt,
            'issued-ticket selling/offer fares must be re-applied after handleTicketOptionChange');
        $this->assertNotFalse(strrpos($outboundBody, 'this.ticketFareForm.offer_price = poit.offer_price'),
            'issued-ticket offer price must be re-applied after handleTicketOptionChange');
    }

    public function test_confirmation_form_locks_four_selector_fields(): void
    {
        $src = $this->confirmationSource();

        $this->assertStringContainsString('id="inputRouteType" disabled', $src,
            'confirmation route type must be readonly');
        $this->assertStringContainsString('id="inputTicketType" disabled', $src,
            'confirmation ticket type must be readonly');
        $this->assertStringContainsString('id="inputFlightType" disabled', $src,
            'confirmation flight type must be readonly');
        $this->assertStringContainsString('id="inputTicketFare" disabled', $src,
            'confirmation ticket select must be readonly');
    }

    public function test_confirmation_form_unlocks_empty_selects_and_sources_fares_from_issued_ticket(): void
    {
        $src = $this->confirmationSource();

        $this->assertStringContainsString('rtSelect.disabled = !!originalRt;', $src,
            'route type stays locked only when it has a value');
        $this->assertStringContainsString('ttSelect.disabled = !!originalTt;', $src,
            'ticket type stays locked only when it has a value');
        $this->assertStringContainsString('ftSelect.disabled = !!originalFt;', $src,
            'flight type stays locked only when it has a value');
        $this->assertStringContainsString("document.getElementById('inputTicketFare').disabled = !!selectedTicketFareId;", $src,
            'ticket select stays locked only when it has a value');

        $this->assertStringContainsString('selling_fare: t.selling_fare ?? 0', $src,
            'confirmation selling fare must be sourced from the issued ticket');
        $this->assertStringContainsString('offer_price: t.offer_price ?? 0', $src,
            'confirmation offer price must be sourced from the issued ticket');
        $this->assertStringContainsString('net_fare: src.net_fare ?? 0', $src,
            'net fare sourcing must remain unchanged');
        $this->assertStringContainsString('var sf = sourceFares.selling_fare;', $src,
            'syncFareFields selling fare must come from the issued-ticket source fares');
        $this->assertStringContainsString('var ofp = sourceFares.offer_price;', $src,
            'syncFareFields offer price must come from the issued-ticket source fares');
    }
}
