<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Fingerprint;
use App\Models\FingerprintDetail;
use App\Models\Invoice;
use App\Models\IssuedTicket;
use App\Models\Package;
use App\Models\Passenger;
use App\Models\RefundedTicket;
use App\Models\ReIssuedTicket;
use App\Models\VisaSubmission;
use App\Observers\BookingObserver;
use App\Observers\FingerprintDetailObserver;
use App\Observers\FingerprintObserver;
use App\Observers\InvoiceObserver;
use App\Observers\IssuedTicketObserver;
use App\Observers\PackageObserver;
use App\Observers\PassengerObserver;
use App\Observers\RefundedTicketObserver;
use App\Observers\ReIssuedTicketObserver;
use App\Observers\VisaSubmissionObserver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
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
        // The app runs behind an ISPConfig reverse proxy that terminates TLS.
        // Without this, Laravel generates http:// URLs (the internal Docker
        // network scheme), causing CORS errors and broken redirects.
        if (self::shouldForceHttps($this->app->environment())) {
            URL::forceScheme('https');
        }

        Booking::observe(BookingObserver::class);
        Passenger::observe(PassengerObserver::class);
        FingerprintDetail::observe(FingerprintDetailObserver::class);
        VisaSubmission::observe(VisaSubmissionObserver::class);
        IssuedTicket::observe(IssuedTicketObserver::class);
        Invoice::observe(InvoiceObserver::class);
        ReIssuedTicket::observe(ReIssuedTicketObserver::class);
        RefundedTicket::observe(RefundedTicketObserver::class);
        Fingerprint::observe(FingerprintObserver::class);
        Package::observe(PackageObserver::class);

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

    /**
     * Whether generated URLs should be forced to the https scheme.
     *
     * Both production and staging run behind the ISPConfig reverse proxy that
     * terminates TLS, so without forcing https Laravel emits http:// URLs
     * (the internal Docker network scheme). On https:// pages this triggers
     * mixed-content blocks on fetch() calls and http:// redirects.
     */
    public static function shouldForceHttps(string $environment): bool
    {
        return in_array($environment, ['production', 'staging'], true);
    }
}
