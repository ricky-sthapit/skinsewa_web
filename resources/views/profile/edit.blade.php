@extends('layouts.workspace')

@section('title', 'Your profile · SkinSewa')

@section('workspace-content')
<div class="back-link-row">
    <a class="back-link" href="{{ route('profile.show') }}">← Profile</a>
</div>
<section class="page-heading-row compact-heading">
    <div>
        <p class="eyebrow">YOUR ACCOUNT</p>
        <h1>Profile details</h1>
        <p class="muted">Update your contact and work details. Your name and email are managed separately.</p>
    </div>
</section>

@if (session('status'))
    <div class="success-alert" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert" role="alert">{{ $errors->first() }}</div>
@endif

<section class="panel edit-user-panel profile-edit-panel" aria-labelledby="profile-edit-title">
    <div class="panel-heading">
        <div>
            <h2 id="profile-edit-title">Contact and workplace</h2>
            <p class="muted">Signed in as {{ $user->full_name }} · {{ $user->email }}</p>
        </div>
    </div>
    <form class="edit-user-form" method="post" action="{{ route('profile.update') }}">
        @csrf
        @method('PUT')
        <div class="edit-form-grid">
            <label>
                <span>Phone</span>
                <input name="phone" type="tel" value="{{ old('phone', $user->phone) }}" maxlength="32" autocomplete="tel">
            </label>
            <label>
                <span>Facility</span>
                <input name="facility_name" value="{{ old('facility_name', $user->facility_name) }}" maxlength="150">
            </label>
            <label>
                <span>Specialty</span>
                <input name="specialty" value="{{ old('specialty', $user->specialty) }}" maxlength="120">
            </label>
        </div>
        <div class="edit-form-actions">
            <a class="secondary-button" href="{{ route('profile.show') }}">Cancel</a>
            <button class="primary-button" type="submit">Save details</button>
        </div>
    </form>
</section>
@endsection
