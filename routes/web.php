<?php

use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? view('dashboard') : view('auth.login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', fn () => redirect()->route('home'))->name('login');
    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:10,1')->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')->name('logout');

Route::get('/dashboard', fn () => redirect()->route('home'))
    ->middleware('auth')->name('dashboard');
