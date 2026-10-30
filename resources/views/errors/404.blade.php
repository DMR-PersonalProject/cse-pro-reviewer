<x-layouts::app :title="__('Page not found')">
    <div class="mx-auto max-w-md space-y-4 text-center">
        <p class="text-sm text-muted">404</p>
        <h1 class="text-2xl font-semibold">{{ __('Page not found') }}</h1>
        <p class="text-muted">{{ __('The page you asked for doesn’t exist or has moved.') }}</p>
        <a href="{{ url('/') }}" class="inline-block font-medium underline">{{ __('Go to the dashboard') }}</a>
    </div>
</x-layouts::app>
