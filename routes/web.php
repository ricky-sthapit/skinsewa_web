<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\ResourceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request, DashboardController $dashboard) {
    return Auth::check() ? $dashboard($request) : view('auth.login');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', fn () => redirect()->route('home'))->name('login');
    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:10,1')->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/resources', [ResourceController::class, 'index'])->name('resources.index');
    Route::get('/resources/{educationalResource}/view', [ResourceController::class, 'view'])->name('resources.view');
    Route::get('/resources/{educationalResource}/download', [ResourceController::class, 'download'])->name('resources.download');
});

Route::get('/dashboard', fn () => redirect()->route('home'))
    ->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'role:ADMIN'])->group(function (): void {
    Route::get('/resources/create', [ResourceController::class, 'create'])->name('resources.create');
    Route::post('/resources', [ResourceController::class, 'store'])->name('resources.store');
    Route::get('/resources/{educationalResource}/edit', [ResourceController::class, 'edit'])->name('resources.edit');
    Route::put('/resources/{educationalResource}', [ResourceController::class, 'update'])->name('resources.update');
    Route::delete('/resources/{educationalResource}', [ResourceController::class, 'destroy'])->name('resources.destroy');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});
