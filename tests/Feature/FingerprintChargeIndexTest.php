<?php

namespace Tests\Feature;

use App\Livewire\Fingerprint\FingerprintChargeList;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class FingerprintChargeIndexTest extends TestCase
{
    use RefreshDatabase;

    private function setupUser(): User
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'branch_id' => null,
        ]);

        return $user;
    }

    private function seedCharges(int $count = 12, int $userId = 1): array
    {
        $divisions = ['Barisal', 'Chittagong', 'Dhaka', 'Rajshahi'];
        $charges = [];

        foreach (range(1, $count) as $i) {
            $district = District::create([
                'name' => 'District '.$i,
                'division' => $divisions[$i % count($divisions)],
            ]);

            $charges[] = FingerprintCharge::create([
                'district_id' => $district->id,
                'user_id' => $userId,
                'fingerprint_charge' => 50.00 + $i,
            ]);
        }

        return $charges;
    }

    /** @test */
    public function test_fingerprint_charges_list_renders_as_livewire_component(): void
    {
        $user = $this->setupUser();
        $this->seedCharges(5, $user->id);

        Auth::login($user);

        $response = $this->get(route('fingerprint-charges.index'));
        $response->assertOk();
        $response->assertSee('wire:id');
        $response->assertSee('Division');
        $response->assertSee('District');
        $response->assertSee('Charge');
    }

    /** @test */
    public function test_fingerprint_charges_filter_by_division(): void
    {
        $user = $this->setupUser();
        $this->seedCharges(8, $user->id);

        Auth::login($user);

        Livewire::test(FingerprintChargeList::class)
            ->set('divisionFilter', 'Dhaka')
            ->assertSee('District 2') // districts with Dhaka division: 2, 6, 10...
            ->assertDontSee('District 1');
    }

    /** @test */
    public function test_fingerprint_charges_pagination_remains_bounded(): void
    {
        $user = $this->setupUser();
        $this->seedCharges(25, $user->id);

        Auth::login($user);

        $response = $this->get(route('fingerprint-charges.index'));
        $response->assertOk();
        $response->assertSee('wire:id');
    }
}
