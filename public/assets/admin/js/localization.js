(() => {
    'use strict';

    const activate = (root, locale) => {
        root.querySelectorAll('[data-locale-tab]').forEach((tab) => {
            const active = tab.dataset.localeTab === locale;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        root.querySelectorAll('[data-locale-panel]').forEach((panel) => {
            const active = panel.dataset.localePanel === locale;
            panel.classList.toggle('active', active);
            panel.hidden = !active;
        });

        const form = root.closest('form');
        form?.querySelectorAll('[data-preview-locale]').forEach((input) => {
            input.value = locale;
        });
    };

    document.querySelectorAll('[data-locale-tabs]').forEach((root) => {
        root.querySelectorAll('[data-locale-tab]').forEach((tab) => {
            tab.addEventListener('click', () => activate(root, tab.dataset.localeTab ?? ''));
        });
    });
})();
