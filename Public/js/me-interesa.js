// Me interesa: las publicaciones que la persona marcó, con la tarjeta compacta de la
// portada (buildCard con compacta) y un botón "Quitar" (acción RETIRAR, idempotente).

import { request } from './shared/api.js';
import { endExpiredSession, readAuthSession } from './shared/supabase-auth.js?v=session-3';
import { buildCard } from './explore.js?v=foto-3';
import { createToast } from './shared/toast.js';

const API = 'api/v1/publicaciones/interacciones';
let toast = null;

function setView(view, message = '') {
    document.querySelector('#saved-loading').hidden = view !== 'loading';
    document.querySelector('#saved-error').hidden = view !== 'error';
    document.querySelector('#saved-list').hidden = view !== 'content';
    document.querySelector('#saved-empty').hidden = view !== 'empty';
    const text = document.querySelector('#saved-error-message');
    if (text && message) text.textContent = message;
}

function refreshEmpty() {
    if (document.querySelector('#saved-list').children.length === 0) setView('empty');
}

async function removeSaved(publicacionId, card, button) {
    button.disabled = true;
    try {
        await request(API, { method: 'POST', body: JSON.stringify({ publicacionId, tipo: 'ME_INTERESA', accion: 'RETIRAR' }) });
        card.remove();
        toast?.success('Quitamos la publicación de tu lista.');
        refreshEmpty();
    } catch (error) {
        button.disabled = false;
        toast?.error(error?.message || 'No pudimos quitar la publicación.');
    }
}

/** Misma tarjeta compacta de la portada ("Ver más información"), con Quitar en vez de acciones. */
export function savedCard(publicacion) {
    const card = buildCard(publicacion, { compacta: true });
    const body = card.querySelector('.explore-card__body');
    if (publicacion.estado !== 'ACTIVO') {
        const badge = document.createElement('span');
        badge.className = 'explore-card__type';
        badge.textContent = 'No disponible';
        body?.prepend(badge);
    }
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.innerHTML = '<i class="fa-solid fa-heart-crack" aria-hidden="true"></i><span>Quitar de Me interesa</span>';
    remove.addEventListener('click', () => removeSaved(Number(publicacion.publicacionId), card, remove));
    const actions = document.createElement('div');
    actions.className = 'explore-card__actions';
    actions.append(remove);
    body?.append(actions);
    return card;
}

async function load() {
    setView('loading');
    try {
        const response = await request(`${API}?tipo=ME_INTERESA&pagina=1&tamanoPagina=100`);
        const items = response.data?.publicaciones ?? [];
        document.querySelector('#saved-list').replaceChildren(...items.map(savedCard));
        setView(items.length ? 'content' : 'empty');
    } catch (error) {
        if (error?.status === 401) endExpiredSession();
        else setView('error', error?.message || 'No pudimos cargar tus publicaciones.');
    }
}

function initialize() {
    if (!readAuthSession()) {
        window.location.assign('entrar?next=me-interesa');
        return;
    }
    toast = createToast({ polite: document.querySelector('#toast-status'), assertive: document.querySelector('#toast-alert') });
    document.querySelector('#saved-retry')?.addEventListener('click', load);
    load();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
