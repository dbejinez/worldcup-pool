<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=archivo:600,700,800&family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Flag icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@7.2.3/css/flag-icons.min.css">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div>
                <a href="/" class="flex flex-col items-center gap-2">
                    <x-application-logo class="w-16 h-16 fill-current text-brand-navy" />
                    <span class="font-display font-semibold text-xl tracking-wide text-brand-navy">{{ __('World Cup Pool') }}</span>
                </a>
            </div>

            {{-- Language switcher --}}
            <div class="mt-3 flex items-center gap-1">
                <form method="POST" action="{{ route('locale.switch', 'en') }}">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded transition
                                   {{ app()->getLocale() === 'en' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200' }}">
                        <span class="fi fi-us" style="width:1.1em;height:0.85em;display:inline-block;border-radius:2px;background-size:cover;background-position:center;"></span>
                        EN
                    </button>
                </form>
                <form method="POST" action="{{ route('locale.switch', 'es') }}">
                    @csrf
                    <button type="submit"
                            class="flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded transition
                                   {{ app()->getLocale() === 'es' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-200' }}">
                        <span class="fi fi-mx" style="width:1.1em;height:0.85em;display:inline-block;border-radius:2px;background-size:cover;background-position:center;"></span>
                        ES
                    </button>
                </form>
            </div>

            <div class="w-full sm:max-w-md mt-4 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
