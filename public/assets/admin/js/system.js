document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('[data-copy-system-report]');
    const source = document.querySelector('[data-system-report]');
    const state = document.querySelector('[data-system-copy-state]');

    if (!(button instanceof HTMLButtonElement) || !(source instanceof HTMLTextAreaElement)) {
        return;
    }

    const setState = (message, type = 'success') => {
        if (!(state instanceof HTMLElement)) {
            return;
        }

        state.textContent = message;
        state.dataset.type = type;
    };

    const fallbackCopy = () => {
        source.hidden = false;
        source.focus();
        source.select();

        const copied = document.execCommand('copy');
        source.hidden = true;

        if (!copied) {
            throw new Error('Copy command failed.');
        }
    };

    button.addEventListener('click', async () => {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(source.value);
            } else {
                fallbackCopy();
            }

            setState('Отчёт скопирован.');
        } catch {
            setState('Не удалось скопировать отчёт.', 'error');
        }
    });
});
