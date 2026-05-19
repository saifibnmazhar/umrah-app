<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'BM Umrah')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 min-h-screen px-4">
    @auth
        @include('partials.nav')
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