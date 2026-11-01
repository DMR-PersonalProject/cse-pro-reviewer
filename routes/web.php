<?php

use App\Livewire\Attempt\Runner;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\SignIn;
use App\Livewire\Dashboard;
use App\Livewire\Profile\Edit as ProfileEdit;
use App\Livewire\Results\Show as ResultsShow;
use App\Livewire\Tests\ConfigureIndividual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/privacy', 'privacy')->name('privacy');

Route::middleware('guest')->group(function () {
    Route::get('/start', Register::class)->name('start');
    Route::get('/login', SignIn::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/profile', ProfileEdit::class)->name('profile');
    Route::get('/practice', ConfigureIndividual::class)->name('practice');
    Route::get('/attempts/{attempt}', Runner::class)->name('attempts.show');
    Route::get('/attempts/{attempt}/results', ResultsShow::class)->name('results.show');

    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('start');
    })->name('logout');
});
