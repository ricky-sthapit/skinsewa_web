@extends('layouts.workspace')

@section('title', 'Users · SkinSewa')

@section('workspace-content')
<section class="page-heading-row">
    <div>
        <p class="eyebrow">ADMINISTRATION</p>
        <h1>Users</h1>
        <p class="muted">Manage health workers, dermatologists, and administrators.</p>
    </div>
    <div class="result-count">{{ $users->total() }} active {{ str('user')->plural($users->total()) }}</div>
</section>

@if (session('status'))
    <div class="success-alert" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="alert" role="alert">{{ $errors->first() }}</div>
@endif

<section class="panel users-panel" aria-labelledby="user-list-title">
    <div class="panel-heading">
        <div>
            <h2 id="user-list-title">User directory</h2>
            <p class="muted">Search by name or email, then narrow by account level.</p>
        </div>
    </div>

    <form class="user-filters" method="get" action="{{ route('users.index') }}">
        <label class="filter-search">
            <span>Search</span>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name or email" autocomplete="off">
        </label>
        <label>
            <span>User type</span>
            <select name="user_type">
                <option value="">All user types</option>
                @foreach (\App\Models\User::ROLES as $role)
                    <option value="{{ $role }}" @selected($filters['user_type'] === $role)>{{ str($role)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Rows per page</span>
            <select name="per_page">
                @foreach ($pageSizes as $size)
                    <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                @endforeach
            </select>
        </label>
        <button class="primary-button filter-button" type="submit">Apply filters</button>
        @if ($filters['q'] !== '' || $filters['user_type'] !== '' || $filters['per_page'] !== 10)
            <a class="reset-filters" href="{{ route('users.index') }}">Reset</a>
        @endif
    </form>

    <div class="table-scroll">
        <table class="users-table">
            <thead>
                <tr>
                    <th scope="col">User</th>
                    <th scope="col">Access / clinical role</th>
                    <th scope="col">Phone</th>
                    <th scope="col">Facility / specialty</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $listedUser)
                    <tr>
                        <td>
                            <div class="listed-user">
                                <span class="listed-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($listedUser->first_name, 0, 1).mb_substr($listedUser->last_name, 0, 1)) }}</span>
                                <span class="listed-user-copy">
                                    <strong>{{ $listedUser->full_name }}</strong>
                                    <span>{{ $listedUser->email }}</span>
                                </span>
                            </div>
                        </td>
                        <td><span class="role-badge">{{ $listedUser->roleLabel() }}</span></td>
                        <td>{{ $listedUser->phone ?: '—' }}</td>
                        <td>
                            <span class="stacked-detail">{{ $listedUser->facility_name ?: '—' }}</span>
                            @if ($listedUser->specialty)
                                <span class="table-subtext">{{ $listedUser->specialty }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="table-actions">
                                <a class="secondary-button small-button" href="{{ route('users.edit', $listedUser) }}">Edit</a>
                                @if (! $listedUser->is(auth()->user()))
                                    <form method="post" action="{{ route('users.destroy', $listedUser) }}" onsubmit="return confirm('Deactivate {{ $listedUser->full_name }}? They will no longer be able to sign in.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="danger-button small-button" type="submit">Deactivate</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="empty-state" colspan="5">No users match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <footer class="table-footer">
        <p>
            @if ($users->total() > 0)
                Showing <strong>{{ $users->firstItem() }}–{{ $users->lastItem() }}</strong> of <strong>{{ $users->total() }}</strong> users
            @else
                No users to show
            @endif
        </p>
        @if ($users->hasPages())
            <nav class="simple-pagination" aria-label="Pagination">
                @if ($users->onFirstPage())
                    <span class="pagination-disabled">Previous</span>
                @else
                    <a href="{{ $users->previousPageUrl() }}">Previous</a>
                @endif
                <span>Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span>
                @if ($users->hasMorePages())
                    <a href="{{ $users->nextPageUrl() }}">Next</a>
                @else
                    <span class="pagination-disabled">Next</span>
                @endif
            </nav>
        @endif
    </footer>
</section>
@endsection
