@extends('layouts.workspace')

@section('title', 'Send invitation · SkinSewa')

@section('workspace-content')
<div class="back-link-row"><a class="back-link" href="{{ route('invitations.index') }}">← Invitations</a></div>
<section class="page-heading-row compact-heading">
    <div><p class="eyebrow">ADMINISTRATION / INVITATIONS</p><h1>Send an invitation</h1><p class="muted">The invited user will receive a registration link and one-time code by email.</p></div>
</section>
@if ($errors->any()) <div class="alert" role="alert">{{ $errors->first() }}</div> @endif
<section class="panel edit-user-panel" aria-labelledby="invitation-form-title">
    <div class="panel-heading"><div><h2 id="invitation-form-title">Invite a team member</h2><p class="muted">Only health workers and dermatologists can be invited.</p></div></div>
    @if (config('mail.default') === 'log')
        <p class="mail-delivery-note">Local mail is set to log. Invitation emails are written to <code>storage/logs/laravel.log</code>; configure SMTP to deliver them to the recipient.</p>
    @endif
    <form class="edit-user-form invitation-form" method="post" action="{{ route('invitations.store') }}">
        @csrf
        <div class="edit-form-grid">
            <label class="full-width-field"><span>Email address</span><input name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="100" required autofocus></label>
            <label class="full-width-field"><span>User type</span><select name="user_type" required><option value="">Select a user type</option><option value="MEDICAL_OFFICER" @selected(old('user_type') === 'MEDICAL_OFFICER')>Health worker</option><option value="DERMATOLOGIST" @selected(old('user_type') === 'DERMATOLOGIST')>Dermatologist</option></select></label>
        </div>
        <div class="edit-form-actions"><a class="secondary-button" href="{{ route('invitations.index') }}">Cancel</a><button class="primary-button" type="submit">Send invitation</button></div>
    </form>
</section>
@endsection
