{{-- "Saving…" while a request is in flight, "Saved" otherwise (FR-T4). The offline state comes with the autosave queue. --}}
<p role="status" {{ $attributes->class('text-sm text-muted') }}>
    <span wire:loading.delay wire:target="select">{{ __('Saving…') }}</span>
    <span wire:loading.remove wire:target="select">{{ __('Saved') }}</span>
</p>
