<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Stub for M1. Resume card, start buttons and recent results arrive in M4.
 */
class Dashboard extends Component
{
    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function needsPin(): bool
    {
        return $this->user->hasRole(Role::User) && ! $this->user->hasPin();
    }

    public function render(): View
    {
        return view('livewire.dashboard')->title(__('Dashboard'));
    }
}
