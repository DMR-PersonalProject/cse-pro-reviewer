<?php

namespace App\Livewire\Auth;

use App\Domain\Identity\SignInWithUsername;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class SignIn extends Component
{
    public string $username = '';

    public string $pin = '';

    public function signIn(SignInWithUsername $signInWithUsername): void
    {
        $this->validate(['username' => ['required', 'string', 'max:20'], 'pin' => ['nullable', 'string', 'max:6']]);

        try {
            $user = $signInWithUsername->handle($this->username, $this->pin, (string) request()->ip());
        } finally {
            $this->reset('pin');
        }

        Auth::login($user);
        session()->regenerate();

        $this->redirectIntended(route('dashboard'));
    }

    public function render(): View
    {
        return view('livewire.auth.sign-in')->title(__('Sign in'));
    }
}
