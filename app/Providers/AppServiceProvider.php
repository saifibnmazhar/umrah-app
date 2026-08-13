<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\FingerprintDetail;
use App\Models\IssuedTicket;
use App\Models\Passenger;
use App\Models\VisaSubmission;
use App\Observers\BookingObserver;
use App\Observers\FingerprintDetailObserver;
use App\Observers\IssuedTicketObserver;
use App\Observers\PassengerObserver;
use App\Observers\VisaSubmissionObserver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Booking::observe(BookingObserver::class);
        Passenger::observe(PassengerObserver::class);
        FingerprintDetail::observe(FingerprintDetailObserver::class);
        VisaSubmission::observe(VisaSubmissionObserver::class);
        IssuedTicket::observe(IssuedTicketObserver::class);

        Blade::directive('currency', function ($expression) {
            $parts = explode(',', $expression);
            $amount = trim($parts[0]);
            $decimals = isset($parts[1]) ? trim($parts[1]) : 2;
            $decimals = is_numeric($decimals) ? (int) $decimals : 2;
            $rate = isset($parts[2]) ? trim($parts[2]) : 'null';
            $bdtAmount = isset($parts[3]) ? trim($parts[3]) : 'null';

            return "<?php
                \$__val = {$amount} ?? 0;
                \$__dec = {$decimals};
                \$__rate = {$rate};
                \$__bdt = {$bdtAmount};
                \$__dataBdt = \$__bdt !== null ? ' data-bdt=\"' . number_format((float) \$__bdt, 6, '.', '') . '\"' : '';
                echo '<span class=\"currency-display\" data-sar=\"' . number_format((float) \$__val, 6, '.', '') . '\" data-dec=\"' . \$__dec . '\" data-rate=\"' . (\$__rate !== null ? (float) \$__rate : '') . '\"' . \$__dataBdt . '>' . number_format((float) \$__val, \$__dec, '.', '') . '</span>';
            ?>";
        });

        Blade::directive('endcurrency', function () {
            return '';
        });
    }
}
