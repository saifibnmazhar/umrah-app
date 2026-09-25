<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisaAdminPriceTabTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        $branch = Branch::create([
            'name' => 'PB', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'PB01',
        ]);
        $user = User::create([
            'name' => 'Super Admin', 'email' => uniqid().'@example.com',
            'password' => bcrypt('password'), 'is_active' => true, 'branch_id' => $branch->id,
        ]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'Super Admin']));

        return $user;
    }

    public function test_visa_admin_page_renders_with_price_tab_routes(): void
    {
        $admin = $this->makeSuperAdmin();

        $price = VisaSellingPrice::create(['user_id' => $admin->id, 'selling_price' => 999.00]);

        $this->actingAs($admin)->get(route('visa.admin', ['tab' => 'visa-selling-prices']))
            ->assertOk()
            ->assertSee(route('visa-selling-prices.store'), false)
            ->assertSee(route('visa-selling-prices.destroy', $price), false);

        $this->post(route('visa-selling-prices.store'), ['selling_price' => 123.45])
            ->assertRedirect(route('visa.admin', ['tab' => 'visa-selling-prices']));

        $this->assertDatabaseHas('visa_selling_prices', ['selling_price' => 123.45]);
    }

    public function test_disabled_visa_selling_prices_urls_redirect_to_visa_admin_tab(): void
    {
        $admin = $this->makeSuperAdmin();
        $target = route('visa.admin', ['tab' => 'visa-selling-prices']);

        foreach (['/visa-selling-prices', '/visa-selling-prices/create', '/visa-selling-prices/123', '/visa-selling-prices/123/edit'] as $uri) {
            $this->actingAs($admin)->get($uri)->assertRedirect($target);
        }
    }
}
