<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Comparo surface colours (light / dark) before the stylesheet loads --}}
        <style>
            html {
                background-color: #FBFAF7;
            }

            html.dark {
                background-color: #08090B;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        {{--
            SEO head. With SSR running, Inertia renders the head from the page's <Head>.
            Without SSR this slot renders the same tags server-side from the `seo` prop,
            keyed with data-inertia so the client head manager replaces (never duplicates) them.
        --}}
        <x-inertia::head>
            @php($seo = $page['props']['seo'] ?? null)
            @if (is_array($seo))
                <title data-inertia="">{{ $seo['title'] }}</title>
                <meta name="description" content="{{ $seo['description'] }}" data-inertia="description">
                <meta name="robots" content="{{ $seo['robots'] }}" data-inertia="robots">
                <link rel="canonical" href="{{ $seo['canonical'] }}" data-inertia="canonical">
                @foreach ($seo['alternates'] as $alternate)
                    <link rel="alternate" hreflang="{{ $alternate['hreflang'] }}" href="{{ $alternate['href'] }}" data-inertia="alternate-{{ $alternate['hreflang'] }}">
                @endforeach
                @foreach ($seo['openGraph'] as $property => $content)
                    <meta property="og:{{ $property }}" content="{{ $content }}" data-inertia="og:{{ $property }}">
                @endforeach
                @foreach ($seo['jsonLd'] as $index => $document)
                    <script type="application/ld+json" data-inertia="jsonld-{{ $index }}">{!! json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
                @endforeach
            @else
                <title>{{ config('app.name', 'Comparo Performance') }}</title>
                {{-- Account, merchant and staff surfaces are never indexed. --}}
                <meta name="robots" content="noindex,nofollow" data-inertia="robots">
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
