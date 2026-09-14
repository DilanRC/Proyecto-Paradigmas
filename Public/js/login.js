import { request } from './shared/api.js';
import { clearAuthSession, signInWithPassword, signOut } from './shared/supabase-auth.js';
import { readPublicProfile, syncPublicProfile } from './shared/public-profile.js';

const PUBLIC_DESTINATIONS = new Set(['explorar.php', 'mi-actividad.php', 'fletes.php', 'publicar.php']);

export function resolveNext(search = '', hasProfile = false) {
    const requested = new URLSearchParams(search).get('next');
    if (requested && PUBLIC_DESTINATIONS.has(requested)) return requested;
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

function setBusy(form, button, busy) {
    form.setAttribute('aria-busy', String(busy));
    button.disabled = busy;
}

async function loadBusinessProfile() {
    const response = await request('api/mi-actividad.php');
    return syncPublicProfile(response.data);
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
            await signInWithPassword(email, password);
            status.textContent = 'Credenciales válidas. Vinculando tu identidad de TinderCows…';

            let profile;
            try {
                profile = await loadBusinessProfile();
            } catch (error) {
                if (error?.status === 401 || error?.status === 409) {
                    try { await signOut(); } catch { clearAuthSession(); }
                    status.textContent = error?.status === 409
                        ? 'La cuenta existe en autenticación, pero todavía no está vinculada a una Persona de TinderCows.'
                        : 'La sesión no pudo verificarse. Vuelve a iniciar sesión.';
                    return;
                }
                // Si MySQL/API está temporalmente indisponible conservamos la
                // sesión válida de Supabase para que el usuario pueda reintentar
                // sin crear otra sesión remota.
                status.textContent = error?.message || 'La sesión se inició, pero no pudimos cargar tu perfil. Intenta nuevamente.';
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
