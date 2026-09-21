import { flujoLogin, guardarActor, leerBearer, SESSION_KEY } from './shared/sesion.js';

const PRIVATE_ROUTES = new Set([
    'productores.php',
    'compradores.php',
    'transportistas.php',
    'vehiculos.php',
    'pagometodos.php',
]);

const PUBLIC_DESTINATIONS = new Set(['explorar.php']);

export function resolveNext(search = '') {
    const requested = new URLSearchParams(search).get('next');
    return requested && (PRIVATE_ROUTES.has(requested) || PUBLIC_DESTINATIONS.has(requested))
        ? requested
        : 'explorar.php';
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

/**
 * Marca local de sesión del demo. El backend sigue exigiendo Bearer (DEC-30);
 * este marcador únicamente abre el shell privado para inspección local y es la
 * sesión "modo público" documentada en DEC-33: sin sesión real solo hay
 * lecturas, y los 401 SIN_SESION se muestran como "inicie sesión".
 */
function guardarMarcadorLocal(email) {
    if (typeof globalThis === 'undefined' || !globalThis.sessionStorage) return;
    globalThis.sessionStorage.setItem(SESSION_KEY, JSON.stringify({
        authenticated: true,
        version: 1,
        email: String(email ?? '').trim(),
        startedAt: new Date().toISOString(),
        mode: 'local-browser-session',
    }));
}

async function enviarLogin(event, form, status, storage) {
    event.preventDefault();
    if (!validate(form)) return false;

    const email = String(new FormData(form).get('email') ?? '').trim();

    // Tramo A: resolver la superficie contra identidad.php. Si el navegador ya
    // porta un Bearer real (proveedor de identidad), flujoLogin conserva el
    // actor y las superficies privadas funcionan de verdad. Sin Bearer, el
    // resultado es modo público y la sesión local no representa credenciales
    // verificadas contra el servidor.
    const resuelto = await flujoLogin({ email, storage });
    if (resuelto.autenticado && resuelto.actor && storage) {
        guardarActor(storage, resuelto.actor, leerBearer(storage));
        status.textContent = 'Sesión verificada con el proveedor. Abriendo TinderCows…';
    } else {
        guardarMarcadorLocal(email);
        status.textContent = 'Acceso confirmado (modo público de solo lectura). '
            + 'Para escribir, inicie sesión con su proveedor de identidad.';
    }

    window.location.assign(resolveNext(window.location.search));
    return true;
}

function initialize() {
    const form = document.querySelector('#formulario-login');
    const status = document.querySelector('#login-status');
    if (!form || !status) return;

    form.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement) setError(event.target, '');
    });

    form.addEventListener('submit', (event) => {
        enviarLogin(event, form, status, globalThis.sessionStorage);
    });
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}