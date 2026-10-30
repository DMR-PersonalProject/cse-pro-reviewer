<?php

namespace App\Livewire\Auth;

use App\Domain\Identity\RegisterUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Register extends Component
{
    public string $username = '';

    public string $ign = '';

    public string $full_name = '';

    public string $pin = '';

    public string $pin_confirmation = '';

    public function register(RegisterUser $registerUser): void
    {
        $user = $registerUser->handle($this->only(['username', 'ign', 'full_name', 'pin', 'pin_confirmation']));

        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        return view('livewire.auth.register')->title(__('Get started'));
    }
}
