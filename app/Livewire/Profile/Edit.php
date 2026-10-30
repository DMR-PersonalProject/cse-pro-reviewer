<?php

namespace App\Livewire\Profile;

use App\Domain\Identity\RemovePin;
use App\Domain\Identity\SetPin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Username, IGN and full name are shown read-only; only admins change an IGN (FR-I1).
 */
class Edit extends Component
{
    public string $pin = '';

    public string $pin_confirmation = '';

    public string $current_pin = '';

    public ?string $status = null;

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    public function savePin(SetPin $setPin): void
    {
        try {
            $setPin->handle($this->user, $this->pin, $this->pin_confirmation);
        } finally {
            $this->reset('pin', 'pin_confirmation');
        }

        $this->status = __('PIN saved.');
    }

    public function removePin(RemovePin $removePin): void
    {
        try {
            $removePin->handle($this->user, $this->current_pin);
        } finally {
            $this->reset('current_pin');
        }

        $this->status = __('PIN removed.');
    }

    public function render(): View
    {
        return view('livewire.profile.edit')->title(__('Profile'));
    }
}
