<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\StayDurationLimit;

class StorePassengerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $limits = StayDurationLimit::getOrCreate();

        return [
            'booking_id' => 'nullable|exists:bookings,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'passport_no' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'mobile_no' => 'nullable|string|max:20',
            'passport_expiry' => 'nullable|date',
            'service_required' => 'nullable|in:All,Visa Only,Ticket Only',
            'stay_duration' => 'nullable|integer|min:' . $limits->min_days . '|max:' . $limits->max_days,
            'flight_date_from' => 'nullable|date',
            'flight_date_to' => 'nullable|date',
            'address' => 'nullable|string|max:500',
            'passenger_type' => 'nullable|in:adult,child,infant',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'passport_no.required' => 'Passport number is required.',
            'date_of_birth.required' => 'Date of birth is required.',
            'date_of_birth.before' => 'Date of birth must be before today.',
        ];
    }
}