@extends('layouts.workspace')

@section('title', 'Dashboard · SkinSewa')

@section('workspace-content')
<div class="dashboard-shell">
    <section class="welcome-panel dashboard-welcome">
        <div>
            <p class="eyebrow">YOUR SKIN. OUR CARE.</p>
            <h1>Hello, {{ $user->first_name }} <span aria-hidden="true">👋</span></h1>
            <p class="muted">Here’s the latest in your SkinSewa workspace.</p>
        </div>
        @if ($user->isAdministrator())
            <a class="primary-button dashboard-action" href="{{ route('users.index') }}">Manage users <span aria-hidden="true">→</span></a>
        @endif
    </section>

    @if ($user->isAdministrator())
        <section class="dashboard-stats" aria-label="User summary">
            <article class="dashboard-stat-card">
                <span class="stat-icon" aria-hidden="true">♙</span>
                <div><p>Total users</p><strong>{{ number_format($stats['users']) }}</strong><span>Active accounts</span></div>
            </article>
            <article class="dashboard-stat-card">
                <span class="stat-icon" aria-hidden="true">✚</span>
                <div><p>Health workers</p><strong>{{ number_format($stats['medical_officers']) }}</strong><span>Medical officers</span></div>
            </article>
            <article class="dashboard-stat-card">
                <span class="stat-icon" aria-hidden="true">＋</span>
                <div><p>Dermatologists</p><strong>{{ number_format($stats['dermatologists']) }}</strong><span>Doctor accounts</span></div>
            </article>
        </section>

        <section class="panel dashboard-users-panel" aria-labelledby="recent-users-title">
            <div class="panel-heading dashboard-panel-heading">
                <div>
                    <h2 id="recent-users-title">Recent users</h2>
                    <p class="muted">Recently added accounts in your care network.</p>
                </div>
                <a class="secondary-button" href="{{ route('users.index') }}">All users <span aria-hidden="true">→</span></a>
            </div>
            <div class="table-scroll">
                <table class="users-table dashboard-users-table">
                    <thead>
                        <tr>
                            <th scope="col">User</th>
                            <th scope="col">User type</th>
                            <th scope="col">Phone</th>
                            <th scope="col">Facility / specialty</th>
                            <th scope="col">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentUsers as $listedUser)
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
                                <td><span class="role-badge">{{ str($listedUser->role())->replace('_', ' ')->title() }}</span></td>
                                <td>{{ $listedUser->phone ?: '—' }}</td>
                                <td>
                                    <span class="stacked-detail">{{ $listedUser->facility_name ?: '—' }}</span>
                                    @if ($listedUser->specialty)
                                        <span class="table-subtext">{{ $listedUser->specialty }}</span>
                                    @endif
                                </td>
                                <td>{{ $listedUser->created_at?->format('M j, Y') ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td class="empty-state" colspan="5">No user accounts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <footer class="dashboard-table-footer">
                <span>Showing {{ $recentUsers->count() }} recent {{ str('user')->plural($recentUsers->count()) }}</span>
                <a href="{{ route('users.index') }}">Open user directory <span aria-hidden="true">→</span></a>
            </footer>
        </section>
    @else
        <section class="profile-card dashboard-profile-card" aria-labelledby="profile-title">
            <div class="profile-heading">
                <div class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->first_name, 0, 1).mb_substr($user->last_name, 0, 1)) }}</div>
                <div>
                    <h2 id="profile-title">{{ $user->full_name }}</h2>
                    <span class="role-badge">{{ str($user->role())->replace('_', ' ')->title() }}</span>
                </div>
                <a class="secondary-button dashboard-profile-link" href="{{ route('profile.show') }}">View profile</a>
            </div>
            <dl class="profile-details">
                <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
                <div><dt>Phone</dt><dd>{{ $user->phone ?: 'Not provided' }}</dd></div>
                <div><dt>Facility</dt><dd>{{ $user->facility_name ?: 'Not assigned' }}</dd></div>
                <div><dt>Specialty</dt><dd>{{ $user->specialty ?: 'Not provided' }}</dd></div>
            </dl>
        </section>
    @endif
</div>
@endsection
