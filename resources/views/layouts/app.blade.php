@php
    $theme = \App\Enums\ThemeMode::fromRequest(request());
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme->value }}"
      @class(['dark' => $theme === \App\Enums\ThemeMode::Dark])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @include('partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-canvas font-sans text-ink antialiased">
    <a href="#main"
       class="sr-only rounded bg-accent px-3 py-2 text-on-accent focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50">
        {{ __('Skip to content') }}
    </a>

    @include('partials.header')

    <main id="main" tabindex="-1" class="px-4 py-10 focus:outline-none">
        {{ $slot }}
    </main>

    <footer class="border-t border-line px-4 py-6 text-center text-sm text-muted">
        <a href="{{ route('privacy') }}" class="underline">{{ __('Privacy') }}</a>
    </footer>

    @livewireScripts
</body>
</html>
