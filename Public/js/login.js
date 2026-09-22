import { request } from './shared/api.js?v=api-2';
import { clearAuthSession, getAccessToken, signInWithPassword, signOut } from './shared/supabase-auth.js';
import { readPublicProfile, syncPublicProfile } from './shared/public-profile.js';
import { clearAdminBrowserSession, writeAdminBrowserSession } from './shared/auth-gate.js?v=auth-gate-2';

const PUBLIC_DESTINATIONS = new Set(['explorar', 'mi-actividad', 'fletes', 'publicar']);
const ADMIN_DESTINATIONS = new Set(['admin/dashboard', 'admin/productores', 'admin/compradores', 'admin/transportistas', 'admin/vehiculos', 'admin/metodos-pago']);

export function resolveNext(search = '', hasProfile = false) {
    const requested = new URLSearchParams(search).get('next');
    if (requested && PUBLIC_DESTINATIONS.has(requested)) return requested;
    return hasProfile ? 'mi-actividad' : 'explorar';
}

export function resolveAdminNext(search = '') {
    const requested = new URLSearchParams(search).get('next');
    return requested && ADMIN_DESTINATIONS.has(requested) ? requested : 'admin/dashboard';
}

export function isAdminLogin(location = globalThis.location) {
    const pathname = String(location?.pathname ?? '').replace(/\/+$/, '');
    return pathname.endsWith('/admin/entrar')
        || new URLSearchParams(location?.search ?? '').get('area') === 'admin';
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
            if (control.type === 'email') message = control.value ? 'Ingrese un correo válido.' : 'El correo electrónico es obligatorio.';
            else message = control.value ? 'Use al menos 8 caracteres.' : 'La contraseña es obligatoria.';
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

function setStatus(status, message, kind = 'info') {
    status.textContent = message;
    status.dataset.status = message ? kind : '';
}

async function loadBusinessProfile() {
    const response = await request('api/v1/actividad');
    return syncPublicProfile(response.data);
}

async function isAdminAccount() {
    try {
        const token = await getAccessToken();
        if (!token) return false;
        const response = await fetch('api/v1/admin/status', {
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
        setStatus(status, '');
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!validate(form) || submit.disabled) return;

        const data = new FormData(form);
        const email = String(data.get('email') ?? '').trim().toLowerCase();
        const password = String(data.get('password') ?? '');
        setBusy(form, submit, true);
        setStatus(status, 'Verificando credenciales…');

        try {
            clearAuthSession();
            clearAdminBrowserSession();
            await signInWithPassword(email, password);
            setStatus(status, 'Credenciales válidas. Vinculando tu identidad de TinderCows…');

            let profile;
            try {
                profile = await loadBusinessProfile();
            } catch (error) {
                if (error?.status === 401 || error?.status === 409) {
                    if (error?.status === 409 && await isAdminAccount()) {
                        writeAdminBrowserSession(email);
                        setStatus(status, 'Acceso administrativo confirmado. Abriendo TinderCows…', 'success');
                        window.location.assign(resolveAdminNext(window.location.search));
                        return;
                    }
                    if (error?.status === 409) {
                        setStatus(status, 'La cuenta está validada. Completa ahora tu registro guiado para crear tu perfil.', 'info');
                        window.location.assign(`registro?next=${encodeURIComponent(resolveNext(window.location.search))}`);
                        return;
                    }
                    try { await signOut(); } catch { clearAuthSession(); }
                    setStatus(status, 'La sesión no pudo verificarse. Vuelve a iniciar sesión.', 'error');
                    return;
                }
                // Si MySQL/API está temporalmente indisponible conservamos la
                // sesión válida de Supabase para que el usuario pueda reintentar
                // sin crear otra sesión remota.
                setStatus(status, error?.message || 'La sesión se inició, pero no pudimos cargar tu perfil. Intenta nuevamente.', 'error');
                return;
            }

            if (isAdminLogin(window.location)) {
                if (!await isAdminAccount()) {
                    setStatus(status, 'La cuenta inició sesión, pero no tiene autorización administrativa.', 'error');
                    return;
                }
                writeAdminBrowserSession(email);
                setStatus(status, 'Acceso administrativo confirmado. Abriendo TinderCows…', 'success');
                window.location.assign(resolveAdminNext(window.location.search));
                return;
            }

            setStatus(status, 'Acceso confirmado. Abriendo TinderCows…', 'success');
            window.location.assign(resolveNext(window.location.search, Boolean(profile?.persona)));
        } catch (error) {
            setStatus(status, error?.message || 'No fue posible iniciar sesión. Revise sus datos e intente nuevamente.', 'error');
        } finally {
            setBusy(form, submit, false);
        }
    });

    // Un perfil de una sesión anterior no autentica a nadie. Solo se conserva
    // como cache de interfaz; resolveNext lo usa después de validar el JWT.
    void readPublicProfile();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
