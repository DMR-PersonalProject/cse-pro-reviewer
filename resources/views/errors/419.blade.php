<x-layouts::app :title="__('Your session expired')">
    <div class="mx-auto max-w-md space-y-4 text-center">
        <p class="text-sm text-muted">419</p>
        <h1 class="text-2xl font-semibold">{{ __('Your session expired') }}</h1>
        <p class="text-muted">{{ __('Your answers up to the last save are kept.') }} {{ __('Sign in again to pick up where you left off.') }}</p>
        <a href="{{ route('login') }}" class="inline-block font-medium underline">{{ __('Sign in') }}</a>
    </div>
</x-layouts::app>
