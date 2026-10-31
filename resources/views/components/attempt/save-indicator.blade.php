{{-- Follows the autosave queue (FR-T4): "Saving…" while answers are pending, "Offline" while they
     can't be sent, "Saved" when the queue is empty. Ignored by morphing so a response doesn't reset it. --}}
<div wire:ignore {{ $attributes }}>
    <p role="status" class="text-sm text-muted">
        <span x-show="$store.autosave.state === 'saved'">{{ __('Saved') }}</span>
        <span x-show="$store.autosave.state === 'saving'" x-cloak>{{ __('Saving…') }}</span>
        <span x-show="$store.autosave.state === 'offline'" x-cloak class="text-danger">{{ __('Offline') }}</span>
    </p>
    <p x-show="$store.autosave.state === 'offline'" x-cloak role="alert"
       class="fixed inset-x-0 top-0 z-50 border-b border-danger bg-canvas px-4 py-2 text-center text-sm text-danger">
        {{ __('Offline — answers will sync') }}
    </p>
</div>
