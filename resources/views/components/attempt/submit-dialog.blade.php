@props(['summary'])

{{-- Submit confirmation (M5-05). Escape or Cancel closes it on the server, so the attempt stays in progress. --}}
<dialog x-data x-init="$el.showModal()" x-on:cancel.prevent="$wire.cancelSubmit()" aria-labelledby="submit-title"
        class="m-auto w-full max-w-md rounded border border-line bg-surface p-6 text-ink backdrop:bg-black/50">
    <h2 id="submit-title" class="text-lg font-semibold">{{ __('Submit the test?') }}</h2>

    <ul class="mt-3 space-y-1 text-sm">
        <li>{{ __(':count unanswered', ['count' => $summary['unanswered']]) }}</li>
        <li>{{ __(':count flagged', ['count' => $summary['flagged']]) }}</li>
    </ul>

    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        @if ($summary['firstUnanswered'])
            <button type="button" x-on:click="$store.keyboard.go('goTo', {{ $summary['firstUnanswered'] }})"
                    class="rounded border border-line px-3 py-2">{{ __('Go to first unanswered') }}</button>
        @endif
        @if ($summary['firstFlagged'])
            <button type="button" x-on:click="$store.keyboard.go('goTo', {{ $summary['firstFlagged'] }})"
                    class="rounded border border-line px-3 py-2">{{ __('Go to first flagged') }}</button>
        @endif
    </div>

    <div class="mt-6 flex justify-end gap-2">
        <button type="button" wire:click="cancelSubmit" autofocus class="rounded border border-line px-4 py-2">{{ __('Cancel') }}</button>
        <button type="button" wire:click="submit" class="rounded bg-accent px-4 py-2 font-medium text-on-accent">{{ __('Submit') }}</button>
    </div>
</dialog>
