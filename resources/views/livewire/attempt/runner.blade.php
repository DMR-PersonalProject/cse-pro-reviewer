@php
    $item = $this->item;
    $revealed = $this->mayReveal($item);
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <h1 class="text-lg font-semibold">
        {{ __('Item :position of :total', ['position' => $position, 'total' => $this->attempt->item_count]) }}
    </h1>

    @if ($notice)
        <p role="alert" class="rounded border border-line bg-surface px-3 py-2 text-sm">{{ $notice }}</p>
    @endif

    <x-markdown :text="$item->snapshot['stem']" />

    <fieldset>
        <legend class="sr-only">{{ __('Choices') }}</legend>
        @foreach ($item->snapshot['choices'] as $choice)
            <label class="flex items-start gap-2" wire:key="choice-{{ $position }}-{{ $choice['key'] }}">
                <input type="radio" name="choice" value="{{ $choice['key'] }}" @checked($item->selected_key === $choice['key'])
                       wire:click="select('{{ $choice['key'] }}')">
                <span>{{ $choice['key'] }}. {{ $choice['body'] }}</span>
                @if ($revealed && $item->correct_key === $choice['key'])
                    <strong>{{ __('Correct answer') }}</strong>
                @endif
            </label>
        @endforeach
    </fieldset>

    @if ($revealed)
        <x-markdown :text="$item->snapshot['explanation'] ?? __('No explanation yet')" />
    @endif

    <nav aria-label="{{ __('Items') }}" class="flex flex-wrap gap-1">
        @foreach ($this->navigator as $entry)
            <button type="button" wire:click="goTo({{ $entry['position'] }})" data-flagged="{{ (int) $entry['flagged'] }}"
                    @if ($entry['position'] === $position) aria-current="step" @endif>{{ $entry['position'] }}</button>
        @endforeach
    </nav>

    <div class="flex gap-2">
        <button type="button" wire:click="prev">{{ __('Previous') }}</button>
        <button type="button" wire:click="toggleFlag">{{ __('Flag') }}</button>
        <button type="button" wire:click="next">{{ __('Next') }}</button>
        <button type="button" wire:click="submit">{{ __('Submit') }}</button>
    </div>
</div>
