@props(['passage'])

{{-- Scrolls on its own beside the item at large widths; stacked above it on small screens. --}}
<section role="region" aria-label="{{ __('Passage') }}" tabindex="0"
         {{ $attributes->class('rounded border border-line bg-surface p-4 lg:max-h-[75vh] lg:overflow-y-auto') }}>
    <x-markdown :text="$passage['body']" class="max-w-[70ch]" />
    <x-attempt.media :items="$passage['media'] ?? []" />
</section>
