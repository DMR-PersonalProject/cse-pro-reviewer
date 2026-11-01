<div class="mx-auto max-w-3xl space-y-8">
    <h1 class="text-2xl font-semibold">{{ __('Welcome, :ign', ['ign' => $this->user->ign]) }}</h1>

    @if ($this->needsPin)
        <div role="note" class="space-y-1 rounded border border-line bg-surface px-4 py-3 text-sm">
            <p class="font-medium">{{ __('Set a PIN to protect your username') }}</p>
            <p class="text-muted">{{ __('Without a PIN, anyone who knows your username can sign in as you, and you won’t appear on the leaderboard.') }}</p>
            <a href="{{ route('profile') }}" class="inline-block font-medium underline">{{ __('Set a PIN') }}</a>
        </div>
    @endif

    @if ($active = $this->active)
        @php($timeLeft = $this->timeLeft($active))
        <section aria-labelledby="resume-title" class="space-y-3 rounded border border-line bg-surface px-4 py-3">
            <h2 id="resume-title" class="font-medium">{{ __('Test in progress') }}</h2>
            <p class="text-sm">
                {{ $active->subtest?->name ?? $active->mode->label() }}
                <span class="text-muted">·</span>
                {{ $active->timing->label() }}
                @if ($timeLeft !== null)
                    <span class="text-muted">·</span>
                    {{ __('Time left') }} <span class="font-semibold tabular-nums">{{ $timeLeft }}</span>
                @endif
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('attempts.show', $active) }}" class="rounded bg-accent px-4 py-2 font-medium text-on-accent">{{ __('Resume') }}</a>
                <button type="button" wire:click="abandon" wire:confirm="{{ __('Abandon the test in progress?') }}"
                        class="rounded border border-line px-4 py-2 font-medium">{{ __('Abandon') }}</button>
            </div>
        </section>
    @endif

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('practice') }}" class="inline-block rounded bg-accent px-4 py-2 font-medium text-on-accent">{{ __('Practice a subtest') }}</a>
        <a href="{{ route('mock') }}" class="inline-block rounded border border-line px-4 py-2 font-medium">{{ __('Take a mock exam') }}</a>
    </div>
</div>
