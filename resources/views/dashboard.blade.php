@extends('layouts.workspace')

@section('title', 'Dashboard · SkinSewa')

@section('workspace-content')
@php($user = auth()->user())
<div class="dashboard-shell">
    <section class="welcome-panel dashboard-welcome">
        <div>
            <p class="eyebrow">YOUR SKIN. OUR CARE.</p>
            <h1>Welcome, {{ $user->first_name }}</h1>
            <p class="muted">You’re signed in to the SkinSewa care workspace.</p>
        </div>
        @if ($user->isAdministrator())
            <a class="primary-button dashboard-action" href="{{ route('users.index') }}">Manage users <span aria-hidden="true">→</span></a>
        @endif
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
</div>
@endsection
