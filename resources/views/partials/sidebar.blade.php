<aside class="workspace-sidebar">
    <div class="sidebar-header">
        <a class="brand sidebar-brand" href="{{ route('home') }}" aria-label="SkinSewa home">
            <img src="{{ asset('images/skinsewa-logo.png') }}" alt="">
            <span>SkinSewa</span>
        </a>
        <button class="sidebar-collapse-toggle" type="button" aria-label="Collapse sidebar" aria-pressed="false" title="Collapse sidebar">
            <img src="{{ asset('images/sidebar-collapse.svg') }}" alt="" aria-hidden="true">
        </button>
    </div>
    <button class="mobile-nav-toggle" type="button" aria-label="Open navigation menu" aria-expanded="false" aria-controls="workspace-navigation">
        <img src="{{ asset('images/menu.svg') }}" alt="" aria-hidden="true">
    </button>
    <p class="sidebar-caption">WORKSPACE</p>
    <nav class="sidebar-nav" id="workspace-navigation" aria-label="Main navigation">
        <a class="sidebar-link {{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}" title="Dashboard">
            <span class="sidebar-icon" aria-hidden="true">⌂</span><span class="sidebar-link-label">Dashboard</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('resources.*') ? 'is-active' : '' }}" href="{{ route('resources.index') }}" title="Resources">
            <span class="sidebar-icon" aria-hidden="true">▤</span><span class="sidebar-link-label">Resources</span>
        </a>
        @if (auth()->user()->isAdministrator() || in_array(auth()->user()->clinicalRole(), [\App\Models\User::ROLE_MEDICAL_OFFICER, \App\Models\User::ROLE_DERMATOLOGIST], true))
            <a class="sidebar-link {{ request()->routeIs('cases.*') ? 'is-active' : '' }}" href="{{ route('cases.index') }}" title="{{ auth()->user()->isAdministrator() ? 'All cases' : (auth()->user()->clinicalRole() === \App\Models\User::ROLE_DERMATOLOGIST ? 'Assigned cases' : 'Patient cases') }}">
                <span class="sidebar-icon" aria-hidden="true">✚</span><span class="sidebar-link-label">{{ auth()->user()->isAdministrator() ? 'All cases' : (auth()->user()->clinicalRole() === \App\Models\User::ROLE_DERMATOLOGIST ? 'Assigned cases' : 'Patient cases') }}</span>
            </a>
        @endif
        @if (auth()->user()->isAdministrator())
            <a class="sidebar-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}" href="{{ route('reports.index') }}" title="Reports">
                <span class="sidebar-icon" aria-hidden="true">▦</span><span class="sidebar-link-label">Reports</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('users.*') ? 'is-active' : '' }}" href="{{ route('users.index') }}" title="Users">
                <span class="sidebar-icon" aria-hidden="true">♙</span><span class="sidebar-link-label">Users</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('invitations.*') ? 'is-active' : '' }}" href="{{ route('invitations.index') }}" title="Invitations">
                <span class="sidebar-icon" aria-hidden="true">✉</span><span class="sidebar-link-label">Invitations</span>
            </a>
        @endif
    </nav>
    <div class="sidebar-account">
        <div class="sidebar-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->first_name, 0, 1).mb_substr(auth()->user()->last_name, 0, 1)) }}</div>
        <div class="sidebar-account-copy">
            <strong>{{ auth()->user()->full_name }}</strong>
            <span>{{ auth()->user()->email }}</span>
        </div>
    </div>
</aside>
