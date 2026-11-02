@props(['item', 'revealed' => false, 'locked' => false])

{{-- A native radio group, so screen readers announce it without custom ARIA (plan §4.1). --}}
<fieldset {{ $attributes->class('space-y-2') }} @disabled($locked)>
    <legend class="sr-only">{{ __('Choices for item :position', ['position' => $item->position]) }}</legend>

    @foreach ($item->snapshot['choices'] as $choice)
        @php($id = 'choice-'.$item->position.'-'.$choice['key'])
        @php($isCorrect = $revealed && $item->correct_key === $choice['key'])
        <div wire:key="{{ $id }}" @class([
            'flex items-start gap-3 rounded border px-3 py-2',
            'border-accent' => $item->selected_key === $choice['key'],
            'border-line' => $item->selected_key !== $choice['key'],
        ])>
            <input type="radio" id="{{ $id }}" name="choice-{{ $item->position }}" value="{{ $choice['key'] }}"
                   @checked($item->selected_key === $choice['key'])
                   x-on:change="$store.autosave.save({{ $item->position }}, '{{ $choice['key'] }}')" class="mt-1.5">
            <label for="{{ $id }}" class="min-w-0 flex-1 break-words">
                <span class="font-semibold">{{ $choice['key'] }}.</span>
                {{ $choice['body'] }}
                <x-attempt.media :items="$choice['media'] ?? []" />
                @if ($isCorrect)
                    <strong class="ml-1 text-sm">({{ __('Correct answer') }})</strong>
                @endif
            </label>
        </div>
    @endforeach
</fieldset>
