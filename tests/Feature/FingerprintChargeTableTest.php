<?php

namespace Tests\Feature;

use App\Livewire\Settings\FingerprintChargeTable;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class FingerprintChargeTableTest extends TestCase
{
    use RefreshDatabase;

    private ?User $user = null;

    private function setupUser(): User
    {
        if ($this->user) {
            return $this->user;
        }
        $this->user = User::create([
            'name' => 'Admin Settings',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

        return $this->user;
    }

    private function createCharge(string $division, string $districtName): FingerprintCharge
    {
        $district = District::create(['division' => $division, 'name' => $districtName]);

        return FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->user->id,
            'fingerprint_charge' => 150.00,
        ]);
    }

    /** @test */
    public function test_fingerprint_charge_table_renders_as_livewire_component(): void
    {
        $this->setupUser();
        $this->createCharge('Khulna', 'Kushtia');
        Auth::login($this->user);

        $response = $this->get(route('settings', ['tab' => 'fingerprint-charge']));
        $response->assertOk();
        $response->assertSee('wire:id', false);
        $response->assertSee('Kushtia');
        $response->assertSee('Fingerprint Charge');
    }

    /** @test */
    public function test_fingerprint_charge_table_filter_by_division(): void
    {
        $this->setupUser();
        $this->createCharge('Khulna', 'Kushtia');
        $this->createCharge('Dhaka', 'Gazipur');
        Auth::login($this->user);

        Livewire::test(FingerprintChargeTable::class)
            ->set('divisionFilter', 'Dhaka')
            ->assertSee('Gazipur');
    }

    /** @test */
    public function test_fingerprint_charge_table_pagination_remains_bounded(): void
    {
        $this->setupUser();
        $this->createCharge('Khulna', 'Kushtia');
        $this->createCharge('Dhaka', 'Gazipur');
        Auth::login($this->user);

        $faresPerQuery = Livewire::test(FingerprintChargeTable::class);
        // With only 2 records and 10 per page, we should see 1 page of results
        $faresPerQuery->assertSee('Kushtia');
        $faresPerQuery->assertSee('Gazipur');
    }
}
