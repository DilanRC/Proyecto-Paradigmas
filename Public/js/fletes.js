import { request } from './shared/api.js';
import { readAuthSession } from './shared/supabase-auth.js';

function renderState(stateBox, icon, message) {
    stateBox.replaceChildren();
    const iconNode = document.createElement('i');
    iconNode.className = icon;
    iconNode.setAttribute('aria-hidden', 'true');
    const copy = document.createElement('p');
    copy.textContent = message;
    stateBox.append(iconNode, copy);
}

function stateFromActivity(payload) {
    return payload?.data?.capacidades?.TRANSPORTISTA?.estado ?? 'NO_CONFIGURADO';
}

async function initialize() {
    const stateBox = document.querySelector('#fletes-state');
    const primary = document.querySelector('#fletes-primary');
    if (!stateBox || !(primary instanceof HTMLAnchorElement)) return;

    const session = readAuthSession();

    if (!session) {
        renderState(stateBox, 'fa-solid fa-circle-info', 'Inicia sesión o crea una cuenta para ofrecer fletes.');
        primary.href = 'entrar?next=fletes';
        primary.textContent = 'Entrar para continuar';
        return;
    }

    let state;
    try {
        state = stateFromActivity(await request('api/v1/actividad'));
    } catch {
        renderState(stateBox, 'fa-solid fa-triangle-exclamation', 'No pudimos comprobar el estado de tu servicio. Revisa Mi actividad e inténtalo nuevamente.');
        primary.href = 'mi-actividad';
        primary.textContent = 'Abrir Mi actividad';
        return;
    }

    if (state === 'ACTIVO') {
        renderState(stateBox, 'fa-solid fa-circle-check', 'Tu servicio de fletes está activo. Puedes actualizarlo desde Mi actividad.');
        primary.href = 'mi-actividad';
        primary.textContent = 'Administrar mi servicio';
        return;
    }

    if (state === 'INACTIVO') {
        renderState(stateBox, 'fa-solid fa-pause', 'Tu servicio de fletes está inactivo. Tu identidad y datos se conservan para poder reactivarlo.');
        primary.href = 'mi-actividad';
        primary.textContent = 'Reactivar servicio';
        return;
    }

    renderState(stateBox, 'fa-solid fa-circle-info', 'Aún no ofreces fletes. Completa esta actividad y conserva los datos que ya registraste.');
    primary.href = 'registro/transportista';
    primary.textContent = 'Quiero ofrecer fletes';
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
