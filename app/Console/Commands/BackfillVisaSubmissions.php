<?php

namespace App\Console\Commands;

use App\Models\Passenger;
use App\Models\VisaSellingPrice;
use App\Models\VisaSubmission;
use Illuminate\Console\Command;

class BackfillVisaSubmissions extends Command
{
    protected $signature = 'umrah:backfill-visa-submissions';

    protected $description = 'Create a pending VisaSubmission for every passenger that does not have one';

    public function handle(): void
    {
        $count = 0;

        Passenger::whereDoesntHave('visaSubmission')
            ->where('service_required', '!=', 'ticket_only')
            ->chunk(100, function ($passengers) use (&$count) {
                foreach ($passengers as $passenger) {
                    $visaSellingPriceId = $passenger->booking?->package?->visa_selling_price_id;

                    VisaSubmission::create([
                        'passenger_id' => $passenger->id,
                        'visa_selling_price_id' => $visaSellingPriceId ?? VisaSellingPrice::latest('id')->value('id'),
                        'status' => 'pending',
                    ]);

                    $count++;
                }
            });

        $this->info("Created {$count} visa submission(s) for passengers without one.");
    }
}
