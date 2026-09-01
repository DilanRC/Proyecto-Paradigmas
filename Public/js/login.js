const SESSION_KEY = 'tindercows:login';
const PRIVATE_ROUTES = new Set([
    'productores.php',
    'compradores.php',
    'transportistas.php',
    'vehiculos.php',
    'pagometodos.php',
]);

export function resolveNext(search = '') {
    const requested = new URLSearchParams(search).get('next');
    return requested && PRIVATE_ROUTES.has(requested)
        ? requested
        : 'productores.php';
}

function setError(control, message) {
    const error = document.querySelector(`[data-error-for="${control.name}"]`);
    control.setAttribute('aria-invalid', message ? 'true' : 'false');
    if (error) error.textContent = message;
}

function validate(form) {
    let valid = true;
    for (const control of form.querySelectorAll('input')) {
        let message = '';
        if (!control.validity.valid) {
            message = control.type === 'email'
                ? 'Ingrese un correo válido.'
                : 'Ingrese al menos 8 caracteres.';
            valid = false;
        }
        setError(control, message);
    }
    return valid;
}

function initialize() {
    const form = document.querySelector('#formulario-login');
    const status = document.querySelector('#login-status');
    if (!form || !status) return;

    form.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement) setError(event.target, '');
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!validate(form)) return;

        const email = String(new FormData(form).get('email') ?? '').trim();
        sessionStorage.setItem(SESSION_KEY, JSON.stringify({
            authenticated: true,
            version: 1,
            email,
            startedAt: new Date().toISOString(),
            mode: 'local-browser-session',
        }));
        status.textContent = 'Acceso confirmado. Abriendo TinderCows…';
        window.location.assign(resolveNext(window.location.search));
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
