<aside class="workspace-rail" aria-label="Quick navigation">
    <a class="rail-brand" href="{{ route('home') }}" aria-label="SkinSewa dashboard">
        <img src="{{ asset('images/skinsewa-logo.png') }}" alt="">
    </a>
    <nav class="rail-nav" aria-label="Quick links">
        <a class="rail-link {{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}" title="Dashboard" aria-label="Dashboard">
            <span aria-hidden="true">⌂</span>
        </a>
        <a class="rail-link {{ request()->routeIs('resources.*') ? 'is-active' : '' }}" href="{{ route('resources.index') }}" title="Resources" aria-label="Resources">
            <span aria-hidden="true">▤</span>
        </a>
        @if (auth()->user()->isAdministrator() || in_array(auth()->user()->role(), [\App\Models\User::ROLE_MEDICAL_OFFICER, \App\Models\User::ROLE_DERMATOLOGIST], true))
            <a class="rail-link {{ request()->routeIs('cases.*') ? 'is-active' : '' }}" href="{{ route('cases.index') }}" title="{{ auth()->user()->isAdministrator() ? 'All cases' : (auth()->user()->role() === \App\Models\User::ROLE_DERMATOLOGIST ? 'Assigned cases' : 'Patient cases') }}" aria-label="{{ auth()->user()->isAdministrator() ? 'All cases' : (auth()->user()->role() === \App\Models\User::ROLE_DERMATOLOGIST ? 'Assigned cases' : 'Patient cases') }}">
                <span aria-hidden="true">✚</span>
            </a>
        @endif
        @if (auth()->user()->isAdministrator())
            <a class="rail-link {{ request()->routeIs('users.*') ? 'is-active' : '' }}" href="{{ route('users.index') }}" title="Users" aria-label="Users">
                <span aria-hidden="true">♙</span>
            </a>
            <a class="rail-link {{ request()->routeIs('invitations.*') ? 'is-active' : '' }}" href="{{ route('invitations.index') }}" title="Invitations" aria-label="Invitations">
                <span aria-hidden="true">✉</span>
            </a>
        @endif
        <a class="rail-link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}" href="{{ route('profile.show') }}" title="Profile" aria-label="Profile">
            <span aria-hidden="true">◉</span>
        </a>
    </nav>
    <div class="rail-footer">
        <button class="sidebar-collapse-toggle" type="button" aria-label="Collapse sidebar" aria-pressed="false" title="Collapse sidebar">
            <img src="{{ asset('images/sidebar-collapse.svg') }}" alt="" aria-hidden="true">
        </button>
        <a class="rail-avatar" href="{{ route('profile.show') }}" title="{{ auth()->user()->full_name }}" aria-label="Your profile">
            {{ mb_strtoupper(mb_substr(auth()->user()->first_name, 0, 1).mb_substr(auth()->user()->last_name, 0, 1)) }}
        </a>
    </div>
</aside>
