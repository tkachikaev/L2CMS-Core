(() => {
    const readyAttribute = 'data-account-navigation-ready';

    const closeMobileSidebar = () => {
        document.documentElement.classList.remove('account-sidebar-open');
        document.querySelector('[data-account-sidebar-toggle]')?.setAttribute('aria-expanded', 'false');
    };

    const initializeShell = () => {
        const sidebar = document.querySelector('[data-account-sidebar]');
        const toggle = document.querySelector('[data-account-sidebar-toggle]');

        if (toggle && !toggle.hasAttribute(readyAttribute)) {
            toggle.setAttribute(readyAttribute, '');
            toggle.addEventListener('click', () => {
                const willOpen = !document.documentElement.classList.contains('account-sidebar-open');
                document.documentElement.classList.toggle('account-sidebar-open', willOpen);
                toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
        }

        if (sidebar && !sidebar.hasAttribute(readyAttribute)) {
            sidebar.setAttribute(readyAttribute, '');
            sidebar.addEventListener('click', (event) => {
                if (event.target instanceof Element && event.target.closest('a[wire\\:navigate]')) {
                    closeMobileSidebar();
                }
            });
        }

        document.querySelectorAll('.account-profile-menu[open]').forEach((menu) => menu.removeAttribute('open'));
    };

    const beginNavigation = () => {
        document.documentElement.classList.add('account-is-navigating');
        closeMobileSidebar();
    };

    const finishNavigation = () => {
        initializeShell();

        window.requestAnimationFrame(() => {
            document.documentElement.classList.remove('account-is-navigating');
        });
    };

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeMobileSidebar();
            document.querySelectorAll('.account-profile-menu[open]').forEach((menu) => menu.removeAttribute('open'));
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (!document.documentElement.classList.contains('account-sidebar-open')) {
            return;
        }

        if (event.target instanceof Element && !event.target.closest('[data-account-sidebar], [data-account-sidebar-toggle]')) {
            closeMobileSidebar();
        }
    });

    document.addEventListener('livewire:navigate', beginNavigation);
    document.addEventListener('livewire:navigated', finishNavigation);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', finishNavigation, { once: true });
    } else {
        finishNavigation();
    }
})();
