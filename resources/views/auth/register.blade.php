@extends('layouts.app')

@section('title', 'Complete registration · SkinSewa')

@section('content')
<main class="page-shell login-shell registration-shell">
    <section class="login-card registration-card" aria-labelledby="registration-title">
        <img class="login-logo" src="{{ asset('images/skinsewa-logo.png') }}" alt="SkinSewa">
        <p class="eyebrow">YOUR SKIN. OUR CARE.</p>
        <h1 id="registration-title">Complete registration</h1>
        <p class="muted">Your invitation sets your email and user type. Enter the code from the invitation email to verify and create your account.</p>
        @if ($errors->any()) <div class="alert" role="alert">{{ $errors->first() }}</div> @endif
        <form method="post" action="{{ request()->fullUrl() }}" class="form-stack registration-form">
            @csrf
            <label for="registration-email">Invited email</label>
            <input id="registration-email" type="email" value="{{ $registration->email }}" readonly aria-readonly="true">

            <label for="registration-role">User type</label>
            <input id="registration-role" type="text" value="{{ $registration->user_type_label }}" readonly aria-readonly="true">

            <label for="otp">Email verification code</label>
            <input id="otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" value="{{ old('otp') }}" required>

            <div class="registration-name-fields">
                <div><label for="first-name">First name</label><input id="first-name" name="first_name" type="text" value="{{ old('first_name') }}" autocomplete="given-name" maxlength="100" required></div>
                <div><label for="last-name">Last name</label><input id="last-name" name="last_name" type="text" value="{{ old('last_name') }}" autocomplete="family-name" maxlength="100" required></div>
            </div>

            <label for="phone">Phone <span class="optional-label">Optional</span></label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="32">
            <label for="facility-name">Facility <span class="optional-label">Optional</span></label>
            <input id="facility-name" name="facility_name" type="text" value="{{ old('facility_name', $registration->facility_name) }}" maxlength="150">
            @if ($registration->role === \App\Models\User::ROLE_DERMATOLOGIST)
                <label for="specialty">Specialty <span class="optional-label">Optional</span></label>
                <input id="specialty" name="specialty" value="{{ old('specialty') }}" maxlength="120">
            @endif

            <label for="password">Create password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            <small class="password-hint">Use at least 10 characters, including letters and numbers.</small>
            <label for="password-confirmation">Confirm password</label>
            <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <button class="primary-button" type="submit">Verify code and create account</button>
        </form>
        <p class="login-footnote">Invitation expires {{ $registration->otp_expires_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}.</p>
    </section>
</main>
@endsection
