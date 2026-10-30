<?php

use App\Livewire\Auth\Register;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/start', Register::class)->name('start');
});

Route::middleware('auth')->group(function () {
    Route::view('/', 'dashboard')->name('dashboard');
});
