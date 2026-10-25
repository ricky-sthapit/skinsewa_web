@extends('layouts.workspace')

@section('title', 'Edit user · SkinSewa')

@section('workspace-content')
<div class="back-link-row">
    <a class="back-link" href="{{ route('users.index') }}">← Users</a>
</div>
<section class="page-heading-row compact-heading">
    <div>
        <p class="eyebrow">ADMINISTRATION / USERS</p>
        <h1>Edit user</h1>
        <p class="muted">Update {{ $user->full_name }}’s profile and access level.</p>
    </div>
</section>

@if ($errors->any())
    <div class="alert" role="alert">{{ $errors->first() }}</div>
@endif

<section class="panel edit-user-panel" aria-labelledby="edit-user-title">
    <div class="panel-heading">
        <div>
            <h2 id="edit-user-title">Account details</h2>
            <p class="muted">Leave the password blank to keep the current password.</p>
        </div>
    </div>
    <form class="edit-user-form" method="post" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')
        <div class="edit-form-grid">
            <label>
                <span>First name</span>
                <input name="first_name" value="{{ old('first_name', $user->first_name) }}" maxlength="100" required>
            </label>
            <label>
                <span>Last name</span>
                <input name="last_name" value="{{ old('last_name', $user->last_name) }}" maxlength="100" required>
            </label>
            <label class="full-width-field">
                <span>Email address</span>
                <input name="email" type="email" value="{{ old('email', $user->email) }}" maxlength="100" required>
            </label>
            <label>
                <span>Clinical role</span>
                <select name="user_type" required>
                    @foreach ([\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_MEDICAL_OFFICER, \App\Models\User::ROLE_DERMATOLOGIST] as $role)
                        <option value="{{ $role }}" @selected(old('user_type', $user->user_type) === $role)>{{ $role === \App\Models\User::ROLE_ADMIN ? 'Admin only' : str($role)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </label>
            <div class="full-width-field administrator-access">
                <input type="hidden" name="is_admin" value="0">
                <label class="admin-access-toggle">
                    <input type="checkbox" name="is_admin" value="1" @checked((bool) old('is_admin', $user->isAdministrator()))>
                    <span>Administrator access</span>
                </label>
                <small>Can manage users and system resources. This is independent of the clinical role.</small>
            </div>
            <label>
                <span>Phone</span>
                <input name="phone" type="tel" value="{{ old('phone', $user->phone) }}" maxlength="32">
            </label>
            <label>
                <span>Facility</span>
                <input name="facility_name" value="{{ old('facility_name', $user->facility_name) }}" maxlength="150">
            </label>
            <label>
                <span>Specialty</span>
                <input name="specialty" value="{{ old('specialty', $user->specialty) }}" maxlength="120">
            </label>
            <label>
                <span>New password <small>(optional)</small></span>
                <input name="password" type="password" autocomplete="new-password">
            </label>
            <label>
                <span>Confirm new password</span>
                <input name="password_confirmation" type="password" autocomplete="new-password">
            </label>
        </div>
        <div class="edit-form-actions">
            <a class="secondary-button" href="{{ route('users.index') }}">Cancel</a>
            <button class="primary-button" type="submit">Save changes</button>
        </div>
    </form>
</section>
@endsection
