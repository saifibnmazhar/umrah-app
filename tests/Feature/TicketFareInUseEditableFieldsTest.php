<?php

namespace Tests\Feature;

use App\Models\Airline;
use App\Models\AirlineClass;
use App\Models\Branch;
use App\Models\CityCode;
use App\Models\Package;
use App\Models\Role;
use App\Models\Route;
use App\Models\TicketFare;
use App\Models\TravelClass;
use App\Models\User;
use App\Models\VisaSellingPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketFareInUseEditableFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private TicketFare $fare;

    private Package $package;

    private float $visaPrice = 2000.00;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::create([
            'name' => 'TB', 'address' => 'A', 'contacts' => '01',
            'location' => 'KSA', 'fingerprint_operation' => true, 'branch_code' => 'TB01',
        ]);
        $this->user = User::create([
            'name' => 'Admin', 'email' => 'admin@example.com',
            'password' => bcrypt('password'), 'is_active' => true, 'branch_id' => $branch->id,
        ]);
        $this->user->roles()->attach(Role::create(['name' => 'Super Admin']));

        $c1 = CityCode::create(['city_name' => 'Dhaka', 'code' => 'DAC', 'country' => 'BD']);
        $c2 = CityCode::create(['city_name' => 'Riyadh', 'code' => 'RUH', 'country' => 'SA']);
        $airline = Airline::create(['name' => 'SV', 'code' => 'SV']);
        $travelClass = TravelClass::create(['name' => 'Economy']);
        $airlineClass = AirlineClass::create(['airline_id' => $airline->id, 'class_id' => $travelClass->id]);
        $route = Route::create([
            'airline_id' => $airline->id, 'route_type' => 'round', 'flight_type' => 'direct',
            'from_city_id' => $c1->id, 'to_city_id' => $c2->id,
            'return_city_id' => $c1->id, 'additional_gap' => null,
        ]);

        $this->fare = TicketFare::create([
            'airline_id' => $airline->id,
            'airline_classes_id' => $airlineClass->id,
            'route_id' => $route->id,
            'ticket_type' => 'regular',
            'effective_from' => now()->subDays(30)->format('Y-m-d'),
            'effective_to' => now()->addDays(30)->format('Y-m-d'),
            'net_fare' => 25000.00,
            'selling_fare' => 28000.00,
            'offer_price' => null,
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'with_meal' => true,
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $visa = VisaSellingPrice::create(['user_id' => $this->user->id, 'selling_price' => $this->visaPrice]);

        $this->package = Package::create([
            'package_name' => 'Pkg',
            'ticket_fare_id' => $this->fare->id,
            'visa_selling_price_id' => $visa->id,
            'regular_price' => 28000.00 + $this->visaPrice,
            'offer_price' => 32000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
            'is_double_ticket' => false,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'selling_fare' => 28000.00,
            'effective_from' => now()->subDays(30)->format('Y-m-d'),
            'effective_to' => now()->addDays(30)->format('Y-m-d'),
            'child_fare_percentage' => 75.00,
            'infant_fare_percentage' => 10.00,
            'page' => 1,
        ], $overrides);
    }

    public function test_in_use_fare_updates_effective_from_child_and_infant(): void
    {
        $newFrom = now()->addDay()->format('Y-m-d');

        $response = $this->actingAs($this->user)->put(
            route('ticket-fares.update', $this->fare->id),
            $this->payload([
                'effective_from' => $newFrom,
                'child_fare_percentage' => 65.50,
                'infant_fare_percentage' => 15.25,
            ])
        );

        $response->assertRedirect();

        $this->fare->refresh();
        $this->assertSame($newFrom, $this->fare->effective_from->format('Y-m-d'));
        $this->assertEquals(65.50, (float) $this->fare->child_fare_percentage);
        $this->assertEquals(15.25, (float) $this->fare->infant_fare_percentage);
    }

    public function test_in_use_fare_rejects_child_percentage_above_100(): void
    {
        $response = $this->actingAs($this->user)->put(
            route('ticket-fares.update', $this->fare->id),
            $this->payload(['child_fare_percentage' => 150])
        );

        $response->assertRedirect();
        $this->assertEquals(75.00, (float) $this->fare->refresh()->child_fare_percentage);
    }

    public function test_in_use_fare_rejects_effective_to_before_effective_from(): void
    {
        $from = now()->addDays(10)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        $response = $this->actingAs($this->user)->put(
            route('ticket-fares.update', $this->fare->id),
            $this->payload(['effective_from' => $from, 'effective_to' => $to])
        );

        $response->assertRedirect();
        $this->assertNotSame($from, $this->fare->refresh()->effective_from->format('Y-m-d'));
    }

    public function test_in_use_selling_fare_change_still_cascades_to_package(): void
    {
        $newSelling = 30000.00;

        $this->actingAs($this->user)->put(
            route('ticket-fares.update', $this->fare->id),
            $this->payload(['selling_fare' => $newSelling])
        );

        $this->assertEquals(
            round($newSelling + $this->visaPrice, 6),
            (float) $this->package->refresh()->regular_price
        );
    }

    public function test_edit_form_renders_three_fields_editable_when_in_use(): void
    {
        $this->assertTrue($this->fare->isLocked());

        $response = $this->actingAs($this->user)->get(route('ticket-fares.edit', $this->fare->id));
        $response->assertOk();

        $html = $response->getContent();
        foreach (['effective_from', 'child_fare_percentage', 'infant_fare_percentage'] as $field) {
            $this->assertDoesNotMatchRegularExpression(
                '/name="'.$field.'"[^>]*readonly/',
                $html,
                "Field {$field} should be editable on in-use fare edit form"
            );
        }
    }
}
