@php
    $attempt = $this->attempt;
    $item = $this->item;
    $revealed = $this->mayReveal($item);
    $passage = $item->snapshot['passage'] ?? null;
    $unanswered = collect($this->navigator)->where('answered', false)->count();
    $flagged = collect($this->navigator)->where('flagged', true)->count();
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-lg font-semibold">
            {{ $attempt->subtest?->name ?? $attempt->mode->label() }}
            <span class="text-muted">·</span>
            {{ __('Item :position of :total', ['position' => $position, 'total' => $attempt->item_count]) }}
        </h1>
        <x-attempt.save-indicator />
    </div>

    @if ($notice)
        <p role="alert" class="rounded border border-danger px-3 py-2 text-sm text-danger">{{ $notice }}</p>
    @endif

    <div @class(['grid gap-6', 'lg:grid-cols-2' => $passage])>
        @if ($passage)
            <x-attempt.passage :passage="$passage" />
        @endif

        <div class="space-y-4">
            <x-attempt.stem :snapshot="$item->snapshot" />
            <x-attempt.choices :item="$item" :revealed="$revealed" :locked="$item->revealed_at !== null" />

            @if ($revealed)
                <section aria-label="{{ __('Explanation') }}" class="rounded border border-line bg-surface p-4">
                    <h2 class="mb-1 text-sm font-medium">{{ __('Explanation') }}</h2>
                    @if (filled($item->snapshot['explanation'] ?? null))
                        <x-markdown :text="$item->snapshot['explanation']" />
                    @else
                        <p class="text-sm text-muted">{{ __('No explanation yet') }}</p>
                    @endif
                </section>
            @endif
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="prev" @disabled($position === 1)
                class="rounded border border-line px-4 py-2 disabled:opacity-50">{{ __('Previous') }}</button>
        <button type="button" wire:click="toggleFlag" aria-pressed="{{ $item->flagged ? 'true' : 'false' }}"
                class="rounded border border-line px-4 py-2 aria-pressed:border-danger">
            {{ $item->flagged ? __('Unflag') : __('Flag for review') }}
        </button>
        <button type="button" wire:click="next" @disabled($position === $attempt->item_count)
                class="rounded border border-line px-4 py-2 disabled:opacity-50">{{ __('Next') }}</button>
        <button type="button" wire:click="submit"
                wire:confirm="{{ __('Submit now? :unanswered unanswered and :flagged flagged.', ['unanswered' => $unanswered, 'flagged' => $flagged]) }}"
                class="ml-auto rounded bg-accent px-4 py-2 font-medium text-on-accent">{{ __('Submit') }}</button>
    </div>

    <x-attempt.navigator :entries="$this->navigator" :current="$position" />
</div>
