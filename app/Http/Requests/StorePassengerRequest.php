<?php

namespace App\Http\Requests;

use App\Models\FlightDateGap;
use App\Models\StayDurationLimit;
use App\Models\TicketFare;
use App\Rules\FlightDateSlot;
use Illuminate\Foundation\Http\FormRequest;

class StorePassengerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $limits = StayDurationLimit::getOrCreate();

        $fareId = $this->input('ticket_fare_id') ?? $this->input('ticket_fare_inbound_id');
        $additionalGap = 0;
        if (is_numeric($fareId)) {
            $additionalGap = (int) (TicketFare::with('route')->find((int) $fareId)?->route?->additional_gap ?? 0);
        }
        $from = $this->input('flight_date_from');

        return [
            'booking_id' => 'nullable|exists:bookings,id',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'passport_no' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'mobile_no' => 'nullable|string|max:20',
            'passport_expiry' => 'nullable|date',
            'service_required' => 'nullable|in:All,Visa Only,Ticket Only',
            'stay_duration' => 'required|integer|min:'.$limits->min_days.'|max:'.$limits->max_days,
            'flight_date_from' => ['required', 'date'],
            'flight_date_to' => ['required', 'date', new FlightDateSlot(
                is_string($from) ? $from : null,
                FlightDateGap::first()?->gap ?? 30,
                $additionalGap
            )],
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
