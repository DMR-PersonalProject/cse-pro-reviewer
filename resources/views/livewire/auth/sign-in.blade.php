<div class="mx-auto max-w-md space-y-8">
    <h1 class="text-2xl font-semibold">{{ __('Sign in') }}</h1>

    @if (session('status'))
        <p role="status" class="rounded border border-line bg-surface px-3 py-2 text-sm">{{ session('status') }}</p>
    @endif

    <form wire:submit="signIn" class="space-y-5" novalidate>
        <div>
            <label for="username" class="block text-sm font-medium">{{ __('Username') }}</label>
            <input id="username" type="text" wire:model="username" autocomplete="username" autocapitalize="none"
                   spellcheck="false" required maxlength="20" autofocus
                   @error('username') aria-invalid="true" aria-describedby="username-error" @enderror
                   class="mt-1 block w-full rounded border border-line bg-canvas px-3 py-2">
            @error('username') <p id="username-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="pin" class="block text-sm font-medium">{{ __('PIN (if you set one)') }}</label>
            <input id="pin" type="password" wire:model="pin" inputmode="numeric" autocomplete="current-password"
                   maxlength="6" class="mt-1 block w-full rounded border border-line bg-canvas px-3 py-2">
        </div>

        <button type="submit" class="w-full rounded bg-accent px-4 py-2 font-medium text-on-accent disabled:opacity-60" wire:loading.attr="disabled">
            {{ __('Sign in') }}
        </button>
    </form>

    <p class="text-sm">
        {{ __('New here?') }} <a href="{{ route('start') }}" class="underline">{{ __('Get started') }}</a>
    </p>
</div>
