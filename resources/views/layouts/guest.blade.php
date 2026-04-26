<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-8 pb-10 sm:pt-0 sm:pb-0 px-4 bg-gradient-to-br from-slate-100 via-slate-50 to-emerald-50/60">
            <div class="text-center">
                <a href="/" class="inline-flex flex-col items-center gap-1 group">
                    <span class="text-2xl sm:text-3xl font-semibold text-slate-800 tracking-tight group-hover:text-emerald-800 transition-colors">
                        {{ config('app.name', 'Laravel') }}
                    </span>
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-8 px-6 py-8 sm:px-8 sm:py-9 bg-white/90 backdrop-blur-sm border border-slate-200/80 shadow-[0_20px_50px_-15px_rgba(15,23,42,0.15)] overflow-hidden rounded-2xl
                [&_input]:transition-colors [&_input]:focus:border-emerald-500 [&_input]:focus:ring-emerald-500/30">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
