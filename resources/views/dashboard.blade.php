@extends('layouts.app')

@section('title', 'Dashboard · SkinSewa')

@section('content')
@php($user = auth()->user())
<main class="page-shell dashboard-shell">
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}" aria-label="SkinSewa home">
            <img src="{{ asset('images/skinsewa-logo.png') }}" alt="">
            <span>SkinSewa</span>
        </a>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="quiet-button" type="submit">Sign out</button>
        </form>
    </header>

    <section class="welcome-panel">
        <p class="eyebrow">YOUR SKIN. OUR CARE.</p>
        <h1>Welcome, {{ $user->first_name }}</h1>
        <p class="muted">You’re signed in to the SkinSewa care workspace.</p>
    </section>

    <section class="profile-card" aria-labelledby="profile-title">
        <div class="profile-heading">
            <div class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1)) }}</div>
            <div>
                <h2 id="profile-title">{{ $user->full_name }}</h2>
                <span class="role-badge">{{ str($user->role())->replace('_', ' ')->title() }}</span>
            </div>
        </div>
        <dl class="profile-details">
            <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
            <div><dt>Phone</dt><dd>{{ $user->phone ?: 'Not provided' }}</dd></div>
            <div><dt>Facility</dt><dd>{{ $user->facility_name ?: 'Not assigned' }}</dd></div>
            <div><dt>Specialty</dt><dd>{{ $user->specialty ?: 'Not provided' }}</dd></div>
            <div><dt>Account level</dt><dd>{{ $user->role() }}</dd></div>
        </dl>
    </section>
</main>
@endsection
