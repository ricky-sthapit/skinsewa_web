<?php

use App\Http\Controllers\Api\V1\Admin\EducationalResourceController as AdminEducationalResourceController;
use App\Http\Controllers\Api\V1\Admin\RegistrationRequestController;
use App\Http\Controllers\Api\V1\Admin\WorkspaceController as AdminWorkspaceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CaseConversationController;
use App\Http\Controllers\Api\V1\EducationalResourceController;
use App\Http\Controllers\Api\V1\PatientCaseController;
use App\Http\Controllers\Api\V1\PlaceController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['app' => config('app.name'), 'status' => 'ok']));

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1')->name('forgot-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('reset-password');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::get('/places/provinces', [PlaceController::class, 'provinces'])->name('places.provinces');
        Route::get('/places/{place}/children', [PlaceController::class, 'children'])->name('places.children');

        Route::get('/resources', [EducationalResourceController::class, 'index'])->name('resources.index');
        Route::get('/resources/{educationalResource}/view', [EducationalResourceController::class, 'view'])->name('resources.view');
        Route::get('/resources/{educationalResource}/download', [EducationalResourceController::class, 'download'])->name('resources.download');
        Route::get('/resources/{educationalResource}', [EducationalResourceController::class, 'show'])->name('resources.show');

        Route::middleware('role:ADMIN,MEDICAL_OFFICER,DERMATOLOGIST')->group(function (): void {
            Route::get('/cases', [PatientCaseController::class, 'index'])->name('cases.index');
            Route::post('/cases/{patientCase}/messages', [CaseConversationController::class, 'store'])
                ->middleware('throttle:30,1')->name('cases.messages.store');
            Route::get('/cases/{patientCase}/messages', [CaseConversationController::class, 'index'])->name('cases.messages.index');
            Route::get('/cases/{patientCase}/photos/{casePhoto}', [PatientCaseController::class, 'photo'])->name('cases.photos.show');
            Route::get('/cases/{patientCase}', [PatientCaseController::class, 'show'])->name('cases.show');
        });

        Route::middleware('role:MEDICAL_OFFICER')->group(function (): void {
            Route::get('/case-dermatologists', [PatientCaseController::class, 'dermatologists'])->name('cases.dermatologists');
            Route::get('/patients/lookup', [PatientCaseController::class, 'lookupPatient'])->name('patients.lookup');
            Route::post('/cases', [PatientCaseController::class, 'store'])->middleware('throttle:10,1')->name('cases.store');
        });

        Route::post('/cases/{patientCase}/treatments', [CaseConversationController::class, 'prescribe'])
            ->middleware('role:DERMATOLOGIST')->name('cases.treatments.store');
    });

    Route::middleware(['auth:sanctum', 'role:ADMIN'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::post('/registration-requests', [RegistrationRequestController::class, 'store'])
            ->middleware('throttle:10,1')->name('registration-requests.store');
        Route::get('/dashboard', [AdminWorkspaceController::class, 'dashboard'])->name('dashboard');
        Route::get('/users', [AdminWorkspaceController::class, 'users'])->name('users.index');
        Route::get('/users/{user}', [AdminWorkspaceController::class, 'showUser'])->name('users.show');
        Route::patch('/users/{user}', [AdminWorkspaceController::class, 'updateUser'])->name('users.update');
        Route::delete('/users/{user}', [AdminWorkspaceController::class, 'deleteUser'])->name('users.destroy');
        Route::get('/invitations', [AdminWorkspaceController::class, 'invitations'])->name('invitations.index');
        Route::post('/invitations', [AdminWorkspaceController::class, 'invite'])->middleware('throttle:10,1')->name('invitations.store');
        Route::post('/invitations/{registration}/resend', [AdminWorkspaceController::class, 'resendInvitation'])
            ->middleware('throttle:10,1')->name('invitations.resend');
        Route::post('/resources', [AdminEducationalResourceController::class, 'store'])->name('resources.store');
        Route::post('/resources/{educationalResource}', [AdminEducationalResourceController::class, 'update'])->name('resources.update');
        Route::delete('/resources/{educationalResource}', [AdminEducationalResourceController::class, 'destroy'])->name('resources.destroy');
    });
});
