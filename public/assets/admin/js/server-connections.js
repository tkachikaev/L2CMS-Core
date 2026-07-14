(() => {
    const forms = document.querySelectorAll('[data-game-server-connection-form]');

    forms.forEach((form) => {
        const toggle = form.querySelector('[data-use-login-connection]');
        const fields = form.querySelector('[data-server-database-own-fields]');
        if (!(toggle instanceof HTMLInputElement) || !(fields instanceof HTMLElement)) {
            return;
        }

        const sync = () => {
            const useLogin = toggle.checked;
            fields.hidden = useLogin;
            fields.querySelectorAll('input, select').forEach((control) => {
                if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement) {
                    control.disabled = useLogin;
                }
            });
        };

        toggle.addEventListener('change', sync);
        sync();
    });
})();
