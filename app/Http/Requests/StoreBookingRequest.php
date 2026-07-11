<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\StayDurationLimit;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $limits = StayDurationLimit::getOrCreate();

        return [
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
            'passengers.*.stay_duration' => 'nullable|integer|min:' . $limits->min_days . '|max:' . $limits->max_days,
            'passengers.*.flight_date_from' => 'nullable|date',
            'passengers.*.flight_date_to' => 'nullable|date|after:passengers.*.flight_date_from',
            'passengers.*.address' => 'nullable|string|max:500',
        ];
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