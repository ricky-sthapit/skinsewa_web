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
        @if (auth()->user()->isAdministrator())
            <a class="sidebar-link {{ request()->routeIs('users.*') ? 'is-active' : '' }}" href="{{ route('users.index') }}" title="Users">
                <span class="sidebar-icon" aria-hidden="true">♙</span><span class="sidebar-link-label">Users</span>
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
