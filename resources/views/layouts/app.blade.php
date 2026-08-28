<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'BM Umrah')</title>
    @php
        use App\Services\CurrencyRateService;
        use App\Models\StayDurationLimit;
        $__currencyRate = (float) (app(CurrencyRateService::class)->getRateForDate(now())?->rate ?? 0);
        $__stayDurationLimits = StayDurationLimit::first();
    @endphp
    <script>window.__currencyRate = {{ $__currencyRate }};</script>
    <script>window.__stayDurationLimits = { minDays: {{ $__stayDurationLimits?->min_days ?? 1 }}, maxDays: {{ $__stayDurationLimits?->max_days ?? 85 }} };</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 min-h-screen px-4">
    @auth
        @section('navigation')
            @include('partials.nav')
        @show
    @endauth

    <main class="py-6">
        @yield('content')
    </main>

    @auth
        @include('components.toast')
        @if(session('toast'))
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const toast = @json(session('toast'));
                    if (toast && window.showToast) window.showToast(toast.message, toast.type);
                });
            </script>
        @endif
    @endauth

    @stack('scripts')
</body>
</html>