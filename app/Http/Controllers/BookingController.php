<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Passenger;
use App\Models\Customer;
use App\Models\District;
use App\Models\Document;
use App\Models\Package;
use App\Models\Office;
use App\Models\FingerprintCharge;
use App\Models\Fingerprint;
use App\Models\BookingCondition;
use App\Models\FingerprintDetail;
use App\Models\Route;
use App\Models\Airline;
use App\Models\TravelClass;
use App\Models\TicketFare;
use App\Models\VisaSellingPrice;
use App\Models\PassengerStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Bank;
use App\Models\TransactionType;
use App\Models\CurrencyRate;
use App\Enums\FingerprintStatus;
use App\Enums\PassengerType;
use App\Enums\ServiceRequired;
use App\Enums\FingerprintLocation;
use App\Enums\DiscountType;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private InvoiceService $invoiceService,
    ) {}

    private function syncBookingFinancials(Booking $booking): array
    {
        $this->bookingService->syncFinancials($booking);

        $invoice = $booking->invoice;
        if ($invoice) {
            $invoice = $invoice->fresh();
            return [
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'balance' => (float) $invoice->balance,
                'original_total' => (float) $booking->total_value,
                'discount_amount' => (float) $booking->discount_amount,
                'discount_type' => $booking->discount_type?->value,
                'discount_value' => (float) $booking->discount_value,
            ];
        }

        return [
            'total_amount' => 0,
            'paid_amount' => 0,
            'balance' => 0,
            'original_total' => (float) $booking->total_value,
            'discount_amount' => (float) $booking->discount_amount,
            'discount_type' => $booking->discount_type?->value,
            'discount_value' => (float) $booking->discount_value,
        ];
    }

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'booking');
        
        $bookings = Booking::with(['customer', 'passengers', 'office', 'invoice', 'district', 'package'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        $passengers = Passenger::approvedFingerprint()->with([
            'booking',
            'booking.customer',
            'booking.package.ticketFare.route',
            'booking.invoice',
            'ticketFare.route',
            'status',
            'visaSubmission.visaAgent',
            'fingerprintDetail.fingerprint.fingerprintDetails'
        ])
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $passengerStatuses = PassengerStatus::all();

        return view('bookings.index', compact('tab', 'bookings', 'passengers', 'passengerStatuses'));
    }

    public function create(Request $request)
    {
        $packageId = $request->query('package_id');
        $preSelectedPackageId = null;

        if ($packageId) {
            $package = Package::find($packageId);
            $preSelectedPackageId = $package ? $package->id : null;
        }

        $districts = District::orderBy('name')->get();
        $packages = Package::with(['ticketFare', 'visaSellingPrice'])->orderBy('package_name')->get()->map(function ($pkg) {
            return [
                'id' => $pkg->id,
                'package_name' => $pkg->package_name,
                'ticket_fare_id' => $pkg->ticket_fare_id,
                'visa_selling_price' => $pkg->visaSellingPrice?->selling_price ?? 0,
                'service_charge' => $pkg->service_charge ?? 0,
            ];
        });

        $ticketFares = TicketFare::with([
            'route.fromCity',
            'route.toCity',
            'route.returnCity',
            'route.multiSegments.fromCity',
            'route.multiSegments.toCity',
            'airline',
            'airlineClass.class',
            'groupTicket',
            'baggageAllowances',
        ])->get()->map(function ($fare) {
            $routeCode = '';
            $routeType = $fare->route->route_type?->value;

            if ($routeType === 'multi_city') {
                $segments = $fare->route->multiSegments->map(function ($seg) {
                    return $seg->fromCity?->code . '-' . $seg->toCity?->code;
                })->toArray();
                $routeCode = implode(', ', $segments);
            } elseif ($routeType === 'round') {
                $routeCode = $fare->route->fromCity?->code . '-' .
                    $fare->route->toCity?->code . '-' .
                    $fare->route->returnCity?->code;
            } else {
                $routeCode = $fare->route->fromCity?->code . '-' . $fare->route->toCity?->code;
            }

            return [
                'id' => $fare->id,
                'route' => $routeCode,
                'airline' => $fare->airline->name,
                'airline_class' => $fare->airlineClass->class?->name,
                'ticket_type' => $fare->ticket_type->value,
                'selling_fare' => $fare->selling_fare,
                'child_fare_percentage' => $fare->child_fare_percentage,
                'infant_fare_percentage' => $fare->infant_fare_percentage,
                'offer_price' => $fare->offer_price,
                'available_seats' => $fare->groupTicket?->ticket_qty ?? null,
                'route_type' => $routeType,
                'flight_type' => $fare->route->flight_type?->value,
                'baggage_allowances' => $fare->baggageAllowances->map(function ($ba) {
                    return [
                        'passenger_type' => $ba->passenger_type,
                        'travel_direction' => $ba->travel_direction,
                        'allowance' => $ba->allowance
                    ];
                })->toArray(),
            ];
        });

        $offices = Office::orderBy('name')->get();

        $currencyRates = \App\Models\CurrencyRate::orderBy('created_at', 'desc')->get();
        $currentCurrencyRate = \App\Models\CurrencyRate::orderBy('created_at', 'desc')->first();

        return view('bookings.create', compact(
            'districts', 'packages', 'offices', 'preSelectedPackageId', 'ticketFares',
            'currencyRates', 'currentCurrencyRate'
        ));
    }

    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'district_id' => 'required|exists:districts,id',
            'office_id' => 'nullable|exists:offices,id',
            'package_id' => 'nullable|exists:packages,id',
            'fingerprint_charge_id' => 'required|exists:fingerprint_charges,id',
            'fingerprint_location' => 'nullable|in:office,home',
            'pax_qty' => 'nullable|integer|min:1',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
            'passengers' => 'required|array|min:1',
            'passengers.*.first_name' => 'required|string|max:255',
            'passengers.*.last_name' => 'required|string|max:255',
            'passengers.*.passport_no' => 'required|string|max:50',
            'passengers.*.date_of_birth' => 'required|date|before:today',
            'passengers.*.mobile_no' => 'nullable|string|max:20',
            'passengers.*.passport_expiry' => 'nullable|date',
            'passengers.*.service_required' => 'nullable|in:all,visa_only,ticket_only',
            'passengers.*.stay_duration' => 'nullable|integer|min:1',
            'passengers.*.flight_date_from' => 'nullable|date',
            'passengers.*.flight_date_to' => 'nullable|date|after:passengers.*.flight_date_from',
            'passengers.*.address' => 'nullable|string|max:500',
            'passengers.*.gender' => 'nullable|in:male,female',
            'passengers.*.ticket_fare_id' => 'nullable|exists:ticket_fares,id',
            'booking_customer_docs' => 'nullable|array',
            'booking_customer_docs.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'payment' => 'required|array',
            'payment.amount' => 'required|numeric|min:0.01',
            'payment.bdt_amount' => 'nullable|numeric|min:0',
            'payment.currency' => 'required|in:SAR,BDT',
            'payment.payment_method' => 'required|in:cash,bank',
            'payment.payment_date' => 'nullable|date',
            'payment.bank_id' => 'nullable|exists:banks,id',
            'payment.transaction_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            \Log::warning('Booking store validation failed', [
                'errors' => $validator->errors()->toArray(),
                'input_keys' => array_keys($request->all()),
                'files' => array_keys($request->allFiles()),
            ]);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        try {
            DB::beginTransaction();

            $currentCurrencyRate = CurrencyRate::orderBy('created_at', 'desc')->first();

            $booking = Booking::create([
                'user_id' => auth()->id(),
                'branch_id' => auth()->user()->branch_id ?? 1,
                'invoice_id' => $this->bookingService->generateInvoiceId(auth()->user()->branch_id ?? 1),
                'date_gap_id' => \App\Models\FlightDateGap::getOrCreate()->id,
                'customer_id' => $validated['customer_id'],
                'district_id' => $validated['district_id'] ?? null,
                'office_id' => $validated['office_id'] ?? null,
                'package_id' => $validated['package_id'] ?? null,
                'fingerprint_charge_id' => $validated['fingerprint_charge_id'] ?? null,
                'fingerprint_location' => $validated['fingerprint_location'] ?? 'Office',
                'pax_qty' => count($validated['passengers']),
                'discount_type' => ($validated['discount_type'] ?? 'fixed') === 'fixed' ? 'fixed_amount' : 'percentage',
                'discount_value' => $validated['discount_value'] ?? 0,
                'discount_amount' => 0,
                'remarks' => $validated['remarks'] ?? null,
                'currency_rate_id' => $currentCurrencyRate?->id,
            ]);

            $customerDocs = $request->file('booking_customer_docs', []);
            if (is_array($customerDocs) && count($customerDocs) > 0) {
                foreach ($customerDocs as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                        $booking->documents()->create([
                            'owner_type' => 'booking',
                            'owner_id' => $booking->id,
                            'file_path' => $file->store('booking-docs', 'public'),
                            'display_name' => $file->getClientOriginalName(),
                        ]);
                    }
                }
            }

            foreach ($validated['passengers'] as $passengerData) {
                $passengerType = $this->bookingService->calculatePassengerType(
                    $passengerData['date_of_birth'],
                    $passengerData['stay_duration'] ?? null
                );

                Passenger::create([
                    'booking_id' => $booking->id,
                    'first_name' => $passengerData['first_name'],
                    'last_name' => $passengerData['last_name'],
                    'passport_no' => $passengerData['passport_no'],
                    'date_of_birth' => $passengerData['date_of_birth'],
                    'gender' => $passengerData['gender'] ?? null,
                    'passenger_type' => $passengerType,
                    'passport_expiry' => $passengerData['passport_expiry'] ?? null,
                    'mobile_no' => $passengerData['mobile_no'] ?? null,
                    'service_required' => $passengerData['service_required'] ?? 'All',
                    'stay_duration' => $passengerData['stay_duration'] ?? 14,
                    'flight_date_from' => $passengerData['flight_date_from'] ?? null,
                    'flight_date_to' => $passengerData['flight_date_to'] ?? null,
                    'address' => $passengerData['address'] ?? null,
                    'ticket_fare_id' => ($passengerData['service_required'] ?? '') === 'visa_only'
                        ? null
                        : ($passengerData['ticket_fare_id'] ?? $booking->package?->ticket_fare_id),
                ]);
            }

            $fingerprint = Fingerprint::create([
                'booking_id' => $booking->id,
                'deadline' => now()->addDays(10),
                'cost' => 0,
                'assigned_staff_id' => null,
            ]);

            $booking->load('passengers');
            foreach ($booking->passengers as $passenger) {
                FingerprintDetail::create([
                    'fingerprint_id' => $fingerprint->id,
                    'passenger_id' => $passenger->id,
                    'status' => 'none',
                ]);
            }

            $booking->refresh();
            $this->bookingService->recalculateBookingTotal($booking);

            $invoice = $this->bookingService->createInvoiceForBooking($booking);

            $discountAmount = $this->bookingService->calculateDiscount(
                $booking->total_value,
                $validated['discount_type'] ?? 'fixed',
                $validated['discount_value'] ?? 0
            );
            $booking->discount_amount = $discountAmount;
            $booking->saveQuietly();

            $discountedTotal = max(0, $booking->total_value - $discountAmount);
            $invoice->total_amount = $discountedTotal;
            $invoice->balance = $discountedTotal;
            $invoice->save();

            $paymentAmount = (float) ($validated['payment']['amount'] ?? 0);
            $paymentBdtAmount = (float) ($validated['payment']['bdt_amount'] ?? 0);

            \Log::info('Payment debug - amount: ' . $paymentAmount . ', bdt_amount: ' . $paymentBdtAmount . ', payment array: ', $validated['payment'] ?? []);

            if ($paymentAmount > 0 || $paymentBdtAmount > 0) {
                \Log::info('Processing payment...');
                try {
                    $initialPaymentTransactionType = TransactionType::where('name', 'Initial Payment')->first();
                    
                    if (!$initialPaymentTransactionType) {
                        throw new \Exception('Initial Payment transaction type not found. Please seed transaction types.');
                    }

                    $paymentData = [
                        'branch_id' => $booking->branch_id,
                        'user_id' => $booking->user_id,
                        'payment_date' => $validated['payment']['payment_date'] ?? now()->toDateString(),
                        'payment_method' => $validated['payment']['payment_method'] ?? 'cash',
                        'amount' => $validated['payment']['amount'] ?? 0,
                        'bdt_amount' => $validated['payment']['bdt_amount'] ?? 0,
                        'currency' => $validated['payment']['currency'] ?? 'SAR',
                        'bank_id' => $validated['payment']['bank_id'] ?? null,
                        'transaction_id' => $validated['payment']['transaction_id'] ?? null,
                        'transaction_type_id' => $initialPaymentTransactionType->id,
                    ];

                    app(PaymentService::class)->createCustomerPaymentAndUpdateInvoice($invoice, $paymentData);
                } catch (\Exception $e) {
                    \Log::error('Payment creation failed: ' . $e->getMessage());
                    throw $e;
                }
            }

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                $paymentMessage = ($paymentAmount > 0 || $paymentBdtAmount > 0)
                    ? ' with initial payment'
                    : '';

                return response()->json([
                    'success' => true,
                    'message' => 'Booking created successfully with ' . count($validated['passengers']) . ' passenger(s)' . $paymentMessage,
                    'url' => route('bookings.print', $booking->id)
                ]);
            }

            $paymentMessage = ($paymentAmount > 0 || $paymentBdtAmount > 0)
                ? ' and initial payment recorded'
                : '';

            return redirect()->route('bookings.print', $booking->id)
                ->with('success', 'Booking created successfully with ' . count($validated['passengers']) . ' passenger(s)' . $paymentMessage);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create booking: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', 'Failed to create booking: ' . $e->getMessage())->withInput();
        }
    }

    public function show(Booking $booking)
    {
        $booking->load([
            'customer',
            'passengers' => fn($q) => $q->approvedFingerprint(),
            'passengers.documents',
            'passengers.ticketFare',
            'user',
            'district',
            'package',
            'office',
            'invoice',
            'payments.vouchers',
        ]);

        $packages = Package::with(['ticketFare', 'visaSellingPrice'])->orderBy('package_name')->get()->map(function ($pkg) {
            return [
                'id' => $pkg->id,
                'package_name' => $pkg->package_name,
                'ticket_fare_id' => $pkg->ticket_fare_id,
                'visa_selling_price' => $pkg->visaSellingPrice?->selling_price ?? 0,
                'service_charge' => $pkg->service_charge ?? 0,
                'package_value' => ($pkg->ticketFare?->selling_fare ?? 0) + ($pkg->visaSellingPrice?->selling_price ?? 0) + ($pkg->service_charge ?? 0),
            ];
        });

        $ticketFares = TicketFare::with([
            'route.fromCity',
            'route.toCity',
            'route.returnCity',
            'route.multiSegments.fromCity',
            'route.multiSegments.toCity',
            'airline',
            'airlineClass.class',
            'groupTicket',
            'baggageAllowances',
        ])->get()->map(function ($fare) {
            $routeCode = '';
            $routeType = $fare->route->route_type?->value;

            if ($routeType === 'multi_city') {
                $segments = $fare->route->multiSegments->map(function ($seg) {
                    return $seg->fromCity?->code . '-' . $seg->toCity?->code;
                })->toArray();
                $routeCode = implode(', ', $segments);
            } elseif ($routeType === 'round') {
                $routeCode = $fare->route->fromCity?->code . '-' .
                    $fare->route->toCity?->code . '-' .
                    $fare->route->returnCity?->code;
            } else {
                $routeCode = $fare->route->fromCity?->code . '-' . $fare->route->toCity?->code;
            }

            return [
                'id' => $fare->id,
                'route' => $routeCode,
                'airline' => $fare->airline->name,
                'airline_class' => $fare->airlineClass->class?->name,
                'ticket_type' => $fare->ticket_type->value,
                'selling_fare' => $fare->selling_fare,
                'child_fare_percentage' => $fare->child_fare_percentage,
                'infant_fare_percentage' => $fare->infant_fare_percentage,
                'offer_price' => $fare->offer_price,
                'available_seats' => $fare->groupTicket?->ticket_qty ?? null,
                'route_type' => $routeType,
                'flight_type' => $fare->route->flight_type?->value,
                'baggage_allowances' => $fare->baggageAllowances->map(function ($ba) {
                    return [
                        'passenger_type' => $ba->passenger_type,
                        'travel_direction' => $ba->travel_direction,
                        'allowance' => $ba->allowance,
                    ];
                })->toArray(),
            ];
        });

        $currentCurrencyRate = \App\Models\CurrencyRate::orderBy('created_at', 'desc')->first();

        $rate = $currentCurrencyRate?->rate ?? 0;
        $totalAmount = $booking->invoice?->total_amount ?? 0;
        $paidAmount = $booking->invoice?->paid_amount ?? 0;
        $balance = $booking->invoice?->balance ?? 0;
        $totalAmountBdt = $rate > 0 ? $totalAmount * $rate : 0;
        $paidAmountBdt = $rate > 0 ? $paidAmount * $rate : 0;
        $balanceBdt = $rate > 0 ? $balance * $rate : 0;

        $originalTotal = (float) ($booking->total_value ?? 0);
        $originalTotalBdt = $rate > 0 ? $originalTotal * $rate : 0;
        $discountedTotalBdt = $totalAmountBdt;

        return view('bookings.show', compact(
            'booking', 'ticketFares', 'packages', 'currentCurrencyRate',
            'totalAmountBdt', 'paidAmountBdt', 'balanceBdt',
            'originalTotal', 'originalTotalBdt', 'discountedTotalBdt'
        ));
    }

    public function edit(Booking $booking)
    {
        $booking->load(['customer', 'passengers' => fn($q) => $q->approvedFingerprint(), 'district', 'office', 'package', 'documents', 'passengers.documents', 'passengers.ticketFare', 'fingerprintCharge']);

        $districts = District::orderBy('name')->get();
        $packages = Package::with(['ticketFare', 'visaSellingPrice'])->orderBy('package_name')->get()->map(function ($pkg) {
            return [
                'id' => $pkg->id,
                'package_name' => $pkg->package_name,
                'ticket_fare_id' => $pkg->ticket_fare_id,
                'visa_selling_price' => $pkg->visaSellingPrice?->selling_price ?? 0,
                'service_charge' => $pkg->service_charge ?? 0,
                'package_value' => ($pkg->ticketFare?->selling_fare ?? 0) + ($pkg->visaSellingPrice?->selling_price ?? 0) + ($pkg->service_charge ?? 0),
            ];
        });
        $offices = Office::orderBy('name')->get();

        $ticketFares = TicketFare::with([
            'route.fromCity',
            'route.toCity',
            'route.returnCity',
            'route.multiSegments.fromCity',
            'route.multiSegments.toCity',
            'airline',
            'airlineClass.class',
            'groupTicket',
            'baggageAllowances',
        ])->get()->map(function ($fare) {
            $routeCode = '';
            $routeType = $fare->route->route_type?->value;

            if ($routeType === 'multi_city') {
                $segments = $fare->route->multiSegments->map(function ($seg) {
                    return $seg->fromCity?->code . '-' . $seg->toCity?->code;
                })->toArray();
                $routeCode = implode(', ', $segments);
            } elseif ($routeType === 'round') {
                $routeCode = $fare->route->fromCity?->code . '-' .
                    $fare->route->toCity?->code . '-' .
                    $fare->route->returnCity?->code;
            } else {
                $routeCode = $fare->route->fromCity?->code . '-' . $fare->route->toCity?->code;
            }

            return [
                'id' => $fare->id,
                'route' => $routeCode,
                'airline' => $fare->airline->name,
                'airline_class' => $fare->airlineClass->class?->name,
                'ticket_type' => $fare->ticket_type->value,
                'selling_fare' => $fare->selling_fare,
                'child_fare_percentage' => $fare->child_fare_percentage,
                'infant_fare_percentage' => $fare->infant_fare_percentage,
                'offer_price' => $fare->offer_price,
                'available_seats' => $fare->groupTicket?->ticket_qty ?? null,
                'route_type' => $routeType,
                'flight_type' => $fare->route->flight_type?->value,
                'baggage_allowances' => $fare->baggageAllowances->map(function ($ba) {
                    return [
                        'passenger_type' => $ba->passenger_type,
                        'travel_direction' => $ba->travel_direction,
                        'allowance' => $ba->allowance,
                    ];
                })->toArray(),
            ];
        });

        $customers = \App\Models\Customer::orderBy('name')->get(['id', 'name', 'passport_no', 'iqama_no', 'mobile_no']);
        $currentCurrencyRate = \App\Models\CurrencyRate::orderBy('created_at', 'desc')->first();

        return view('bookings.edit', compact(
            'booking', 'districts', 'packages', 'offices', 'ticketFares', 'customers', 'currentCurrencyRate'
        ));
    }

    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'customer_id' => 'sometimes|required|exists:customers,id',
            'district_id' => 'nullable|exists:districts,id',
            'office_id' => 'nullable|exists:offices,id',
            'package_id' => 'nullable|exists:packages,id',
            'fingerprint_location' => 'nullable|in:office,home',
            'discount_type' => 'nullable|in:fixed,percentage',
            'discount_value' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
            'passengers' => 'nullable|array',
            'passengers.*.id' => 'nullable|exists:passengers,id',
        ]);

        try {
            $validated['discount_type'] = ($validated['discount_type'] ?? 'fixed') === 'fixed' ? 'fixed_amount' : 'percentage';
            $booking->update($validated);

            if (($validated['fingerprint_location'] ?? null) === 'office' && $booking->fingerprint) {
                $booking->fingerprint->update(['assigned_staff_id' => null]);
            }

            $booking = $booking->fresh();
            $invoiceData = $this->syncBookingFinancials($booking);

            $discountType = $booking->discount_type;
            if ($discountType instanceof \BackedEnum) {
                $discountType = $discountType->value;
            }

            $customerDocs = $request->file('booking_customer_docs', []);
            if (is_array($customerDocs) && count($customerDocs) > 0) {
                foreach ($customerDocs as $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                        $booking->documents()->create([
                            'owner_type' => 'booking',
                            'owner_id' => $booking->id,
                            'file_path' => $file->store('booking-docs', 'public'),
                            'display_name' => $file->getClientOriginalName(),
                        ]);
                    }
                }
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Discount applied successfully',
                    'invoice' => $invoiceData,
                    'discount' => [
                        'type' => $discountType,
                        'value' => (float) $booking->discount_value,
                        'amount' => (float) $booking->discount_amount,
                    ],
                ]);
            }

            return redirect()->route('bookings.show', $booking->id)
                ->with('success', 'Booking updated successfully');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update booking.',
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to update booking.')->withInput();
        }
    }

    public function updateFingerprintLocation(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'fingerprint_location' => 'required|in:home,office',
        ]);

        try {
            $booking->update(['fingerprint_location' => $validated['fingerprint_location']]);

            if ($validated['fingerprint_location'] === 'office' && $booking->fingerprint) {
                $booking->fingerprint->update(['assigned_staff_id' => null]);
            }

            $booking = $booking->fresh();
            $invoiceData = $this->syncBookingFinancials($booking);

            return response()->json([
                'success' => true,
                'message' => 'Fingerprint location updated successfully',
                'fingerprint_location' => $booking->fingerprint_location?->value,
                'invoice' => $invoiceData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update fingerprint location',
            ], 500);
        }
    }

    public function destroy(Booking $booking)
    {
        try {
            $booking->passengers()->delete();
            $booking->delete();
            return redirect()->route('bookings.index')
                ->with('success', 'Booking deleted successfully');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete booking.');
        }
    }

    public function addPassenger(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'passport_no' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'mobile_no' => 'nullable|string|max:20',
            'passport_expiry' => 'nullable|date',
            'service_required' => 'nullable|in:all,visa_only,ticket_only',
            'stay_duration' => 'nullable|integer|min:1',
            'flight_date_from' => 'nullable|date',
            'flight_date_to' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'gender' => 'nullable|in:male,female',
            'ticket_fare_id' => 'nullable|exists:ticket_fares,id',
        ]);

        $passengerType = $this->bookingService->calculatePassengerType(
            $validated['date_of_birth'],
            $validated['stay_duration'] ?? null
        );

        $validated['booking_id'] = $booking->id;
        $validated['passenger_type'] = $passengerType;
        $validated['service_required'] = $validated['service_required'] ?? 'All';
        $validated['stay_duration'] = $validated['stay_duration'] ?? 14;
        $validated['ticket_fare_id'] = ($validated['service_required'] ?? '') === 'visa_only'
            ? null
            : ($validated['ticket_fare_id'] ?? $booking->package?->ticket_fare_id);

        $passenger = Passenger::create($validated);

        $fingerprint = Fingerprint::where('booking_id', $booking->id)->first();
        if ($fingerprint) {
            FingerprintDetail::create([
                'fingerprint_id' => $fingerprint->id,
                'passenger_id' => $passenger->id,
                'status' => 'none',
            ]);
        }

        $booking->update(['pax_qty' => $booking->passengers()->count()]);
        $booking = $booking->fresh();

        $invoiceData = $this->syncBookingFinancials($booking);

        $passenger = $passenger->fresh()->load('ticketFare');

        return response()->json([
            'success' => true,
            'message' => 'Passenger added successfully',
            'passenger' => $passenger,
            'display_total' => $passenger->package_value ?? 0,
            'invoice' => $invoiceData,
        ]);
    }

    public function removePassenger(Booking $booking, Passenger $passenger)
    {
        if ($passenger->booking_id !== $booking->id) {
            return response()->json(['success' => false, 'message' => 'Passenger does not belong to this booking'], 403);
        }

        try {
            $detail = FingerprintDetail::where('passenger_id', $passenger->id)->first();
            if ($detail) {
                $detail->rescheduledFingerprints()->delete();
                $detail->delete();
            }

            $passenger->delete();
            $booking->update(['pax_qty' => $booking->passengers()->count()]);
            $booking = $booking->fresh();

            $invoiceData = $this->syncBookingFinancials($booking);

            return response()->json([
                'success' => true,
                'message' => 'Passenger removed successfully',
                'invoice' => $invoiceData,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to remove passenger'], 500);
        }
    }

    public function calculatePassengerType(Request $request)
    {
        $dateOfBirth = $request->input('date_of_birth');
        $stayDuration = $request->input('stay_duration');
        
        if (!$dateOfBirth) {
            return response()->json(['passenger_type' => null]);
        }

        $passengerType = $this->bookingService->calculatePassengerType($dateOfBirth, $stayDuration);

        return response()->json([
            'passenger_type' => $passengerType,
        ]);
    }

    public function getFingerprintCharge(Request $request)
    {
        $districtId = $request->input('district_id');
        $location = $request->input('location', 'Office');

        if (!$districtId) {
            return response()->json(['error' => 'District is required'], 422);
        }

        $fingerprintCharge = FingerprintCharge::where('district_id', $districtId)->first();

        if (!$fingerprintCharge) {
            return response()->json(['error' => 'No fingerprint charge found for selected district. Please contact admin to set up fingerprint charges.'], 422);
        }

        $charge = $location === 'home' ? $fingerprintCharge->fingerprint_charge : 0;

        return response()->json([
            'charge' => $charge,
            'fingerprint_charge_id' => $fingerprintCharge->id
        ]);
    }

    public function print(Booking $booking)
    {
        $booking = Booking::with([
            'customer',
            'office',
            'package',
            'currencyRate',
            'passengers' => fn($q) => $q->approvedFingerprint(),
            'passengers.ticketFare.airline',
            'passengers.ticketFare.airlineClass.travelClass',
            'passengers.ticketFare.route',
            'passengers.ticketFare.route.fromCity',
            'passengers.ticketFare.route.toCity',
            'passengers.ticketFare.route.returnCity',
            'passengers.ticketFare.route.multiSegments.fromCity',
            'passengers.ticketFare.route.multiSegments.toCity',
            'passengers.ticketFare.baggageAllowances',
            'payments',
            'invoice'
        ])->findOrFail($booking->id);

        $subTotal = (float) $booking->passengers->sum('package_value');

        $fingerprintCharge = 0;
        $fpLocation = $booking->fingerprint_location;
        if ($fpLocation instanceof \BackedEnum) {
            $fpLocation = $fpLocation->value;
        }
        if ($fpLocation && strtolower($fpLocation) !== 'office') {
            $fpCharge = FingerprintCharge::where('district_id', $booking->district_id)->first();
            $fingerprintCharge = $fpCharge ? (float) $fpCharge->fingerprint_charge : 0;
        }

        $totalPackages = $booking->pax_qty;

        $discount = 0;
        $baseForDiscount = $subTotal + $fingerprintCharge;
        if ($booking->discount_value && $booking->discount_value > 0 && $booking->discount_type) {
            $discountType = $booking->discount_type;
            if ($discountType instanceof \BackedEnum) {
                $discountType = $discountType->value;
            }
            $discount = $discountType === 'percentage'
                ? $baseForDiscount * ($booking->discount_value / 100)
                : min($booking->discount_value, $baseForDiscount);
        }

        $grandTotal = $subTotal + $fingerprintCharge - $discount;

        // Currency rate resolution
        $currencyRate = $booking->currencyRate;
        if (!$currencyRate) {
            $currencyRate = CurrencyRate::where('created_at', '<=', $booking->created_at)
                ->orderBy('created_at', 'desc')
                ->first();
        }
        $rate = $currencyRate?->rate;
        $useBdt = $rate && $rate > 0;
        $currencySuffix = $useBdt ? 'BDT' : 'SAR';

        if ($useBdt) {
            $displayRate = (float) $rate;
            $displaySubTotal = $subTotal * $rate;
            $displayFingerprintCharge = $fingerprintCharge * $rate;
            $displayDiscount = $discount * $rate;
            $displayGrandTotal = $grandTotal * $rate;
            $displayCurrentPaid = (float) ($booking->payments->last()?->bdt_amount ?? 0);
            $displayTotalPaid = (float) $booking->payments->sum('bdt_amount');
        } else {
            $displayRate = 0;
            $displaySubTotal = $subTotal;
            $displayFingerprintCharge = $fingerprintCharge;
            $displayDiscount = $discount;
            $displayGrandTotal = $grandTotal;
            $displayCurrentPaid = (float) ($booking->payments->last()?->amount ?? 0);
            $displayTotalPaid = (float) ($booking->payments->sum('amount'));
        }

        $displayPreviousPaid = $displayTotalPaid - $displayCurrentPaid;
        $displayDueAmount = $displayGrandTotal - $displayTotalPaid;
        $totalPaid = (float) ($booking->invoice->paid_amount ?? 0);
        $currentPaid = (float) ($booking->payments->last()?->amount ?? 0);
        $dueAmount = $grandTotal - $totalPaid;

        $conditions = BookingCondition::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('bookings.invoice-print', compact(
            'booking',
            'subTotal',
            'fingerprintCharge',
            'totalPackages',
            'discount',
            'grandTotal',
            'totalPaid',
            'currentPaid',
            'dueAmount',
            'conditions',
            'useBdt',
            'currencySuffix',
            'displayRate',
            'displaySubTotal',
            'displayFingerprintCharge',
            'displayDiscount',
            'displayGrandTotal',
            'displayCurrentPaid',
            'displayTotalPaid',
            'displayPreviousPaid',
            'displayDueAmount'
        ));
    }

    public function storePayment(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'amount_bdt' => 'nullable|numeric|min:0',
            'currency' => 'nullable|in:SAR,BDT',
            'payment_method' => 'nullable|in:cash,bank',
            'bank_method' => 'nullable|string|max:255',
            'transaction_id' => 'nullable|string|max:255',
        ]);

        $amount = $validated['amount'] ?? 0;
        $bdtAmount = $validated['amount_bdt'] ?? 0;

        if ($amount == 0 && $bdtAmount == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter payment amount'
            ], 422);
        }

        try {
            $invoice = $booking->invoice;
            if (!$invoice) {
                $invoice = Invoice::create([
                    'booking_id' => $booking->id,
                    'total_amount' => $booking->total_value ?? 0,
                    'paid_amount' => 0,
                    'balance' => $booking->total_value ?? 0,
                ]);
            }

            $dueCollectionTransactionType = TransactionType::where('name', 'Due Collection')->first();
            if (!$dueCollectionTransactionType) {
                throw new \Exception('Due Collection transaction type not found. Please seed transaction types.');
            }

            $bankId = null;
            if (($validated['payment_method'] ?? '') === 'bank' && !empty($validated['bank_method'])) {
                $bank = Bank::where('name', $validated['bank_method'])->first();
                $bankId = $bank?->id;
            }

            $paymentData = [
                'branch_id' => $booking->branch_id,
                'user_id' => auth()->id(),
                'payment_date' => now()->toDateString(),
                'payment_method' => $validated['payment_method'] ?? 'cash',
                'amount' => $amount,
                'bdt_amount' => $bdtAmount,
                'currency' => $validated['currency'] ?? 'SAR',
                'bank_id' => $bankId,
                'transaction_id' => $validated['transaction_id'] ?? null,
                'transaction_type_id' => $dueCollectionTransactionType->id,
            ];

            [$payment, $voucher] = app(PaymentService::class)->createCustomerPaymentAndUpdateInvoice($invoice, $paymentData);

            return response()->json([
                'success' => true,
                'message' => 'Payment saved successfully',
                'payment' => $payment,
                'voucher' => $voucher,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function recalculatePassengerValue(Passenger $passenger)
    {
        $packageValue = $this->bookingService->calculatePackageValue($passenger);
        $passenger->update(['package_value' => $packageValue]);
        $booking = $passenger->booking->fresh();

        $invoiceData = $this->syncBookingFinancials($booking);

        return response()->json([
            'package_value' => $packageValue,
            'total_value' => $booking->total_value,
            'invoice' => $invoiceData,
        ]);
    }
}