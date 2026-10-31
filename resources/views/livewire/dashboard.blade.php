<div class="mx-auto max-w-3xl space-y-8">
    <h1 class="text-2xl font-semibold">{{ __('Welcome, :ign', ['ign' => $this->user->ign]) }}</h1>

    @if ($this->needsPin)
        <div role="note" class="space-y-1 rounded border border-line bg-surface px-4 py-3 text-sm">
            <p class="font-medium">{{ __('Set a PIN to protect your username') }}</p>
            <p class="text-muted">{{ __('Without a PIN, anyone who knows your username can sign in as you, and you won’t appear on the leaderboard.') }}</p>
            <a href="{{ route('profile') }}" class="inline-block font-medium underline">{{ __('Set a PIN') }}</a>
        </div>
    @endif

    <a href="{{ route('practice') }}" class="inline-block rounded bg-accent px-4 py-2 font-medium text-on-accent">{{ __('Practice a subtest') }}</a>
</div>
