const PROFILE_KEY = 'tindercows:profile';
const SESSION_KEY = 'tindercows:login';

function read(key) {
    try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return null; }
}

function initialize() {
    const stateBox = document.querySelector('#fletes-state');
    const primary = document.querySelector('#fletes-primary');
    if (!stateBox || !(primary instanceof HTMLAnchorElement)) return;

    const profile = read(PROFILE_KEY);
    const session = read(SESSION_KEY);
    const state = profile?.capacidadesEstado?.TRANSPORTISTA ?? 'NO_CONFIGURADO';

    if (!session?.authenticated) {
        stateBox.innerHTML = '<i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Inicia sesión o crea una cuenta para ofrecer fletes.</p>';
        primary.href = profile ? 'entrar?next=fletes' : 'registro/transportista';
        primary.textContent = profile ? 'Entrar para continuar' : 'Crear cuenta y ofrecer fletes';
        return;
    }

    if (state === 'ACTIVO') {
        stateBox.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i><p>Actualmente ofreces fletes. Puedes administrar o desactivar esta actividad desde Mi actividad.</p>';
        primary.href = 'mi-actividad';
        primary.textContent = 'Administrar mi servicio';
        return;
    }

    if (state === 'INACTIVO') {
        stateBox.innerHTML = '<i class="fa-solid fa-pause" aria-hidden="true"></i><p>Tu servicio de fletes está inactivo. Tu identidad y datos se conservan para poder reactivarlo.</p>';
        primary.href = 'mi-actividad';
        primary.textContent = 'Reactivar servicio';
        return;
    }

    stateBox.innerHTML = '<i class="fa-solid fa-circle-info" aria-hidden="true"></i><p>Aún no ofreces fletes. Podemos configurar esta actividad sin volver a registrar tu identidad.</p>';
    primary.href = 'registro/transportista';
    primary.textContent = 'Quiero ofrecer fletes';
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
