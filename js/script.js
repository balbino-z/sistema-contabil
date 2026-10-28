'use strict';

(() => {
    const confirmationMessage =
        'Tem certeza que deseja excluir este registro? Esta ação não pode ser desfeita.';
    const formSelector = 'form.btn-excluir, form[data-confirm]';
    const buttonSelector = 'button.btn-excluir, button[data-confirm]';

    document.addEventListener('submit', (event) => {
        if (event.target instanceof HTMLFormElement && event.target.matches(formSelector)) {
            if (!window.confirm(confirmationMessage)) {
                event.preventDefault();
            }
        }
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const button = event.target.closest(buttonSelector);
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const form = button.form;
        if (button.type === 'submit' && form !== null && form.matches(formSelector)) {
            return;
        }

        if (!window.confirm(confirmationMessage)) {
            event.preventDefault();
        }
    });
})();
