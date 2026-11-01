@props(['groups', 'current'])

{{-- Item status only, grouped by subtest with counts (FR-T3, M5-04). Filters and the mobile drawer are Alpine-only. --}}
<div x-data="{ open: false, filter: 'all' }" x-on:navigator-open.window="open = true">
    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="navigator"
            class="rounded border border-line px-4 py-2 lg:hidden">
        {{ __('Items') }}
    </button>

    <nav id="navigator" aria-label="{{ __('Items') }}" tabindex="-1" x-bind:class="{ 'max-lg:hidden': ! open }"
        {{ $attributes->class('mt-3 space-y-3 focus:outline-none max-lg:hidden lg:mt-0') }}>
        <div role="group" aria-label="{{ __('Show') }}" class="flex flex-wrap gap-2 text-sm">
            @foreach (['all' => __('All'), 'flagged' => __('Flagged only'), 'unanswered' => __('Unanswered only')] as $value => $label)
                <button type="button" x-on:click="filter = '{{ $value }}'" x-bind:aria-pressed="filter === '{{ $value }}'"
                        class="rounded border border-line px-3 py-1 aria-pressed:border-accent aria-pressed:font-medium">{{ $label }}</button>
            @endforeach
        </div>

        @foreach ($groups as $group)
            <div>
                <h2 class="mb-1 text-sm font-medium">
                    {{ $group['name'] }}
                    <span class="font-normal text-muted">
                        · {{ __(':answered / :total answered', ['answered' => $group['answered'], 'total' => $group['total']]) }}
                        · {{ __(':count flagged', ['count' => $group['flagged']]) }}
                    </span>
                </h2>
                <ol class="flex flex-wrap gap-1">
                    @foreach ($group['entries'] as $entry)
                        @php($state = $entry['answered'] ? __('answered') : __('unanswered'))
                        <li wire:key="nav-{{ $entry['position'] }}"
                            x-show="filter === 'all' || (filter === 'flagged' && {{ $entry['flagged'] ? 'true' : 'false' }}) || (filter === 'unanswered' && {{ $entry['answered'] ? 'false' : 'true' }})">
                            <button type="button" x-on:click="open = false; $store.keyboard.go('goTo', {{ $entry['position'] }})"
                                    data-flagged="{{ (int) $entry['flagged'] }}"
                                    aria-label="{{ __('Item :position, :state', ['position' => $entry['position'], 'state' => $state]).($entry['flagged'] ? ', '.__('flagged') : '') }}"
                                    @if ($entry['position'] === $current) aria-current="step" @endif
                                    @class([
                                        'relative h-9 w-9 rounded border text-sm tabular-nums',
                                        'bg-accent text-on-accent border-accent' => $entry['answered'],
                                        'border-line' => ! $entry['answered'],
                                        'ring-2 ring-focus ring-offset-2 ring-offset-canvas' => $entry['position'] === $current,
                                    ])>
                                {{ $entry['position'] }}
                                @if ($entry['flagged'])
                                    <span aria-hidden="true" class="absolute -right-1 -top-1 text-xs text-danger">⚑</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endforeach
    </nav>
</div>
