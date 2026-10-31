@props(['entries', 'current', 'subtests' => []])

{{-- Item status only; grouped by subtest (FR-T3). --}}
<nav id="navigator" aria-label="{{ __('Items') }}" tabindex="-1" {{ $attributes->class('space-y-3 focus:outline-none') }}>
    @foreach (collect($entries)->groupBy('subtest') as $subtestId => $group)
        <div>
            @if (count($subtests) > 1)
                <h2 class="mb-1 text-sm font-medium">{{ $subtests[$subtestId] ?? '' }}</h2>
            @endif
            <ol class="flex flex-wrap gap-1">
                @foreach ($group as $entry)
                    @php($state = $entry['answered'] ? __('answered') : __('unanswered'))
                    <li>
                        <button type="button" wire:click="goTo({{ $entry['position'] }})"
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
