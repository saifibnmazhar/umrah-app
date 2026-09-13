<?php

namespace App\Observers;

use App\Models\VisaSubmission;
use App\Models\VisaUpdateLog;
use App\Services\ProfitCalculationService;
use Illuminate\Support\Facades\Auth;

class VisaSubmissionObserver
{
    protected array $trackedFields = [
        'visa_agent_id',
        'commission_agent_id',
        'agent_commission',
        'net_visa_cost',
        'additional_cost',
        'final_cost',
        'visa_number',
        'remarks',
        'status',
    ];

    protected array $profitFields = [
        'status',
        'net_visa_cost',
        'agent_commission',
        'additional_cost',
        'visa_selling_price_id',
    ];

    public function created(VisaSubmission $visaSubmission): void
    {
        $this->recalculateProfit($visaSubmission);

        $visaSubmission->passenger->syncComputedStatus();
    }

    public function updated(VisaSubmission $visaSubmission): void
    {
        $changedTracked = array_intersect_key(
            $visaSubmission->getDirty(),
            array_flip($this->trackedFields)
        );

        $user = Auth::user();
        if ($user && ! empty($changedTracked)) {
            $original = $visaSubmission->getOriginal();
            $oldValues = array_intersect_key($original, $changedTracked);

            $action = $this->determineAction($oldValues, $changedTracked);

            VisaUpdateLog::create([
                'visa_submission_id' => $visaSubmission->id,
                'user_id' => $user->id,
                'action' => $action,
                'old_values' => $oldValues,
                'new_values' => $changedTracked,
            ]);
        }

        if ($visaSubmission->wasChanged($this->profitFields)) {
            $this->recalculateProfit($visaSubmission);
        }

        $visaSubmission->passenger->syncComputedStatus();
    }

    protected function determineAction(array $oldValues, array $newValues): string
    {
        $oldStatus = $this->statusValue($oldValues['status'] ?? null);
        $newStatus = $this->statusValue($newValues['status'] ?? null);

        if ($newStatus !== null) {
            if ($oldStatus === 'pending' && $newStatus === 'submitted') {
                return 'submitted';
            }
            if ($oldStatus === 'submitted' && $newStatus === 'issued') {
                return 'issued';
            }
            if ($newStatus === 'cancelled') {
                return 'cancelled';
            }
            if ($oldStatus === 'issued' && $newStatus === 'submitted') {
                return 'reverted';
            }
        }

        return 'edited';
    }

    protected function statusValue(mixed $status): ?string
    {
        if ($status instanceof \UnitEnum) {
            return isset($status->value) ? (string) $status->value : $status->name;
        }

        return is_scalar($status) ? (string) $status : null;
    }

    protected function recalculateProfit(VisaSubmission $visaSubmission): void
    {
        $passenger = $visaSubmission->passenger;

        if ($passenger?->booking) {
            app(ProfitCalculationService::class)->recalculateBookingProfit($passenger->booking);
        }
    }
}
