@extends('layouts.workspace')

@section('title', 'Your profile · SkinSewa')

@section('workspace-content')
<section class="page-heading-row compact-heading">
    <div>
        <p class="eyebrow">YOUR ACCOUNT</p>
        <h1>Your profile</h1>
        <p class="muted">Your account and workplace details.</p>
    </div>
    <a class="primary-button" href="{{ route('profile.edit') }}">Edit details</a>
</section>

@if (session('status'))
    <div class="success-alert" role="status">{{ session('status') }}</div>
@endif

<section class="panel profile-view-panel" aria-labelledby="profile-view-title">
    <div class="panel-heading">
        <div class="profile-heading profile-view-heading">
            <div class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1)) }}</div>
            <div>
                <h2 id="profile-view-title">{{ $user->full_name }}</h2>
                <span class="role-badge">{{ $user->roleLabel() }}</span>
            </div>
        </div>
    </div>
    <dl class="profile-details profile-view-details">
        <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
        <div><dt>Phone</dt><dd>{{ $user->phone ?: 'Not provided' }}</dd></div>
        <div><dt>Facility</dt><dd>{{ $user->facility_name ?: 'Not assigned' }}</dd></div>
        <div><dt>Specialty</dt><dd>{{ $user->specialty ?: 'Not provided' }}</dd></div>
    </dl>
</section>
@endsection
