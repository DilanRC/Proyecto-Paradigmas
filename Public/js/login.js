import { request } from './shared/api.js';
import { clearAuthSession, getAccessToken, signInWithPassword, signOut } from './shared/supabase-auth.js';
import { readPublicProfile, syncPublicProfile } from './shared/public-profile.js';
import { clearAdminBrowserSession, writeAdminBrowserSession } from './shared/auth-gate.js';

const PUBLIC_DESTINATIONS = new Set(['explorar.php', 'mi-actividad.php', 'fletes.php', 'publicar.php']);
const ADMIN_DESTINATIONS = new Set(['productores.php', 'compradores.php', 'transportistas.php', 'vehiculos.php', 'pagometodos.php']);

export function resolveNext(search = '', hasProfile = false) {
    const requested = new URLSearchParams(search).get('next');
    if (requested && PUBLIC_DESTINATIONS.has(requested)) return requested;
    return hasProfile ? 'mi-actividad.php' : 'explorar.php';
}

export function resolveAdminNext(search = '') {
    const requested = new URLSearchParams(search).get('next');
    return requested && ADMIN_DESTINATIONS.has(requested) ? requested : 'productores.php';
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

function setBusy(form, button, busy) {
    form.setAttribute('aria-busy', String(busy));
    button.disabled = busy;
}

async function loadBusinessProfile() {
    const response = await request('api/mi-actividad.php');
    return syncPublicProfile(response.data);
}

async function isAdminAccount() {
    try {
        const token = await getAccessToken();
        if (!token) return false;
        const response = await fetch('api/admin-status.php', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
            cache: 'no-store',
        });
        return response.ok;
    } catch {
        return false;
    }
}

function initialize() {
    const form = document.querySelector('#formulario-login');
    const status = document.querySelector('#login-status');
    const submit = form?.querySelector('button[type="submit"]');
    if (!(form instanceof HTMLFormElement) || !status || !(submit instanceof HTMLButtonElement)) return;

    form.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement) setError(event.target, '');
        status.textContent = '';
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!validate(form) || submit.disabled) return;

        const data = new FormData(form);
        const email = String(data.get('email') ?? '').trim().toLowerCase();
        const password = String(data.get('password') ?? '');
        setBusy(form, submit, true);
        status.textContent = 'Verificando credenciales…';

        try {
            clearAuthSession();
            clearAdminBrowserSession();
            await signInWithPassword(email, password);
            status.textContent = 'Credenciales válidas. Vinculando tu identidad de TinderCows…';

            let profile;
            try {
                profile = await loadBusinessProfile();
            } catch (error) {
                if (error?.status === 401 || error?.status === 409) {
                    if (error?.status === 409 && await isAdminAccount()) {
                        writeAdminBrowserSession(email);
                        status.textContent = 'Acceso administrativo confirmado. Abriendo TinderCows…';
                        window.location.assign(resolveAdminNext(window.location.search));
                        return;
                    }
                    if (error?.status === 409) {
                        status.textContent = 'La cuenta está validada. Completa ahora tu registro guiado para crear tu perfil.';
                        window.location.assign(`registro.php?next=${encodeURIComponent(resolveNext(window.location.search))}`);
                        return;
                    }
                    try { await signOut(); } catch { clearAuthSession(); }
                    status.textContent = 'La sesión no pudo verificarse. Vuelve a iniciar sesión.';
                    return;
                }
                // Si MySQL/API está temporalmente indisponible conservamos la
                // sesión válida de Supabase para que el usuario pueda reintentar
                // sin crear otra sesión remota.
                status.textContent = error?.message || 'La sesión se inició, pero no pudimos cargar tu perfil. Intenta nuevamente.';
                return;
            }

            if (new URLSearchParams(window.location.search).get('area') === 'admin') {
                if (!await isAdminAccount()) {
                    status.textContent = 'La cuenta inició sesión, pero no tiene autorización administrativa.';
                    return;
                }
                writeAdminBrowserSession(email);
                status.textContent = 'Acceso administrativo confirmado. Abriendo TinderCows…';
                window.location.assign(resolveAdminNext(window.location.search));
                return;
            }

            status.textContent = 'Acceso confirmado. Abriendo TinderCows…';
            window.location.assign(resolveNext(window.location.search, Boolean(profile?.persona)));
        } catch (error) {
            status.textContent = error?.message || 'No fue posible iniciar sesión.';
        } finally {
            setBusy(form, submit, false);
        }
    });

    // Un perfil de una sesión anterior no autentica a nadie. Solo se conserva
    // como cache de interfaz; resolveNext lo usa después de validar el JWT.
    void readPublicProfile();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
