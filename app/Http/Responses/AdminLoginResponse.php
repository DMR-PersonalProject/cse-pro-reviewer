<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Admin sign-in lands in the panel. An intended URL left by the user app (e.g. "/") is dropped;
 * one inside the panel is kept.
 */
class AdminLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $panelUrl = Filament::getUrl();
        $intended = session()->pull('url.intended');

        return redirect()->to(
            is_string($intended) && ($intended === $panelUrl || Str::startsWith($intended, $panelUrl.'/')) ? $intended : $panelUrl
        );
    }
}
