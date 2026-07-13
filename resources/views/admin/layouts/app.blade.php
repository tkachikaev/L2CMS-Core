<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#090c10">
    <title>@yield('title', 'Панель управления') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/admin/css/app.css') }}?v={{ config('cms.version') }}">
    @stack('head')
</head>
<body class="admin-body">
    @yield('body')
    @stack('scripts')
</body>
</html>
