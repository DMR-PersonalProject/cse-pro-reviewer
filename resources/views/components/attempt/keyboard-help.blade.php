{{-- Lists the runner shortcuts (FR-T2). Opened with "?" or the "Keyboard shortcuts" button. --}}
<dialog id="keyboard-help" aria-labelledby="keyboard-help-title"
        class="m-auto max-w-sm rounded border border-line bg-canvas p-6 text-ink backdrop:bg-black/50">
    <h2 id="keyboard-help-title" class="mb-3 text-lg font-semibold">{{ __('Keyboard shortcuts') }}</h2>
    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
        <dt><kbd>1</kbd>–<kbd>5</kbd> {{ __('or') }} <kbd>A</kbd>–<kbd>E</kbd></dt>
        <dd>{{ __('Choose an answer') }}</dd>
        <dt><kbd>←</kbd> <kbd>→</kbd></dt>
        <dd>{{ __('Previous or next item') }}</dd>
        <dt><kbd>F</kbd></dt>
        <dd>{{ __('Flag or unflag the item') }}</dd>
        <dt><kbd>N</kbd></dt>
        <dd>{{ __('Go to the item navigator') }}</dd>
        <dt><kbd>?</kbd></dt>
        <dd>{{ __('Show these shortcuts') }}</dd>
    </dl>
    <p class="mt-3 text-sm text-muted">{{ __('Shortcuts are off while you type in a text field.') }}</p>
    <form method="dialog" class="mt-4">
        <button type="submit" class="rounded bg-accent px-4 py-2 font-medium text-on-accent">{{ __('Close') }}</button>
    </form>
</dialog>
