<div class="mx-auto max-w-md space-y-8">
    <div>
        <h1 class="text-2xl font-semibold">{{ __('Get started') }}</h1>
        <p class="mt-1 text-sm">{{ __('Pick a username and an IGN. You can add a PIN now or later.') }}</p>
    </div>

    <form wire:submit="register" class="space-y-5" novalidate>
        <div>
            <label for="username" class="block text-sm font-medium">{{ __('Username') }}</label>
            <input id="username" type="text" wire:model="username" autocomplete="username" autocapitalize="none"
                   spellcheck="false" required maxlength="20" aria-describedby="username-help @error('username') username-error @enderror"
                   @error('username') aria-invalid="true" @enderror class="mt-1 block w-full rounded border px-3 py-2">
            <p id="username-help" class="mt-1 text-xs">{{ __('3–20 letters, digits or underscores. You sign in with this.') }}</p>
            @error('username') <p id="username-error" class="mt-1 text-sm" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="ign" class="block text-sm font-medium">{{ __('IGN (shown on the leaderboard)') }}</label>
            <input id="ign" type="text" wire:model="ign" required maxlength="20"
                   aria-describedby="ign-help @error('ign') ign-error @enderror"
                   @error('ign') aria-invalid="true" @enderror class="mt-1 block w-full rounded border px-3 py-2">
            <p id="ign-help" class="mt-1 text-xs">{{ __('3–20 letters, digits, spaces, underscores or dashes.') }}</p>
            @error('ign') <p id="ign-error" class="mt-1 text-sm" role="alert">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="full_name" class="block text-sm font-medium">{{ __('Full name (never shown publicly)') }}</label>
            <input id="full_name" type="text" wire:model="full_name" autocomplete="name" required maxlength="100"
                   @error('full_name') aria-invalid="true" aria-describedby="full_name-error" @enderror
                   class="mt-1 block w-full rounded border px-3 py-2">
            @error('full_name') <p id="full_name-error" class="mt-1 text-sm" role="alert">{{ $message }}</p> @enderror
        </div>

        <fieldset class="space-y-3">
            <legend class="text-sm font-medium">{{ __('PIN (optional, 4–6 digits)') }}</legend>
            <p class="text-xs">{{ __('A PIN stops anyone else from using your username, and you need one to appear on the leaderboard.') }}</p>
            <div>
                <label for="pin" class="block text-sm">{{ __('PIN') }}</label>
                <input id="pin" type="password" wire:model="pin" inputmode="numeric" autocomplete="new-password"
                       maxlength="6" @error('pin') aria-invalid="true" aria-describedby="pin-error" @enderror
                       class="mt-1 block w-full rounded border px-3 py-2">
                @error('pin') <p id="pin-error" class="mt-1 text-sm" role="alert">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="pin_confirmation" class="block text-sm">{{ __('Confirm PIN') }}</label>
                <input id="pin_confirmation" type="password" wire:model="pin_confirmation" inputmode="numeric"
                       autocomplete="new-password" maxlength="6" class="mt-1 block w-full rounded border px-3 py-2">
            </div>
        </fieldset>

        <button type="submit" class="w-full rounded px-4 py-2 font-medium" wire:loading.attr="disabled">
            {{ __('Start practicing') }}
        </button>
    </form>

    <p class="text-sm">
        {{ __('Already registered?') }} <a href="{{ url('/login') }}" class="underline">{{ __('Sign in') }}</a>
    </p>

    @include('partials.privacy-summary')
</div>
