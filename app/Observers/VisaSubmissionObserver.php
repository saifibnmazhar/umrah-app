<?php

namespace App\Observers;

use App\Models\VisaSubmission;
use App\Models\VisaUpdateLog;
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

    public function created(VisaSubmission $visaSubmission): void
    {
        $visaSubmission->passenger->syncComputedStatus();
    }

    public function updated(VisaSubmission $visaSubmission): void
    {
        $visaSubmission->passenger->syncComputedStatus();

        $user = Auth::user();
        if (!$user) {
            return;
        }

        $dirty = $visaSubmission->getDirty();
        $changedTracked = array_intersect_key($dirty, array_flip($this->trackedFields));

        if (empty($changedTracked)) {
            return;
        }

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

    protected function determineAction(array $oldValues, array $newValues): string
    {
        if (isset($newValues['status'])) {
            $oldStatus = $oldValues['status'] ?? null;
            $newStatus = $newValues['status'];

            if ($oldStatus === 'pending' && $newStatus === 'submitted') {
                return 'submitted';
            }
            if ($oldStatus === 'submitted' && $newStatus === 'issued') {
                return 'issued';
            }
            if ($newStatus === 'cancelled') {
                return 'cancelled';
            }
        }

        return 'edited';
    }
}
