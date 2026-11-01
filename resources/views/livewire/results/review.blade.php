@php($items = $this->items)
<div class="mx-auto max-w-3xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-lg font-semibold">{{ __('Review answers') }}</h1>
        <a href="{{ route('results.show', $attemptId) }}" class="text-sm underline">{{ __('Back to result') }}</a>
    </div>

    <nav aria-label="{{ __('Filter items') }}" class="flex flex-wrap gap-2">
        @foreach (['all' => __('All'), 'wrong' => __('Wrong'), 'flagged' => __('Flagged'), 'unanswered' => __('Unanswered')] as $value => $label)
            <button type="button" wire:click="$set('filter', '{{ $value }}')" aria-pressed="{{ $filter === $value ? 'true' : 'false' }}"
                    @class(['rounded border px-3 py-1 text-sm', 'border-accent bg-accent text-on-accent' => $filter === $value, 'border-line' => $filter !== $value])>
                {{ $label }}
            </button>
        @endforeach
    </nav>

    @forelse ($items as $item)
        @php($purged = $item->purged_at !== null)
        <article wire:key="review-{{ $item->id }}" aria-labelledby="item-{{ $item->position }}" class="space-y-3 rounded border border-line bg-surface p-4">
            <header class="flex flex-wrap items-center gap-2 text-sm">
                <h2 id="item-{{ $item->position }}" class="font-semibold">{{ __('Item :position', ['position' => $item->position]) }}</h2>
                <span class="text-muted">{{ $item->subtest?->name }}</span>
                @if ($item->selected_key === null)
                    <span class="rounded border border-line px-1.5 text-xs">{{ __('Not answered') }}</span>
                @elseif ($item->is_correct)
                    <span class="rounded border border-line px-1.5 text-xs">{{ __('Correct') }}</span>
                @else
                    <span class="rounded border border-danger px-1.5 text-xs text-danger">{{ __('Wrong') }}</span>
                @endif
                @if ($item->flagged)
                    <span class="rounded border border-line px-1.5 text-xs">{{ __('Flagged') }}</span>
                @endif
            </header>

            @if ($purged)
                {{-- Takedown: the snapshot text was replaced, so only the keys are left to show. --}}
                <p class="text-sm text-muted">{{ __('Content removed (takedown)') }}</p>
                <p class="text-sm">
                    {{ __('Your answer') }}: {{ $item->selected_key ?? __('Not answered') }}
                    · {{ __('Correct answer') }}: {{ $item->correct_key }}
                </p>
            @else
                @if ($item->question_id === null)
                    <p class="text-xs text-muted">{{ __('Removed from bank') }}</p>
                @endif

                @if ($item->snapshot['passage'] ?? null)
                    <details class="rounded border border-line p-3">
                        <summary class="cursor-pointer text-sm font-medium">{{ __('Passage') }}</summary>
                        <x-markdown :text="$item->snapshot['passage']['body']" class="mt-2 max-w-[70ch]" />
                        <x-attempt.media :items="$item->snapshot['passage']['media'] ?? []" />
                    </details>
                @endif

                <x-markdown :text="$item->snapshot['stem']" />
                <x-attempt.media :items="$item->snapshot['media'] ?? []" />

                <ul class="space-y-2">
                    @foreach ($item->snapshot['choices'] as $choice)
                        @php($isCorrect = $choice['key'] === $item->correct_key)
                        @php($isSelected = $choice['key'] === $item->selected_key)
                        <li @class([
                            'rounded border px-3 py-2',
                            'border-accent' => $isCorrect,
                            'border-danger' => $isSelected && ! $isCorrect,
                            'border-line' => ! $isCorrect && ! ($isSelected && ! $isCorrect),
                        ])>
                            <span class="font-semibold">{{ $choice['key'] }}.</span>
                            {{ $choice['body'] }}
                            <x-attempt.media :items="$choice['media'] ?? []" />
                            @if ($isSelected)
                                <strong class="ml-1 text-sm">({{ __('Your answer') }})</strong>
                            @endif
                            @if ($isCorrect)
                                <strong class="ml-1 text-sm">({{ __('Correct answer') }})</strong>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <section aria-label="{{ __('Explanation') }}" class="text-sm">
                    <h3 class="font-medium">{{ __('Explanation') }}</h3>
                    @if (filled($item->snapshot['explanation'] ?? null))
                        <x-markdown :text="$item->snapshot['explanation']" />
                    @else
                        <p class="text-muted">{{ __('No explanation yet') }}</p>
                    @endif
                </section>
            @endif
        </article>
    @empty
        <p class="text-sm text-muted">{{ __('No items match this filter.') }}</p>
    @endforelse

    {{ $items->links() }}
</div>
