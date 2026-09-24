<?php

namespace App\Http\Requests;

use App\Models\FlightDateGap;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Rules\FlightDateSlot;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $limits = StayDurationLimit::getOrCreate();

        return array_merge([
            'customer_id' => 'required|exists:customers,id',
            'district_id' => 'nullable|exists:districts,id',
            'office_id' => 'nullable|exists:offices,id',
            'package_id' => 'nullable|exists:packages,id',
            'branch_id' => 'nullable|exists:branches,id',
            'fingerprint_location' => 'nullable|in:Office,Home',
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
            'passengers.*.service_required' => 'nullable|in:All,Visa Only,Ticket Only',
            'passengers.*.stay_duration' => 'required|integer|min:'.$limits->min_days.'|max:'.$limits->max_days,
            'passengers.*.address' => 'nullable|string|max:500',
        ], self::flightDateRules((array) $this->input('passengers', [])));
    }

    /**
     * Per-index flight-date rules (explicit indexes, not wildcards) so the
     * paired from/to values reach the slot rule. Mirrors
     * BookingController::passengerFlightDateRules().
     */
    public static function flightDateRules(array $passengers): array
    {
        $defaultGap = FlightDateGap::first()?->gap ?? 30;
        $rules = [];

        foreach ($passengers as $index => $passenger) {
            if (! is_array($passenger)) {
                continue;
            }
            $fareId = $passenger['ticket_fare_id'] ?? $passenger['ticket_fare_inbound_id'] ?? null;
            $additionalGap = 0;
            if (is_numeric($fareId)) {
                $additionalGap = (int) (TicketFare::with('route')->find((int) $fareId)?->route?->additional_gap ?? 0);
            }
            $from = $passenger['flight_date_from'] ?? null;
            $rules["passengers.{$index}.flight_date_from"] = ['required', 'date'];
            $rules["passengers.{$index}.flight_date_to"] = [
                'required',
                'date',
                new FlightDateSlot(is_string($from) ? $from : null, $defaultGap, $additionalGap),
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'passengers.required' => 'At least one passenger is required.',
            'passengers.min' => 'At least one passenger is required.',
            'passengers.*.first_name.required' => 'First name is required for all passengers.',
            'passengers.*.last_name.required' => 'Last name is required for all passengers.',
            'passengers.*.passport_no.required' => 'Passport number is required for all passengers.',
            'passengers.*.date_of_birth.required' => 'Date of birth is required for all passengers.',
            'passengers.*.date_of_birth.before' => 'Date of birth must be before today.',
        ];
    }
}
