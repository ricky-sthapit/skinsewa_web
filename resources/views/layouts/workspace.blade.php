@extends('layouts.app')

@section('content')
<div class="workspace-layout">
    @include('partials.rail')
    @include('partials.sidebar')
    <main class="workspace-main">
        <header class="workspace-topbar">
            <details class="account-menu">
                <summary class="account-trigger" aria-label="Open account menu">
                    <span class="sidebar-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->first_name, 0, 1).mb_substr(auth()->user()->last_name, 0, 1)) }}</span>
                    <span class="topbar-user">
                        <span class="topbar-user-name">{{ auth()->user()->full_name }}</span>
                        <span class="topbar-role">{{ auth()->user()->roleLabel() }}</span>
                    </span>
                    <img class="account-chevron" src="{{ asset('images/chevron-down.svg') }}" alt="" aria-hidden="true">
                </summary>
                <div class="account-popover">
                    <span class="account-label">CURRENT USER</span>
                    <strong>{{ auth()->user()->full_name }}</strong>
                    <span class="account-email">{{ auth()->user()->email }}</span>
                    <span class="role-badge account-role">{{ auth()->user()->roleLabel() }}</span>
                    <a class="account-menu-link" href="{{ route('profile.edit') }}">Edit profile details</a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="account-menu-link account-signout" type="submit">Sign out</button>
                    </form>
                </div>
            </details>
        </header>
        <div class="workspace-content">
            @yield('workspace-content')
        </div>
    </main>
</div>
<script>
    (() => {
        const sidebar = document.querySelector('.workspace-sidebar');
        const navigationToggle = document.querySelector('.mobile-nav-toggle');
        const workspace = document.querySelector('.workspace-layout');
        const collapseToggles = document.querySelectorAll('.sidebar-collapse-toggle');
        const accountMenu = document.querySelector('.account-menu');
        const accountTrigger = accountMenu?.querySelector('.account-trigger');
        const desktopSidebar = window.matchMedia('(min-width: 901px)');
        let savedCollapsed = false;
        try {
            savedCollapsed = window.localStorage.getItem('skinsewa-sidebar-collapsed') === 'true';
        } catch (_) {}

        const setDesktopCollapsed = (collapsed, persist = false) => {
            const isCollapsed = desktopSidebar.matches && collapsed;
            workspace?.classList.toggle('is-sidebar-collapsed', isCollapsed);
            collapseToggles.forEach((toggle) => {
                toggle.setAttribute('aria-pressed', String(isCollapsed));
                toggle.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
                toggle.title = isCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
            });
            if (persist) {
                savedCollapsed = collapsed;
                try {
                    window.localStorage.setItem('skinsewa-sidebar-collapsed', String(collapsed));
                } catch (_) {}
            }
        };

        setDesktopCollapsed(savedCollapsed);
        collapseToggles.forEach((toggle) => toggle.addEventListener('click', () => {
            setDesktopCollapsed(!workspace?.classList.contains('is-sidebar-collapsed'), true);
        }));
        window.addEventListener('resize', () => setDesktopCollapsed(savedCollapsed));

        const syncAccountMenuLabel = () => {
            accountTrigger?.setAttribute('aria-label', accountMenu.open ? 'Close account menu' : 'Open account menu');
        };
        accountMenu?.addEventListener('toggle', syncAccountMenuLabel);
        syncAccountMenuLabel();

        const closeNavigation = () => {
            sidebar?.classList.remove('is-open');
            navigationToggle?.setAttribute('aria-expanded', 'false');
            navigationToggle?.setAttribute('aria-label', 'Open navigation menu');
        };

        navigationToggle?.addEventListener('click', () => {
            const isOpen = sidebar.classList.toggle('is-open');
            navigationToggle.setAttribute('aria-expanded', String(isOpen));
            navigationToggle.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        });

        document.addEventListener('pointerdown', (event) => {
            if (accountMenu?.open && !accountMenu.contains(event.target)) accountMenu.open = false;
            if (sidebar?.classList.contains('is-open') && !sidebar.contains(event.target)) closeNavigation();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') return;
            if (accountMenu?.open) accountMenu.open = false;
            if (sidebar?.classList.contains('is-open')) {
                closeNavigation();
                navigationToggle?.focus();
            }
        });

        sidebar?.querySelectorAll('.sidebar-nav a').forEach((link) => link.addEventListener('click', closeNavigation));
    })();
</script>
@endsection
