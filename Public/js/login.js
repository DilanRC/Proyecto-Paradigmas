import { request } from './shared/api.js?v=auth-gate-15';
import { getAccessToken, signInWithPassword, signOut } from './shared/supabase-auth.js?v=session-4';
import { readPublicProfile, syncPublicProfile } from './shared/public-profile.js?v=profile-2';
import { safeNext } from './shared/next.js?v=next-4';
import { CONFIRMACION_PENDIENTE_MESSAGE, completarRegistroPendiente, leerBorradorRegistro } from './shared/registro-pendiente.js?v=pendiente-4';
import { clearAdminBrowserSession, writeAdminBrowserSession } from './shared/auth-gate.js?v=auth-gate-15';
import { marcarAvisoDocumento } from './shared/aviso-documento.js?v=aviso-1';
import { ESTADO_CUENTA, estadoPorError } from './shared/estado-cuenta.js?v=estado-cuenta-1';

const ADMIN_DESTINATIONS = new Set(['admin/dashboard', 'admin/productores', 'admin/compradores', 'admin/transportistas', 'admin/vehiculos', 'admin/metodos-pago', 'admin/publicaciones', 'admin/administradores', 'admin/bitacora', 'admin/fletes', 'admin/documentos', 'admin/catalogos', 'admin/subastas']);

/** Después de entrar: el destino de origen si es seguro; si no, Explorar. */
export function resolveNext(search = '') {
    return safeNext(search) ?? 'explorar';
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

/**
 * Termina el alta con el borrador de esta pestaña (correo recién confirmado).
 * Si la Persona se guardó, nunca se vuelve a crear: un fallo al leer el perfil
 * después solo se deja para el encabezado, que lo confirma al llegar.
 */
async function terminarRegistroPendiente(email, status) {
    try {
        if (!await completarRegistroPendiente(email)) return false;
    } catch {
        // El borrador sigue en la pestaña: el formulario de registro lo recupera.
        return false;
    }
    marcarAvisoDocumento();
    setStatus(status, 'Cuenta confirmada. Terminando tu registro…', 'success');
    try { await loadBusinessProfile(); } catch { /* el registro ya quedó guardado */ }
    window.location.assign(resolveNext(window.location.search));
    return true;
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

    // Recién registrado con confirmación de correo pendiente.
    if (new URLSearchParams(window.location.search).get('registro') === 'confirmar') {
        setStatus(status, CONFIRMACION_PENDIENTE_MESSAGE, 'info');
        const correo = leerBorradorRegistro()?.persona?.correoElectronico;
        const campo = form.elements.namedItem('email');
        if (correo && campo instanceof HTMLInputElement && campo.value === '') campo.value = correo;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!validate(form) || submit.disabled) return;

        const data = new FormData(form);
        const email = String(data.get('email') ?? '').trim().toLowerCase();
        const password = String(data.get('password') ?? '');
        setBusy(form, submit, true);
        setStatus(status, 'Verificando credenciales…');

        try {
            clearAdminBrowserSession();
            await signInWithPassword(email, password);
            setStatus(status, 'Credenciales válidas. Vinculando tu identidad de TinderCows…');

            let profile;
            try {
                profile = await loadBusinessProfile();
            } catch (error) {
                // Misma clasificación que el encabezado (shared/estado-cuenta.js).
                const fallo = estadoPorError(error);
                if (fallo === ESTADO_CUENTA.REGISTRO_INCOMPLETO) {
                    // Correo recién confirmado: se termina el registro con los
                    // datos que la persona ya llenó. Va antes que el acceso
                    // admin: si no, un administrador que se registra como
                    // usuario terminaría siempre en el panel, sin perfil.
                    if (!isAdminLogin(window.location) && await terminarRegistroPendiente(email, status)) return;
                    if (await isAdminAccount()) {
                        writeAdminBrowserSession(email);
                        setStatus(status, 'Acceso administrativo confirmado. Abriendo TinderCows…', 'success');
                        window.location.assign(resolveAdminNext(window.location.search));
                        return;
                    }
                    setStatus(status, 'Tu cuenta está verificada. Completa ahora tu registro para crear tu perfil.', 'info');
                    window.location.assign(`registro?next=${encodeURIComponent(resolveNext(window.location.search))}`);
                    return;
                }
                if (fallo === ESTADO_CUENTA.ANONIMA || fallo === ESTADO_CUENTA.CONFLICTO) {
                    // La autorización admin es independiente del estado de la Persona.
                    if (fallo === ESTADO_CUENTA.CONFLICTO && await isAdminAccount()) {
                        writeAdminBrowserSession(email);
                        setStatus(status, 'Acceso administrativo confirmado. Abriendo TinderCows…', 'success');
                        window.location.assign(resolveAdminNext(window.location.search));
                        return;
                    }
                    // Una Persona inactiva o un correo ligado a dos Personas no se arregla
                    // llenando el registro otra vez: se avisa y no queda la sesión abierta.
                    try { await signOut(); } catch { /* signOut limpia solo la sesión que intentó revocar */ }
                    setStatus(status, fallo === ESTADO_CUENTA.CONFLICTO
                        ? `${error?.message || 'Tu cuenta tiene un conflicto.'} Escríbenos para revisarla.`
                        : 'La sesión no pudo verificarse. Vuelve a iniciar sesión.', 'error');
                    return;
                }
                // Si MySQL/API está temporalmente indisponible conservamos la
                // sesión válida de Supabase para que el usuario pueda reintentar
                // sin crear otra sesión remota. Un fallo temporal nunca se
                // interpreta como "no tiene Persona".
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
            window.location.assign(resolveNext(window.location.search));
        } catch (error) {
            if ((error?.status === 400 || error?.status === 401) && error?.code !== 'email_not_confirmed') {
                // Un solo aviso para correo y contraseña: no revela cuál de los dos falló (no se pueden enumerar cuentas).
                setStatus(status, 'El correo o la contraseña no coinciden. Revisa que estén bien escritos e inténtalo de nuevo.', 'error');
                form.elements.namedItem('password')?.focus();
                return;
            }
            setStatus(status, error?.message || 'No pudimos iniciar sesión. Revisa tu conexión e inténtalo de nuevo.', 'error');
        } finally {
            setBusy(form, submit, false);
        }
    });

    // Un perfil de una sesión anterior no autentica a nadie. Solo se conserva
    // como cache de interfaz; resolveNext lo usa después de validar el JWT.
    void readPublicProfile();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
