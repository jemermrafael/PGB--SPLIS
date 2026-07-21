<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css'])
    @stack('head')
</head>
<body class="ob-print-shell min-h-screen bg-white text-slate-900">
    @yield('content')
    @stack('scripts')
</body>
</html>
