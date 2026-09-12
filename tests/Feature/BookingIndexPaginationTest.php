<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Passenger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesFingerprintFixtures;
use Tests\TestCase;

class BookingIndexPaginationTest extends TestCase
{
    use CreatesFingerprintFixtures, RefreshDatabase;

    private ?User $seedUser = null;

    private function seedBookings(int $bookings = 10, int $perBooking = 2): int
    {
        $user = $this->roleUser('Super Admin');
        $this->seedUser = $user;
        $deps = $this->seedDeps($user);
        $total = 0;

        for ($i = 0; $i < $bookings; $i++) {
            $booking = $this->createBooking($user, $deps);
            Invoice::create([
                'booking_id' => $booking->id,
                'branch_id' => $booking->booking_branch_id,
                'user_id' => $user->id,
                'total_amount' => 50000,
                'paid_amount' => 25000,
                'balance' => 25000,
                'status' => 'partial',
            ]);

            for ($j = 0; $j < $perBooking; $j++) {
                Passenger::create([
                    'booking_id' => $booking->id,
                    'first_name' => 'Pax'.$i.$j,
                    'last_name' => 'Last',
                    'passport_no' => 'BK'.$i.'PS'.$j.uniqid(),
                    'mobile_no' => '0500000000',
                    'date_of_birth' => '1990-01-01',
                    'passenger_type' => 'adult',
                    'passport_expiry' => now()->addYears(5)->format('Y-m-d'),
                    'stay_duration' => 14,
                    'service_required' => 'all',
                    'flight_date_from' => now()->addDays(5)->format('Y-m-d'),
                    'flight_date_to' => now()->addDays(15)->format('Y-m-d'),
                    'ticket_status' => 'pending',
                    'address' => 'Addr',
                ]);
                $total++;
            }
        }

        return $total;
    }

    public function test_shell_bookings_returns_200(): void
    {
        $admin = $this->roleUser('Super Admin');
        $this->actingAs($admin)->get(route('bookings.index'))->assertOk();
    }

    public function test_api_bookings_passengers_returns_15_with_pagination_and_summary(): void
    {
        $total = $this->seedBookings(10, 2);
        $this->assertSame(20, $total);

        $admin = $this->seedUser;
        $response = $this->actingAs($admin)->getJson('/api/bookings/passengers');

        $response->assertOk()
            ->assertJsonPath('pagination.per_page', 15)
            ->assertJsonPath('pagination.total', 20)
            ->assertJsonPath('summary.total', 20);
        $this->assertCount(15, $response->json('data'));
    }

    public function test_shell_passenger_tab_renders_rows(): void
    {
        $this->seedBookings(2, 1);

        $response = $this->actingAs($this->seedUser)->get(route('bookings.index', ['tab' => 'passenger']));

        $response->assertOk();
        $passengers = $response->viewData('passengers');
        $this->assertGreaterThan(0, $passengers->total());
        $response->assertSee(Passenger::first()->passport_no, false);
    }

    public function test_api_bookings_passengers_search_filters(): void
    {
        $this->seedBookings(2, 1);

        $admin = $this->seedUser;
        $passenger = Passenger::first();

        $response = $this->actingAs($admin)->getJson('/api/bookings/passengers?search='.$passenger->passport_no);
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
        $this->assertEquals($passenger->passport_no, $response->json('data.0.passport_no'));
    }
}
