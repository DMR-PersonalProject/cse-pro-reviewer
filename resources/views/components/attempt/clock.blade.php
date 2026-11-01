@props(['remainingMs', 'elapsedMs', 'text'])

{{-- Server-rendered first value; the clock store keeps it running. Ignored by morphing, so a
     response never overwrites the live value; responses resync through the clock-sync event. --}}
<div wire:ignore {{ $attributes->class('text-sm') }}
     x-data
     x-init="$store.clock.start($wire, @js($remainingMs), @js($elapsedMs), @js(['ten' => __('10 minutes left'), 'one' => __('1 minute left')]))">
    <span class="text-muted">{{ $remainingMs === null ? __('Elapsed') : __('Time left') }}</span>
    <span role="timer" class="font-semibold tabular-nums" x-text="$store.clock.display"
          x-bind:class="{ 'text-danger': $store.clock.warning }"
          @if ($remainingMs === null) data-elapsed-ms="{{ $elapsedMs }}" @else data-remaining-ms="{{ $remainingMs }}" @endif>{{ $text }}</span>
    <p id="clock-announcer" class="sr-only" aria-live="polite" x-text="$store.clock.announcement"></p>
</div>
