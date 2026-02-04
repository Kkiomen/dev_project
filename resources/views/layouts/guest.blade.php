<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ __('app.meta_title') }}</title>
        <meta name="description" content="{{ __('app.meta_description') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-300 antialiased">
        <div class="relative min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-brand-bg overflow-hidden">
            {{-- Decorative glow orbs --}}
            <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-indigo-500/20 rounded-full blur-3xl animate-pulse-glow pointer-events-none"></div>
            <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl animate-pulse-glow pointer-events-none" style="animation-delay: 2s;"></div>

            <div class="relative z-10 flex flex-col items-center w-full">
                <div>
                    <a href="/">
                        <img src="{{ asset('assets/images/logo_aisello_white.svg') }}" alt="Logo" class="h-16 w-auto" />
                    </a>
                </div>

                <div class="w-full sm:max-w-md mt-6 px-6 py-8 bg-brand-surface border border-brand-border shadow-lg overflow-hidden rounded-xl">
                    {{ $slot }}
                </div>

                <a href="/" class="mt-6 text-sm text-gray-500 hover:text-white transition-colors duration-200">
                    &larr; {{ __('Back to home') }}
                </a>
            </div>
        </div>
    </body>
</html>
