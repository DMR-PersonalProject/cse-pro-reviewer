{{-- Leftover autosave stash (plan §4.1): sent once for the count, then cleared, so a reload shows nothing. --}}
<div class="mx-auto max-w-3xl space-y-6"
     x-data
     x-init="$store.autosave.reportUnsaved($wire, {{ $this->attempt->id }})">
    <h1 class="text-lg font-semibold">{{ __('Result') }}</h1>

    @if ($unsaved)
        <p role="alert" class="rounded border border-danger px-3 py-2 text-sm text-danger">
            {{ trans_choice(':count answer was not saved because time ran out.|:count answers were not saved because time ran out.', $unsaved) }}
        </p>
    @endif
</div>
