<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'BM Umrah')</title>
    @php
        use App\Services\CurrencyRateService;
        $__currencyRate = app(CurrencyRateService::class)->getCurrentRateValue();
    @endphp
    <script>window.__currencyRate = {{ $__currencyRate }};</script>
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
    @endauth

    @stack('scripts')
</body>
</html>