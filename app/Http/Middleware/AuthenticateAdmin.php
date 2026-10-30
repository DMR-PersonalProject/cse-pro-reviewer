<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;

/**
 * Filament's Authenticate, but a deactivated account is signed out and sent to the login
 * instead of getting a 403 (FR-I6). Active non-admins still get the 403 (AC-4).
 */
class AuthenticateAdmin extends Authenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        if ($user && ! $user->is_active) {
            Filament::auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->unauthenticated($request, $guards);
        }

        parent::authenticate($request, $guards);
    }
}
