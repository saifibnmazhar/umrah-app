<?php

namespace App\Providers;

use App\Models\VisaSubmission;
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
        VisaSubmission::observe(VisaSubmissionObserver::class);

        Blade::directive('currency', function ($expression) {
            $parts = explode(',', $expression . ',2');
            $amount = trim($parts[0]);
            $decimals = isset($parts[1]) ? trim($parts[1]) : 2;
            $decimals = is_numeric($decimals) ? (int) $decimals : 2;
            return "<?php
                \$__val = {$amount} ?? 0;
                \$__dec = {$decimals};
                echo '<span class=\"currency-display\" data-sar=\"' . number_format((float) \$__val, 6, '.', '') . '\" data-dec=\"' . \$__dec . '\">SAR ' . number_format((float) \$__val, \$__dec) . '</span>';
            ?>";
        });

        Blade::directive('endcurrency', function () {
            return '';
        });
    }
}
