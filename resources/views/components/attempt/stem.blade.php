@props(['snapshot'])

{{-- Focus target after navigation (keyboard store). --}}
<div id="stem" tabindex="-1" {{ $attributes->class('focus:outline-none') }}>
    <x-markdown :text="$snapshot['stem']" />
    <x-attempt.media :items="$snapshot['media'] ?? []" />
</div>
