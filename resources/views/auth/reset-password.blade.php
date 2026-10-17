@extends('layouts.app')

@section('title', 'Choose a new password · SkinSewa')

@section('content')
<main class="page-shell login-shell">
    <section class="login-card" aria-labelledby="reset-password-title">
        <img class="login-logo" src="{{ asset('images/skinsewa-logo.png') }}" alt="SkinSewa">
        <p class="eyebrow">YOUR SKIN. OUR CARE.</p>
        <h1 id="reset-password-title">Choose a new password</h1>
        <p class="muted">Set a new password for your SkinSewa account.</p>

        @if ($errors->any()) <div class="alert" role="alert">{{ $errors->first() }}</div> @endif

        <form method="post" action="{{ route('password.update') }}" class="form-stack">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ $email }}" autocomplete="email" readonly required>
            <label for="password">New password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            <p class="password-hint">Use at least 10 characters, including letters and numbers.</p>
            <label for="password-confirmation">Confirm new password</label>
            <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <button class="primary-button" type="submit">Reset password</button>
        </form>
        <p class="login-footnote"><a class="back-link" href="{{ route('home') }}">Back to sign in</a></p>
    </section>
</main>
@endsection
