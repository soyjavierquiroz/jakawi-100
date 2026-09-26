<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-appearance="{{ $appearance ?? 'system' }}" data-appearance-authenticated="{{ ($appearanceIsAuthenticated ?? false) ? 'true' : 'false' }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="JAKAWI">
        <meta name="application-name" content="JAKAWI">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                try {
                    const serverAppearance = '{{ $appearance ?? "system" }}';
                    let appearance = serverAppearance;

                    if (!{{ ($appearanceIsAuthenticated ?? false) ? 'true' : 'false' }} && serverAppearance === 'system') {
                        const storedAppearance = window.localStorage.getItem('appearance');
                        appearance = ['system', 'light', 'dark'].includes(storedAppearance) ? storedAppearance : 'system';
                    }

                    if (appearance === 'dark' || (appearance === 'system' && window.matchMedia?.('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                } catch {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #FFF8F2;
            }

            html.dark {
                background-color: #111111;
            }
        </style>

        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        {{-- Temporary application icon until final JAKAWI mark is approved. --}}
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
