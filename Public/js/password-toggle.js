function updateToggle(button, input) {
    const visible = input.type === 'text';
    button.setAttribute('aria-pressed', String(visible));
    button.setAttribute('aria-label', visible ? 'Ocultar contraseña' : 'Mostrar contraseña');
    const icon = button.querySelector('i');
    if (icon) icon.className = `fa-solid ${visible ? 'fa-eye-slash' : 'fa-eye'}`;
}
function initialize() {
    for (const button of document.querySelectorAll('[data-password-toggle]')) {
        if (button.dataset.ready === 'true') continue;
        const inputId = button.getAttribute('aria-controls');
        const input = inputId ? document.getElementById(inputId) : null;
        if (!(input instanceof HTMLInputElement) || input.type !== 'password') continue;
        button.dataset.ready = 'true';
        updateToggle(button, input);
        button.addEventListener('click', () => {
            const selectionStart = input.selectionStart;
            const selectionEnd = input.selectionEnd;
            input.type = input.type === 'password' ? 'text' : 'password';
            updateToggle(button, input);
            input.focus({ preventScroll: true });
            if (selectionStart !== null && selectionEnd !== null) {
                input.setSelectionRange(selectionStart, selectionEnd);
            }
        });
    }
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
}
