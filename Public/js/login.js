const SESSION_KEY = 'tindercows:login';
const PROFILE_KEY = 'tindercows:profile';
const PRIVATE_ROUTES = new Set([
    'productores.php',
    'compradores.php',
    'transportistas.php',
    'vehiculos.php',
    'pagometodos.php',
]);
const PUBLIC_DESTINATIONS = new Set(['explorar.php', 'mi-actividad.php', 'fletes.php', 'publicar.php']);

export function resolveNext(search = '', hasProfile = false) {
    const requested = new URLSearchParams(search).get('next');
    if (requested && (PRIVATE_ROUTES.has(requested) || PUBLIC_DESTINATIONS.has(requested))) return requested;
    return hasProfile ? 'mi-actividad.php' : 'explorar.php';
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
            message = control.type === 'email' ? 'Ingrese un correo válido.' : 'Ingrese al menos 8 caracteres.';
            valid = false;
        }
        setError(control, message);
    }
    return valid;
}

function hasProfile() {
    try { return Boolean(JSON.parse(sessionStorage.getItem(PROFILE_KEY) || 'null')?.persona); } catch { return false; }
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
            version: 2,
            email,
            startedAt: new Date().toISOString(),
            mode: 'frontend-prototype',
        }));
        status.textContent = 'Acceso confirmado en el prototipo. Abriendo TinderCows…';
        window.location.assign(resolveNext(window.location.search, hasProfile()));
    });
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
