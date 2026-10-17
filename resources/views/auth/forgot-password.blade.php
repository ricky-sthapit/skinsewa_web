@extends('layouts.app')

@section('title', 'Forgot password · SkinSewa')

@section('content')
<main class="page-shell login-shell">
    <section class="login-card" aria-labelledby="forgot-password-title">
        <img class="login-logo" src="{{ asset('images/skinsewa-logo.png') }}" alt="SkinSewa">
        <p class="eyebrow">YOUR SKIN. OUR CARE.</p>
        <h1 id="forgot-password-title">Reset your password</h1>
        <p class="muted">Enter the email address on your account. If it matches an account, we’ll send a reset link.</p>

        @if (session('status')) <div class="success-alert" role="status">{{ session('status') }}</div> @endif
        @if ($errors->any()) <div class="alert" role="alert">{{ $errors->first() }}</div> @endif
        @if (config('mail.default') === 'log') <p class="mail-delivery-note">Local mail is set to log. Reset emails are written to <code>storage/logs/laravel.log</code>.</p> @endif

        <form method="post" action="{{ route('password.email') }}" class="form-stack">
            @csrf
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required autofocus>
            <button class="primary-button" type="submit">Send reset link</button>
        </form>
        <p class="login-footnote"><a class="back-link" href="{{ route('home') }}">Back to sign in</a></p>
    </section>
</main>
@endsection
