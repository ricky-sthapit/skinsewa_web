@extends('layouts.app')

@section('title', 'Sign in · SkinSewa')

@section('content')
<main class="page-shell login-shell">
    <section class="login-card" aria-labelledby="login-title">
        <img class="login-logo" src="{{ asset('images/skinsewa-logo.png') }}" alt="SkinSewa">
        <p class="eyebrow">YOUR SKIN. OUR CARE.</p>
        <h1 id="login-title">Welcome back</h1>
        <p class="muted">Sign in to continue to your SkinSewa workspace.</p>

        @if (session('status')) <div class="success-alert" role="status">{{ session('status') }}</div> @endif
        @if ($errors->any())
            <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('login.store') }}" class="form-stack">
            @csrf
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <a class="forgot-password-link" href="{{ route('password.request') }}">Forgot password?</a>

            <label class="remember-row" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1">
                <span>Keep me signed in</span>
            </label>

            <button class="primary-button" type="submit">Sign in</button>
        </form>
        <p class="login-footnote">Access is provided by your SkinSewa administrator.</p>
    </section>
</main>
@endsection
