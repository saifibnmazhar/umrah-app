<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\FingerprintCharge;
use App\Models\FlightDateGap;
use App\Models\Package;
use App\Models\User;
use App\Models\VisaSellingPrice;
use App\Queries\BookingIndexQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingIndexQueryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);
    }

    protected function seedDeps(): array
    {
        $district = District::create(['name' => 'Test District', 'division' => 'Test Division']);
        $currencyRate = CurrencyRate::create(['user_id' => $this->admin->id, 'rate' => 28.0000]);
        $customer = Customer::create([
            'name' => 'Test Customer',
            'passport_no' => 'P12345678',
            'iqama_type' => 'none',
            'mobile_no' => '0501234567',
            'address' => 'Test Address',
        ]);
        $company = Branch::create([
            'name' => 'Test Branch',
            'address' => 'Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'B0001',
        ]);
        $fingerprintCharge = FingerprintCharge::create([
            'district_id' => $district->id,
            'user_id' => $this->admin->id,
            'fingerprint_charge' => 50.00,
        ]);
        $flightDateGap = FlightDateGap::getOrCreate();
        $visaPrice = VisaSellingPrice::create(['user_id' => $this->admin->id, 'selling_price' => 2000.00]);
        $package = Package::create([
            'package_name' => 'Test Umrah Package',
            'visa_selling_price_id' => $visaPrice->id,
            'regular_price' => 35000.00,
            'service_charge' => 1500.00,
            'is_active' => true,
        ]);

        return compact('district', 'currencyRate', 'customer', 'company', 'package', 'fingerprintCharge', 'flightDateGap');
    }

    private function createBooking(array $deps, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'user_id' => $this->admin->id,
            'customer_id' => $deps['customer']->id,
            'fingerprint_branch_id' => $deps['company']->id,
            'booking_branch_id' => $deps['company']->id,
            'district_id' => $deps['district']->id,
            'package_id' => $deps['package']->id,
            'invoice_id' => 'INV-2025-'.uniqid(),
            'fingerprint_location' => 'office',
            'pax_qty' => 1,
            'discount_type' => 'fixed_amount',
            'discount_value' => 0,
            'discount_amount' => 0,
            'total_value' => 50000.00,
            'remarks' => '',
            'currency_rate_id' => $deps['currencyRate']->id,
            'fingerprint_charge_id' => $deps['fingerprintCharge']->id,
            'date_gap_id' => $deps['flightDateGap']->id,
            'is_cancelled' => false,
        ], $overrides));
    }

    #[Test]
    public function test_booking_index_query_returns_query(): void
    {
        $this->actingAs($this->admin);
        $deps = $this->seedDeps();
        $this->createBooking($deps);

        $request = Request::create('/test', 'GET', ['booking_branch_id' => null]);
        $request->setUserResolver(fn () => $this->admin);
        $query = (new BookingIndexQuery($request))->getQuery();

        $this->assertInstanceOf(Builder::class, $query);
    }

    #[Test]
    public function test_booking_index_query_paginates(): void
    {
        $this->actingAs($this->admin);
        $deps = $this->seedDeps();

        for ($i = 0; $i < 3; $i++) {
            $this->createBooking($deps, ['invoice_id' => 'INV-'.($i + 1)]);
        }

        $request = Request::create('/test', 'GET', ['per_page' => 2]);
        $request->setUserResolver(fn () => $this->admin);
        $paginated = (new BookingIndexQuery($request))->paginate();

        $this->assertEquals(2, $paginated->perPage());
        $this->assertEquals(2, $paginated->count());
        $this->assertEquals(3, $paginated->total());
    }

    #[Test]
    public function test_booking_index_query_filters_by_search(): void
    {
        $this->actingAs($this->admin);
        $deps = $this->seedDeps();
        $booking = $this->createBooking($deps, ['invoice_id' => 'INV-12345']);

        $request = Request::create('/test', 'GET', ['search' => '12345']);
        $request->setUserResolver(fn () => $this->admin);
        $result = (new BookingIndexQuery($request))->getQuery()->get();

        $this->assertTrue($result->contains($booking));
        $this->assertEquals(1, $result->count());
    }

    #[Test]
    public function test_booking_index_query_scopes_by_branch_for_non_admin(): void
    {
        $this->actingAs($this->admin);
        $deps = $this->seedDeps();

        $otherBranch = Branch::create([
            'name' => 'Other Branch',
            'address' => 'Address',
            'contacts' => '0123456789',
            'location' => 'KSA',
            'fingerprint_operation' => true,
            'branch_code' => 'B0002',
        ]);
        $otherCompany = Branch::create([
            'name' => 'Other Company',
            'address' => 'Address',
            'contacts' => '0123456789',
            'location' => 'BD',
            'fingerprint_operation' => false,
            'branch_code' => 'B0003',
        ]);

        $this->createBooking($deps, ['invoice_id' => 'INV-OWN']);
        $this->createBooking($deps, ['invoice_id' => 'INV-OTHER', 'booking_branch_id' => $otherCompany->id, 'fingerprint_branch_id' => $otherCompany->id]);

        $userWithBranch = User::create([
            'name' => 'Branch Staff',
            'email' => 'staff@test.com',
            'password' => Hash::make('password'),
            'branch_id' => $deps['company']->id,
        ]);
        $this->actingAs($userWithBranch);

        $request = Request::create('/test', 'GET', []);
        $request->setUserResolver(fn () => $userWithBranch);
        $result = (new BookingIndexQuery($request))->getQuery()->get();

        $this->assertTrue($result->contains(fn ($b) => $b->invoice_id === 'INV-OWN'));
        $this->assertFalse($result->contains(fn ($b) => $b->invoice_id === 'INV-OTHER'));
    }

    #[Test]
    public function test_booking_index_query_filters_by_booking_status(): void
    {
        $this->actingAs($this->admin);
        $deps = $this->seedDeps();
        $active = $this->createBooking($deps, ['invoice_id' => 'INV-ACTIVE', 'is_cancelled' => false]);

        $request = Request::create('/test', 'GET', ['booking_status' => 'active']);
        $request->setUserResolver(fn () => $this->admin);
        $result = (new BookingIndexQuery($request))->getQuery()->get();

        $this->assertTrue($result->contains($active));
    }

    #[Test]
    public function test_booking_index_query_computes_summary_counts(): void
    {
        $this->actingAs($this->admin);
        $deps = $this->seedDeps();
        $this->createBooking($deps, ['invoice_id' => 'INV-1', 'pax_qty' => 2]);
        $this->createBooking($deps, ['invoice_id' => 'INV-2', 'pax_qty' => 3]);

        $request = Request::create('/test', 'GET', []);
        $request->setUserResolver(fn () => $this->admin);
        $summary = (new BookingIndexQuery($request))->getSummary();

        $this->assertEquals(2, $summary['totalBookingCount']);
        $this->assertEquals(5, $summary['totalBookingPassengerCount']);
    }
}
