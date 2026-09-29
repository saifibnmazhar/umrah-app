<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCustomerSuggestionSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        $branch = Branch::create([
            'name' => 'Main Admin Branch',
            'address' => 'Admin Address',
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
        $user->roles()->attach(Role::create(['name' => 'admin']));

        return $user;
    }

    private function makeBooking(?int $packageId): Booking
    {
        $booking = new Booking;
        $booking->id = 1;
        $booking->fill([
            'package_id' => $packageId,
            'fingerprint_location' => 'office',
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 0,
            'pax_qty' => 1,
            'remarks' => '',
        ]);
        $booking->setRelation('passengers', collect([]));
        $booking->setRelation('customer', null);
        $booking->setRelation('documents', collect([]));
        $booking->setRelation('payments', collect([]));

        return $booking;
    }

    private function assertCustomerSuggestionSelectionFix(string $html): void
    {
        $this->assertStringContainsString('@mousedown.prevent="selectCustomer(customer)"', $html);
        $this->assertStringContainsString('@click="selectCustomer(customer)"', $html);
        $this->assertStringContainsString(':key="customer.id"', $html);
        $this->assertStringContainsString('@focus="handleCustomerFocus()"', $html);
        $this->assertStringContainsString('@blur="handleCustomerBlur()"', $html);
        $this->assertStringNotContainsString('setTimeout(() => { customerInputFocused', $html);
    }

    public function test_create_page_selects_customer_on_mousedown_before_blur(): void
    {
        $response = $this->actingAs($this->createUser())->get('/bookings/create');

        $response->assertOk();
        $this->assertCustomerSuggestionSelectionFix($response->getContent());
    }

    public function test_edit_page_selects_customer_on_mousedown_before_blur(): void
    {
        $view = view('bookings.edit', [
            'booking' => $this->makeBooking(null),
            'packages' => collect([]),
            'districts' => collect([]),
            'offices' => collect([]),
            'ticketFares' => collect([]),
            'customers' => collect([]),
            'currentCurrencyRate' => null,
            'bookingBranches' => collect([]),
            'fingerprintBranches' => collect([]),
        ]);

        $this->assertCustomerSuggestionSelectionFix($view->render());
    }
}
