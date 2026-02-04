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
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @stack('styles')
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        @stack('scripts')

        <script>
            // Start Alpine after all scripts are loaded (including Vite modules)
            function startAlpine() {
                if (window.Alpine && !window.Alpine._started) {
                    window.Alpine.start();
                    window.Alpine._started = true;
                } else if (!window.Alpine) {
                    // Alpine not loaded yet, try again
                    requestAnimationFrame(startAlpine);
                }
            }
            // Run after all modules and scripts are done
            if (document.readyState === 'complete') {
                startAlpine();
            } else {
                window.addEventListener('load', startAlpine);
            }
        </script>
    </body>
</html>
