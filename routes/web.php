<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CaseConversationController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\Admin\InvitationController;
use App\Http\Controllers\Web\InvitationRegistrationController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\PasswordResetController;
use App\Http\Controllers\Web\PatientCaseController;
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
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1')->name('password.update');
    Route::get('/register/{registration}', [InvitationRegistrationController::class, 'show'])
        ->middleware('signed')->name('registration.show');
    Route::post('/register/{registration}', [InvitationRegistrationController::class, 'store'])
        ->middleware(['signed', 'throttle:5,1'])->name('registration.store');
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

Route::get('/cases/create', [PatientCaseController::class, 'create'])
    ->middleware(['auth', 'role:MEDICAL_OFFICER'])->name('cases.create');

Route::middleware(['auth', 'role:ADMIN,MEDICAL_OFFICER,DERMATOLOGIST'])->group(function (): void {
    Route::get('/cases', [PatientCaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/{patientCase}/photos/{casePhoto}', [PatientCaseController::class, 'photo'])->name('cases.photos.show');
    Route::get('/cases/{patientCase}/messages', [CaseConversationController::class, 'index'])->name('cases.messages.index');
    Route::post('/cases/{patientCase}/messages', [CaseConversationController::class, 'store'])->middleware('throttle:30,1')->name('cases.messages.store');
    Route::post('/cases/{patientCase}/treatments', [CaseConversationController::class, 'prescribe'])->middleware('role:DERMATOLOGIST')->name('cases.treatments.store');
    Route::get('/cases/{patientCase}', [PatientCaseController::class, 'show'])->name('cases.show');
});

Route::middleware(['auth', 'role:MEDICAL_OFFICER'])->group(function (): void {
    Route::post('/cases', [PatientCaseController::class, 'store'])->middleware('throttle:10,1')->name('cases.store');
    Route::get('/patients/lookup', [PatientCaseController::class, 'lookupPatient'])->middleware('throttle:30,1')->name('patients.lookup');
    Route::get('/places/{place}/children', [PatientCaseController::class, 'children'])->name('places.children');
});

Route::get('/dashboard', fn () => redirect()->route('home'))
    ->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'role:ADMIN'])->group(function (): void {
    Route::get('/invitations', [InvitationController::class, 'index'])->name('invitations.index');
    Route::get('/invitations/create', [InvitationController::class, 'create'])->name('invitations.create');
    Route::post('/invitations', [InvitationController::class, 'store'])
        ->middleware('throttle:10,1')->name('invitations.store');
    Route::post('/invitations/{registration}/resend', [InvitationController::class, 'resend'])
        ->middleware('throttle:10,1')->name('invitations.resend');

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
