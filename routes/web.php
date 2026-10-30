<?php

use App\Livewire\Auth\Register;
use App\Livewire\Auth\SignIn;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/start', Register::class)->name('start');
    Route::get('/login', SignIn::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::view('/', 'dashboard')->name('dashboard');
});
