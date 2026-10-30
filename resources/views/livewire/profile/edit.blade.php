<div class="mx-auto max-w-md space-y-8">
    <h1 class="text-2xl font-semibold">{{ __('Profile') }}</h1>

    @if ($status)
        <p role="status" class="rounded border border-line bg-surface px-3 py-2 text-sm">{{ $status }}</p>
    @endif

    @unless ($this->user->hasPin())
        <div role="note" class="rounded border border-line bg-surface px-3 py-2 text-sm">
            <p class="font-medium">{{ __('You have no PIN yet.') }}</p>
            <p>{{ __('Anyone who knows your username can sign in as you, and you won’t appear on the leaderboard until you set one.') }}</p>
        </div>
    @endunless

    <dl class="space-y-3 text-sm">
        <div>
            <dt class="font-medium">{{ __('Username') }}</dt>
            <dd>{{ $this->user->username }}</dd>
        </div>
        <div>
            <dt class="font-medium">{{ __('IGN') }}</dt>
            <dd>{{ $this->user->ign }} <span class="text-xs text-muted">({{ __('only an admin can change it') }})</span></dd>
        </div>
        <div>
            <dt class="font-medium">{{ __('Full name (never shown publicly)') }}</dt>
            <dd>{{ $this->user->full_name }}</dd>
        </div>
    </dl>

    <section class="space-y-2">
        <h2 class="text-lg font-semibold">{{ __('Appearance') }}</h2>
        <p class="text-sm text-muted">{{ __('Applies here and in the admin panel on this device.') }}</p>
        <div wire:ignore><x-ui.theme-toggle /></div>
    </section>

    <form wire:submit="savePin" class="space-y-4" novalidate>
        <h2 class="text-lg font-semibold">{{ $this->user->hasPin() ? __('Change your PIN') : __('Set a PIN') }}</h2>
        @if ($this->user->hasPin())
            <div>
                <label for="change_current_pin" class="block text-sm font-medium">{{ __('Current PIN') }}</label>
                <input id="change_current_pin" type="password" wire:model="change_current_pin" inputmode="numeric"
                       autocomplete="current-password" maxlength="6"
                       @error('change_current_pin') aria-invalid="true" aria-describedby="change_current_pin-error" @enderror
                       class="mt-1 block w-full rounded border border-line bg-canvas px-3 py-2">
                @error('change_current_pin') <p id="change_current_pin-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </div>
        @endif
        <div>
            <label for="pin" class="block text-sm font-medium">{{ __('New PIN (4–6 digits)') }}</label>
            <input id="pin" type="password" wire:model="pin" inputmode="numeric" autocomplete="new-password" maxlength="6"
                   @error('pin') aria-invalid="true" aria-describedby="pin-error" @enderror
                   class="mt-1 block w-full rounded border border-line bg-canvas px-3 py-2">
            @error('pin') <p id="pin-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="pin_confirmation" class="block text-sm font-medium">{{ __('Confirm new PIN') }}</label>
            <input id="pin_confirmation" type="password" wire:model="pin_confirmation" inputmode="numeric"
                   autocomplete="new-password" maxlength="6" class="mt-1 block w-full rounded border border-line bg-canvas px-3 py-2">
        </div>
        <button type="submit" class="rounded bg-accent px-4 py-2 font-medium text-on-accent">{{ __('Save PIN') }}</button>
    </form>

    @if ($this->user->hasPin())
        <form wire:submit="removePin" class="space-y-4" novalidate>
            <h2 class="text-lg font-semibold">{{ __('Remove your PIN') }}</h2>
            <div>
                <label for="current_pin" class="block text-sm font-medium">{{ __('Current PIN') }}</label>
                <input id="current_pin" type="password" wire:model="current_pin" inputmode="numeric"
                       autocomplete="current-password" maxlength="6"
                       @error('current_pin') aria-invalid="true" aria-describedby="current_pin-error" @enderror
                       class="mt-1 block w-full rounded border border-line bg-canvas px-3 py-2">
                @error('current_pin') <p id="current_pin-error" class="mt-1 text-sm text-danger" role="alert">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="rounded bg-accent px-4 py-2 font-medium text-on-accent">{{ __('Remove PIN') }}</button>
        </form>
    @endif
</div>
