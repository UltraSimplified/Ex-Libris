<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <script id="mcjs">!function(c,h,i,m,p){m=c.createElement(h),p=c.getElementsByTagName(h)[0],m.async=1,m.src=i,p.parentNode.insertBefore(m,p)}(document,"script","https://chimpstatic.com/mcjs-connected/js/users/91c45d0b7e999e4abc471b3f8/c4d0a1d5868a844f3fec528b3.js");</script>
        <script>
            const theme = localStorage.theme;
            const goblin = localStorage.appearance === 'goblin';
            document.documentElement.classList.toggle(
                'dark',
                goblin || theme === 'dark' || (!theme && matchMedia('(prefers-color-scheme: dark)').matches)
            );
            // Green only inside the editing area; app.css restyles .goblin.
            document.documentElement.classList.toggle(
                'goblin',
                goblin && /^\/(dashboard|settings)(\/|$)/.test(location.pathname)
            );
        </script>
        {{-- The Adobe Fonts kit, linked here rather than imported from app.css so
             the browser asks for it at once instead of after app.css arrives. --}}
        <link rel="preconnect" href="https://use.typekit.net" crossorigin>
        <link rel="preconnect" href="https://p.typekit.net">
        <link rel="stylesheet" href="https://use.typekit.net/owb2ffh.css">
        @vite('resources/css/app.css')
        {{-- The page's largest picture, which without SSR the browser would
             only learn of once the app has run. --}}
        @isset($preloadImage)
            <link rel="preload" as="image" href="{{ $preloadImage }}" fetchpriority="high">
        @endisset
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

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        @php
            // A page that sends no meta of its own is still described: by its
            // title, and the site's description and latest cover.
            $meta = $page['props']['meta'] ?? \App\Support\SiteMeta::for(
                strip_tags((string) ($page['props']['title'] ?? config('app.name')))
            )->toArray();
            $siteName = config('app.name');
        @endphp
        @if($meta)
            <meta name="description" content="{{ $meta['description'] }}">
            <link rel="canonical" href="{{ $meta['url'] }}">

            {{-- Open Graph (Facebook, WhatsApp, LinkedIn, iMessage) --}}
            <meta property="og:site_name" content="{{ $siteName }}">
            <meta property="og:type" content="{{ $meta['type'] }}">
            <meta property="og:title" content="{{ $meta['title'] }}">
            <meta property="og:description" content="{{ $meta['description'] }}">
            <meta property="og:url" content="{{ $meta['url'] }}">
            @if($meta['image'])
                <meta property="og:image" content="{{ $meta['image'] }}">
            @endif

            {{-- Twitter/X --}}
            <meta name="twitter:card" content="{{ $meta['image'] ? 'summary_large_image' : 'summary' }}">
            <meta name="twitter:title" content="{{ $meta['title'] }}">
            <meta name="twitter:description" content="{{ $meta['description'] }}">
            @if($meta['image'])
                <meta name="twitter:image" content="{{ $meta['image'] }}">
            @endif

            {{-- Structured data --}}
            @if($meta['jsonLd'])
                <script type="application/ld+json">{!! json_encode($meta['jsonLd'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
            @endif
        @endif

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia

        @if (config('cookies_consent.enabled', true))
            <x-laravel-cookie-guard></x-laravel-cookie-guard>
            <x-laravel-cookie-guard-scripts></x-laravel-cookie-guard-scripts>
        @endif
    </body>
</html>
