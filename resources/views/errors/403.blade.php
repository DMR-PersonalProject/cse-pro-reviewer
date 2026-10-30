<x-layouts::app :title="__('Access denied')">
    <div class="mx-auto max-w-md space-y-4 text-center">
        <p class="text-sm text-muted">403</p>
        <h1 class="text-2xl font-semibold">{{ __('You don’t have access to this page') }}</h1>
        <p class="text-muted">{{ __('If you think this is a mistake, ask an admin.') }}</p>
        <a href="{{ url('/') }}" class="inline-block font-medium underline">{{ __('Go to the dashboard') }}</a>
    </div>
</x-layouts::app>
