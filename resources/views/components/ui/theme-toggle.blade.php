@php
    use App\Enums\ThemeMode;

    $current = ThemeMode::fromRequest(request());
@endphp

<div role="group" aria-label="{{ __('Theme') }}"
     x-data="{ mode: @js($current->value) }"
     x-init="mode = window.cseTheme.mode"
     x-on:cse-theme.window="mode = $event.detail"
     {{ $attributes->class('inline-flex overflow-hidden rounded border border-line text-sm') }}>
    @foreach (ThemeMode::cases() as $option)
        <button type="button"
                data-theme-option="{{ $option->value }}"
                aria-pressed="{{ $current === $option ? 'true' : 'false' }}"
                x-bind:aria-pressed="(mode === @js($option->value)).toString()"
                x-on:click="window.cseTheme.set(@js($option->value))"
                class="px-2.5 py-1 text-muted hover:text-ink aria-pressed:bg-accent aria-pressed:text-on-accent">
            {{ $option->label() }}
        </button>
    @endforeach
</div>
