(() => {
    'use strict';

    const initializeImageUpload = (container) => {
        const input = container.querySelector('[data-settings-file]');
        const selectButton = container.querySelector('[data-settings-file-select]');
        const preview = container.querySelector('[data-settings-preview]');
        const remove = container.querySelector('[data-settings-remove]');

        if (!(input instanceof HTMLInputElement) || !(preview instanceof HTMLElement)) {
            return;
        }

        let objectUrl = null;

        const clearObjectUrl = () => {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
        };

        const showSelectedFile = () => {
            const file = input.files?.[0];
            if (!file) {
                return;
            }

            clearObjectUrl();
            objectUrl = URL.createObjectURL(file);

            let image = preview.querySelector('[data-settings-preview-image]');
            if (!(image instanceof HTMLImageElement)) {
                image = document.createElement('img');
                image.dataset.settingsPreviewImage = '';
                preview.replaceChildren(image);
            }

            image.src = objectUrl;
            image.alt = container.dataset.previewAlt || 'Selected image preview';
            preview.classList.add('has-image');
            preview.classList.remove('marked-for-removal');

            if (remove instanceof HTMLInputElement) {
                remove.checked = false;
            }
        };

        selectButton?.addEventListener('click', () => input.click());
        input.addEventListener('change', showSelectedFile);

        remove?.addEventListener('change', () => {
            preview.classList.toggle('marked-for-removal', remove.checked);
        });

        window.addEventListener('beforeunload', clearObjectUrl, { once: true });
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-settings-image]').forEach(initializeImageUpload);
    });
})();
