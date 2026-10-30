<x-layouts::app :title="__('Privacy')">
    <div class="mx-auto max-w-2xl space-y-6">
        <h1 class="text-2xl font-semibold">{{ __('Privacy') }}</h1>
        @include('partials.privacy-summary')
    </div>
</x-layouts::app>
