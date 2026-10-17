@extends('layouts.workspace')

@section('title', 'Invitations · SkinSewa')

@section('workspace-content')
<section class="page-heading-row">
    <div>
        <p class="eyebrow">ADMINISTRATION</p>
        <h1>Invitations</h1>
        <p class="muted">Invite health workers and dermatologists to join SkinSewa.</p>
    </div>
    <a class="primary-button" href="{{ route('invitations.create') }}">Send invitation</a>
</section>

@if (session('status')) <div class="success-alert" role="status">{{ session('status') }}</div> @endif
@if ($errors->any()) <div class="alert" role="alert">{{ $errors->first() }}</div> @endif

<section class="panel users-panel" aria-labelledby="invitation-list-title">
    <div class="panel-heading">
        <div><h2 id="invitation-list-title">Invitation history</h2><p class="muted">Pending links expire with their one-time registration code.</p></div>
        <span class="result-count">{{ $invitations->total() }} {{ str('invitation')->plural($invitations->total()) }}</span>
    </div>
    <form class="user-filters invitation-filters" method="get" action="{{ route('invitations.index') }}">
        <label class="filter-search"><span>Search by email</span><input type="search" name="q" value="{{ $search }}" placeholder="name@example.com"></label>
        <label><span>Status</span><select name="status"><option value="">All statuses</option><option value="pending" @selected($status === 'pending')>Pending</option><option value="accepted" @selected($status === 'accepted')>Accepted</option><option value="expired" @selected($status === 'expired')>Expired</option><option value="revoked" @selected($status === 'revoked')>Revoked</option></select></label>
        <label><span>Order by</span><select name="sort"><option value="created_desc" @selected($sort === 'created_desc')>Created date: newest</option><option value="created_asc" @selected($sort === 'created_asc')>Created date: oldest</option><option value="email_asc" @selected($sort === 'email_asc')>Email: A to Z</option><option value="email_desc" @selected($sort === 'email_desc')>Email: Z to A</option></select></label>
        <label><span>Rows per page</span><select name="per_page">@foreach ([10, 25, 50] as $size)<option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>@endforeach</select></label>
        <button class="primary-button filter-button" type="submit">Apply</button>
        @if ($search !== '' || $status !== '' || $sort !== 'created_desc' || $perPage !== 10) <a class="reset-filters" href="{{ route('invitations.index') }}">Reset</a> @endif
    </form>
    <div class="table-scroll">
        <table class="users-table invitations-table">
            <thead><tr><th scope="col">Email</th><th scope="col">User type</th><th scope="col">Status</th><th scope="col">Expires</th><th scope="col">Action</th></tr></thead>
            <tbody>
                @forelse ($invitations as $invitation)
                    <tr>
                        <td><strong class="invitation-email">{{ $invitation->email }}</strong></td>
                        <td>{{ $invitation->user_type_label }}</td>
                        <td><span class="invitation-status invitation-status-{{ $invitation->invitation_status }}">{{ str($invitation->invitation_status)->title() }}</span></td>
                        <td>{{ $invitation->otp_expires_at?->format('M j, Y g:i A') ?: '—' }}</td>
                        <td>
                            @if (! $invitation->is_registered)
                                <form method="post" action="{{ route('invitations.resend', $invitation) }}" onsubmit="return confirm('Send a fresh link and code to {{ addslashes($invitation->email) }}?');">
                                    @csrf
                                    <button class="secondary-button small-button" type="submit">Resend</button>
                                </form>
                            @else
                                <span class="table-subtext">Completed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty-state" colspan="5">{{ $search !== '' ? 'No invitations match this email.' : 'No invitations have been sent yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <footer class="table-footer">
        <p>@if ($invitations->total()) Showing <strong>{{ $invitations->firstItem() }}–{{ $invitations->lastItem() }}</strong> of <strong>{{ $invitations->total() }}</strong> invitations @else No invitations to show @endif</p>
        @if ($invitations->hasPages())
            <nav class="simple-pagination" aria-label="Pagination">
                @if ($invitations->onFirstPage()) <span class="pagination-disabled">Previous</span> @else <a href="{{ $invitations->previousPageUrl() }}">Previous</a> @endif
                <span>Page {{ $invitations->currentPage() }} of {{ $invitations->lastPage() }}</span>
                @if ($invitations->hasMorePages()) <a href="{{ $invitations->nextPageUrl() }}">Next</a> @else <span class="pagination-disabled">Next</span> @endif
            </nav>
        @endif
    </footer>
</section>
@endsection
