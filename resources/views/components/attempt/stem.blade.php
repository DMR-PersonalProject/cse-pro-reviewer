@props(['snapshot', 'label'])

{{-- Focus target after navigation (keyboard store); named with the item number so a screen reader announces it. --}}
<div id="stem" tabindex="-1" role="group" aria-label="{{ $label }}" {{ $attributes->class('focus:outline-none') }}>
    <x-markdown :text="$snapshot['stem']" />
    <x-attempt.media :items="$snapshot['media'] ?? []" />
</div>
