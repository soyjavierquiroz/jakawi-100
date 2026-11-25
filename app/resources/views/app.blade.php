<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-appearance="{{ $appearance ?? 'system' }}" data-appearance-user="{{ auth()->id() ?? '' }}" data-appearance-authenticated="{{ ($appearanceIsAuthenticated ?? false) ? 'true' : 'false' }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        @if ($page['props']['landingSeo'] ?? null)
            @include('partials.landing-seo', ['seo' => $page['props']['landingSeo']])
        @elseif ($page['props']['landingSeoPrivate'] ?? false)
            <meta data-inertia="robots" name="robots" content="noindex,nofollow">
        @endif
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="JAKAWI">
        <meta name="application-name" content="JAKAWI">

        @include('partials.appearance-head')

        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            @unless ($page['component'] === 'coming-soon' || ($page['props']['landingSeo'] ?? null))
                <title>{{ config('app.name', 'Laravel') }}</title>
            @endunless
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
