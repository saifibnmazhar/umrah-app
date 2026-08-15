<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Enums\FingerprintLocation;
use App\Enums\FingerprintStatus;
use App\Enums\Gender;
use App\Enums\InvoiceStatus;
use App\Enums\PassengerType;
use App\Enums\PaymentMethod;
use App\Enums\ServiceRequired;
use App\Enums\TicketStatus;
use App\Models\Bank;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\District;
use App\Models\Fingerprint;
use App\Models\FingerprintCharge;
use App\Models\FingerprintDetail;
use App\Models\FlightDateGap;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\PassengerStatus;
use App\Models\Payment;
use App\Models\TicketAgent;
use App\Models\User;
use App\Models\VisaAgent;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        // ── Resolve supporting seed data ───────────────────────────
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password'), 'is_active' => true]
        );

        $branch = Branch::firstOrCreate(
            ['name' => 'Main Branch'],
            ['address' => '123 Main Street, Dhaka', 'contacts' => '+880 2-1234-5678']
        );

        $district = District::firstOrCreate(
            ['name' => 'Dhaka'],
            ['division' => 'Dhaka Division']
        );

        $customer = Customer::firstOrCreate(
            ['passport_no' => 'PA1234567'],
            [
                'name' => 'Abdullah Rahman',
                'iqama_type' => 'self',
                'iqama_no' => 'IQ987654321',
                'mobile_no' => '+880 171-1234567',
                'address' => 'Apt 5B, 12 Dhanmondi, Dhaka',
            ]
        );

        $currencyRate = CurrencyRate::firstOrCreate(
            ['rate' => 25.0000],
            ['user_id' => $user->id]
        );

        $flightDateGap = FlightDateGap::firstOrCreate(['gap' => 7], []);

        $fingerprintCharge = FingerprintCharge::firstOrCreate(
            ['fingerprint_charge' => 1500.00, 'district_id' => $district->id, 'user_id' => $user->id],
            []
        );

        $visaSellingPrice = VisaSellingPrice::firstOrCreate(
            ['selling_price' => 1200.00, 'user_id' => $user->id],
            []
        );

        $package = Package::firstOrCreate(
            ['package_name' => 'Umrah Package - 10 Days'],
            [
                'ticket_fare_id' => null,
                'visa_selling_price_id' => $visaSellingPrice->id,
                'regular_price' => 45000.00,
                'offer_price' => null,
            ]
        );

        $visaAgent = VisaAgent::firstOrCreate(
            ['name' => 'Global Visa Services'],
            ['address' => 'House 15, Road 7, Dhaka', 'contacts' => '+880 2-9876-5432']
        );

        $ticketAgent = TicketAgent::firstOrCreate(
            ['name' => 'Skylink Travel Agency'],
            ['address' => 'Level 3, City Centre, Chittagong', 'contacts' => '+880 31-555-7777']
        );

        $banks = Bank::all();
        if ($banks->isEmpty()) {
            $banks = collect([
                Bank::create(['name' => 'Dutch-Bangla Bank', 'description' => 'Primary banking partner']),
                Bank::create(['name' => 'BRAC Bank', 'description' => 'Secondary banking partner']),
            ]);
        }

        $bank = $banks->first();

        // ─────────────────────────────────────────────────────────────
        // Booking #1 — 2 adult passengers, full pipeline
        // ─────────────────────────────────────────────────────────────
        $invoiceNumber1 = 'INV-'.date('Y').'-0001';

        // Create booking first (invoice_id is a unique string identifier, not FK)
        $booking1 = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $district->id,
            'package_id' => $package->id,
            'fingerprint_charge_id' => $fingerprintCharge->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => $invoiceNumber1,
            'date_gap_id' => $flightDateGap->id,
            'fingerprint_location' => FingerprintLocation::OFFICE,
            'pax_qty' => 2,
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => 5.00,
            'discount_amount' => 2250.00,
            'total_value' => 42750.00,
            'currency_rate_id' => $currencyRate->id,
            'is_cancelled' => false,
            'remarks' => 'Family Umrah booking — 2 adults',
        ]);

        // Now create invoice linked to booking
        $invoice1 = Invoice::create([
            'booking_id' => $booking1->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 90000.00,
            'paid_amount' => 50000.00,
            'balance' => 40000.00,
            'status' => InvoiceStatus::PARTIAL,
            'notes' => 'Initial invoice for booking #1 — partial payment received',
        ]);

        // Passengers for Booking #1
        $passenger1 = Passenger::create([
            'booking_id' => $booking1->id,
            'passenger_status_id' => PassengerStatus::where('name', 'Processing')->first()?->id,
            'first_name' => 'Mohammed',
            'last_name' => 'Siddique',
            'passport_no' => 'PB1234567',
            'mobile_no' => '+880 191-1111111',
            'date_of_birth' => '1985-03-15',
            'passenger_type' => PassengerType::ADULT,
            'gender' => Gender::MALE,
            'passport_expiry' => '2030-06-30',
            'stay_duration' => 10,
            'service_required' => ServiceRequired::ALL,
            'flight_date_from' => '2025-02-10',
            'flight_date_to' => '2025-02-20',
            'ticket_status' => TicketStatus::PENDING,
            'address' => '12/D, Dhanmondi, Dhaka',
            'package_value' => 42750.00,
        ]);

        $passenger2 = Passenger::create([
            'booking_id' => $booking1->id,
            'passenger_status_id' => PassengerStatus::where('name', 'Processing')->first()?->id,
            'first_name' => 'Fatima',
            'last_name' => 'Siddique',
            'passport_no' => 'PB7654321',
            'mobile_no' => '+880 191-2222222',
            'date_of_birth' => '1988-07-22',
            'passenger_type' => PassengerType::ADULT,
            'gender' => Gender::FEMALE,
            'passport_expiry' => '2029-12-15',
            'stay_duration' => 10,
            'service_required' => ServiceRequired::ALL,
            'flight_date_from' => '2025-02-10',
            'flight_date_to' => '2025-02-20',
            'ticket_status' => TicketStatus::PENDING,
            'address' => '12/D, Dhanmondi, Dhaka',
            'package_value' => 42750.00,
        ]);

        // Payment for Booking #1 (partial — matches invoice paid_amount)
        $payment1 = Payment::create([
            'invoice_id' => $invoice1->id,
            'booking_id' => $booking1->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'currency_rate_id' => $currencyRate->id,
            'bank_id' => $bank->id,
            'sender_bank_id' => $bank->id,
            'other_sender_bank' => null,
            'ticket_agent_id' => $ticketAgent->id,
            'visa_agent_id' => $visaAgent->id,
            'commission_agent_id' => null,
            'payment_date' => now(),
            'payment_method' => PaymentMethod::BANK,
            'amount' => 50000.00,
            'bdt_amount' => 1250000.00,
            'transaction_id' => 'TXN-'.date('Y').'-0001',
            'payment_referral' => null,
            'notes' => 'Initial partial payment',
            'remarks' => null,
        ]);

        // Fingerprint for Booking #1
        $fingerprint1 = Fingerprint::create([
            'booking_id' => $booking1->id,
            'deadline' => now()->addDays(3),
            'cost' => 1500.00,
            'assigned_staff_id' => $user->id,
        ]);

        // Fingerprint details (one per passenger)
        FingerprintDetail::create([
            'fingerprint_id' => $fingerprint1->id,
            'passenger_id' => $passenger1->id,
            'status' => FingerprintStatus::APPROVED,
        ]);

        FingerprintDetail::create([
            'fingerprint_id' => $fingerprint1->id,
            'passenger_id' => $passenger2->id,
            'status' => FingerprintStatus::APPROVED,
        ]);

        // Visa submissions for Booking #1 passengers
        VisaSubmission::create([
            'passenger_id' => $passenger1->id,
            'visa_agent_id' => $visaAgent->id,
            'commission_agent_id' => null,
            'agent_commission' => 150.00,
            'visa_selling_price_id' => $visaSellingPrice->id,
            'net_visa_cost' => 950.00,
            'additional_cost' => 50.00,
            'final_cost' => 1200.00,
            'visa_number' => 'VISA-2025-0001',
            'is_cancelled' => false,
            'status' => 'pending',
            'remarks' => 'Visa submitted for Mohammed Siddique',
        ]);

        VisaSubmission::create([
            'passenger_id' => $passenger2->id,
            'visa_agent_id' => $visaAgent->id,
            'commission_agent_id' => null,
            'agent_commission' => 150.00,
            'visa_selling_price_id' => $visaSellingPrice->id,
            'net_visa_cost' => 950.00,
            'additional_cost' => 50.00,
            'final_cost' => 1200.00,
            'visa_number' => 'VISA-2025-0002',
            'is_cancelled' => false,
            'status' => 'pending',
            'remarks' => 'Visa submitted for Fatima Siddique',
        ]);

        // Issued tickets for Booking #1 passengers
        IssuedTicket::create([
            'passenger_id' => $passenger1->id,
            'booking_id' => $booking1->id,
            'user_id' => $user->id,
            'ticket_agent_id' => $ticketAgent->id,
            'ticket_fare_id' => $package->ticket_fare_id,
            'ticket_number' => 'TKT-2025-SA-UD-001',
            'pnr' => 'PNRUD019X',
            'issued_date' => '2025-01-28',
            'inbound_date' => '2025-02-10',
            'outbound_date' => '2025-02-20',
            'selling_fare' => 45000.00,
            'net_fare' => 42750.00,
            'offer_price' => null,
            'is_refundable' => true,
            'is_exchangeable' => true,
            'baggage_inbound' => '30kg',
            'baggage_outbound' => '30kg',
            'outbound_pending' => false,
            'issue_type' => 'regular',
            'status' => 'pending',
        ]);

        IssuedTicket::create([
            'passenger_id' => $passenger2->id,
            'booking_id' => $booking1->id,
            'user_id' => $user->id,
            'ticket_agent_id' => $ticketAgent->id,
            'ticket_fare_id' => $package->ticket_fare_id,
            'ticket_number' => 'TKT-2025-SA-UD-002',
            'pnr' => 'PNRUD019X',
            'issued_date' => '2025-01-28',
            'inbound_date' => '2025-02-10',
            'outbound_date' => '2025-02-20',
            'selling_fare' => 45000.00,
            'net_fare' => 42750.00,
            'offer_price' => null,
            'is_refundable' => true,
            'is_exchangeable' => true,
            'baggage_inbound' => '30kg',
            'baggage_outbound' => '30kg',
            'outbound_pending' => false,
            'issue_type' => 'regular',
            'status' => 'pending',
        ]);

        // ─────────────────────────────────────────────────────────────
        // Booking #2 — 1 child passenger, visa-only service
        // ─────────────────────────────────────────────────────────────
        $invoiceNumber2 = 'INV-'.date('Y').'-0002';

        $booking2 = Booking::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'fingerprint_branch_id' => $branch->id,
            'district_id' => $district->id,
            'package_id' => $package->id,
            'fingerprint_charge_id' => $fingerprintCharge->id,
            'booking_branch_id' => $branch->id,
            'invoice_id' => $invoiceNumber2,
            'date_gap_id' => $flightDateGap->id,
            'fingerprint_location' => FingerprintLocation::HOME,
            'pax_qty' => 1,
            'discount_type' => DiscountType::FIXED_AMOUNT,
            'discount_value' => 0.00,
            'discount_amount' => 1000.00,
            'total_value' => 11000.00,
            'currency_rate_id' => $currencyRate->id,
            'is_cancelled' => false,
            'remarks' => 'Child Umrah booking — visa only',
        ]);

        $invoice2 = Invoice::create([
            'booking_id' => $booking2->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'total_amount' => 12000.00,
            'paid_amount' => 12000.00,
            'balance' => 0.00,
            'status' => InvoiceStatus::PAID,
            'notes' => 'Full payment received for child Umrah booking',
        ]);

        // Passenger for Booking #2
        $passenger3 = Passenger::create([
            'booking_id' => $booking2->id,
            'passenger_status_id' => PassengerStatus::where('name', 'Processing')->first()?->id,
            'first_name' => 'Yusuf',
            'last_name' => 'Siddique',
            'passport_no' => 'PB9988776',
            'mobile_no' => '+880 171-9999999',
            'date_of_birth' => '2018-04-10',
            'passenger_type' => PassengerType::CHILD,
            'gender' => Gender::MALE,
            'passport_expiry' => '2029-08-15',
            'stay_duration' => 10,
            'service_required' => ServiceRequired::VISA_ONLY,
            'flight_date_from' => '2025-03-15',
            'flight_date_to' => '2025-03-25',
            'ticket_status' => TicketStatus::PENDING,
            'address' => '12/D, Dhanmondi, Dhaka',
            'package_value' => 11000.00,
        ]);

        // Payment for Booking #2 (full)
        Payment::create([
            'invoice_id' => $invoice2->id,
            'booking_id' => $booking2->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'currency_rate_id' => $currencyRate->id,
            'bank_id' => $bank->id,
            'sender_bank_id' => $bank->id,
            'other_sender_bank' => null,
            'ticket_agent_id' => $ticketAgent->id,
            'visa_agent_id' => $visaAgent->id,
            'commission_agent_id' => null,
            'payment_date' => now(),
            'payment_method' => PaymentMethod::CASH,
            'amount' => 12000.00,
            'bdt_amount' => 300000.00,
            'transaction_id' => 'TXN-'.date('Y').'-0002',
            'payment_referral' => null,
            'notes' => 'Full payment for child booking',
            'remarks' => null,
        ]);

        // Fingerprint for Booking #2
        $fingerprint2 = Fingerprint::create([
            'booking_id' => $booking2->id,
            'deadline' => now()->addDays(5),
            'cost' => 1000.00,
            'assigned_staff_id' => $user->id,
        ]);

        FingerprintDetail::create([
            'fingerprint_id' => $fingerprint2->id,
            'passenger_id' => $passenger3->id,
            'status' => FingerprintStatus::DONE,
        ]);

        // Visa submission for Booking #2 passenger
        VisaSubmission::create([
            'passenger_id' => $passenger3->id,
            'visa_agent_id' => $visaAgent->id,
            'commission_agent_id' => null,
            'agent_commission' => 100.00,
            'visa_selling_price_id' => $visaSellingPrice->id,
            'net_visa_cost' => 800.00,
            'additional_cost' => 25.00,
            'final_cost' => 1000.00,
            'visa_number' => 'VISA-2025-0003',
            'is_cancelled' => false,
            'status' => 'issued',
            'remarks' => 'Visa issued for child passenger Yusuf',
        ]);

        // No issued ticket for child (visa-only service)

        $this->command?->info('Created 2 bookings with 3 passengers, invoices, payments, visa submissions, fingerprints, and issued tickets.');
    }
}
