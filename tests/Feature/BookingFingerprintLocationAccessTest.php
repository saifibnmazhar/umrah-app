<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Role;
use App\Models\StayDurationLimit;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFingerprintLocationAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $fingerprintStaff;

    private Booking $booking;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Main Branch',
            'address' => 'Main Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'MAIN01',
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach(Role::create(['name' => 'Super Admin']));

        $this->fingerprintStaff = User::create([
            'name' => 'Fingerprint Staff',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);
        $this->fingerprintStaff->roles()->attach(Role::create(['name' => 'Fingerprint Staff']));

        $district = District::create(['name' => 'Test District', 'division' => 'Test Division']);
        FlightDateGap::getOrCreate();
        StayDurationLimit::getOrCreate();
        CurrencyRate::create(['user_id' => $this->admin->id, 'rate' => 25.0000]);

        $visaPrice = VisaSellingPrice::create(['user_id' => $this->admin->id, 'selling_price' => 2000.00]);
        $package = Package::create([
            'package_name' => 'P',
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 40000.00,
            'service_charge' => 500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
        $charge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->admin->id,
            'fingerprint_charge' => 300.00,
        ]);
        $customer = Customer::create([
            'name' => 'Test Customer',
            'passport_no' => 'P'.substr(uniqid(), -5),
            'iqama_type' => 'none',
            'mobile_no' => '0500000000',
            'address' => 'Addr',
        ]);

        $this->booking = Booking::create([
            'user_id' => $this->admin->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $this->branch->id,
            'district_id' => $district->id,
            'package_id' => $package->id,
            'fingerprint_charge_id' => $charge->id,
            'booking_branch_id' => $this->branch->id,
            'invoice_id' => 'INV-'.substr(uniqid(), -8),
            'date_gap_id' => FlightDateGap::getOrCreate()->id,
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 40000.00,
            'remarks' => '',
            'is_cancelled' => false,
        ]);
        Invoice::create([
            'booking_id' => $this->booking->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'total_amount' => 40000.00,
            'paid_amount' => 10000.00,
            'balance' => 30000.00,
            'status' => 'pending',
        ]);
    }

    // ── Index Page Tests ─────────────────────────────────────

    public function test_index_fingerprint_dropdown_visible_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('bookings.index', ['tab' => 'booking']));

        $response->assertOk();
        $response->assertSee('onchange="updateFingerprintLocation(', false);
    }

    public function test_index_fingerprint_dropdown_hidden_for_non_admin(): void
    {
        $response = $this->actingAs($this->fingerprintStaff)->get(route('bookings.index', ['tab' => 'booking']));

        $response->assertOk();
        $response->assertDontSee('onchange="updateFingerprintLocation(', false);
        $response->assertSee('Office', false);
    }

    // ── Edit Page Tests ──────────────────────────────────────

    public function test_edit_fingerprint_select_visible_for_admin(): void
    {
        $this->actingAs($this->admin);

        $view = view('bookings.edit', [
            'booking' => $this->booking,
            'packages' => collect([]),
            'districts' => collect([]),
            'offices' => collect([]),
            'ticketFares' => collect([]),
            'customers' => collect([]),
            'currentCurrencyRate' => null,
            'bookingBranches' => collect([$this->branch]),
            'fingerprintBranches' => collect([$this->branch]),
        ]);

        $html = $view->render();

        $this->assertStringContainsString('name="fingerprint_location"', $html);
        $this->assertStringContainsString('<option value="office">', $html);
        $this->assertStringContainsString('<option value="home">', $html);
    }

    public function test_edit_fingerprint_select_hidden_for_non_admin(): void
    {
        $this->actingAs($this->fingerprintStaff);

        $view = view('bookings.edit', [
            'booking' => $this->booking,
            'packages' => collect([]),
            'districts' => collect([]),
            'offices' => collect([]),
            'ticketFares' => collect([]),
            'customers' => collect([]),
            'currentCurrencyRate' => null,
            'bookingBranches' => collect([]),
            'fingerprintBranches' => collect([]),
        ]);

        $html = $view->render();

        // The select dropdown should NOT render the fingerprint_location <select>
        $this->assertStringNotContainsString('<select x-model="bookingData.fingerprint_location"', $html);
        // Should show static text instead
        $this->assertStringContainsString('Office', $html);
        // Should have a hidden input preserving the value
        $this->assertStringContainsString('<input type="hidden" name="fingerprint_location" value="office">', $html);
    }

    // ── Controller Update Tests ──────────────────────────────

    public function test_update_admin_can_change_fingerprint_location(): void
    {
        $this->actingAs($this->admin);

        $response = $this->put(route('bookings.update', $this->booking->id), [
            'fingerprint_location' => 'home',
            'customer_id' => $this->booking->customer_id,
            'pax_qty' => 1,
            'discount_type' => 'fixed',
            'discount_value' => 0,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'fingerprint_location' => 'home',
        ]);
    }

    public function test_update_non_admin_cannot_change_fingerprint_location(): void
    {
        $this->actingAs($this->fingerprintStaff);

        $response = $this->put(route('bookings.update', $this->booking->id), [
            'fingerprint_location' => 'home',
            'customer_id' => $this->booking->customer_id,
            'pax_qty' => 1,
        ]);

        $response->assertRedirect();
        // Fingerprint location should remain unchanged because the controller strips it
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'fingerprint_location' => 'office',
        ]);
    }

    // ── AJAX Endpoint Tests ──────────────────────────────────

    public function test_ajax_non_admin_gets_403(): void
    {
        $this->actingAs($this->fingerprintStaff);

        $response = $this->patchJson(route('bookings.fingerprint-location.update', $this->booking->id), [
            'fingerprint_location' => 'home',
        ]);

        $response->assertStatus(403);
    }

    public function test_ajax_admin_succeeds(): void
    {
        $this->actingAs($this->admin);

        $response = $this->patchJson(route('bookings.fingerprint-location.update', $this->booking->id), [
            'fingerprint_location' => 'home',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'fingerprint_location' => 'home',
        ]);
    }
}
