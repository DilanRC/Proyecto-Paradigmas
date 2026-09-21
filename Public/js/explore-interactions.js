import { getAccessToken, readAuthSession } from './shared/supabase-auth.js';

const API_URL = 'api/publicacion-interacciones.php';
const ACTION_TYPES = {
    'Me interesa': 'ME_INTERESA',
    Pasar: 'PASAR',
    Contactar: 'CONTACTAR',
};

function toast(message) {
    const target = document.querySelector('[data-explore-toast]');
    if (!target) return;
    target.textContent = message;
    target.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => { target.hidden = true; }, 2600);
}

function session() {
    return readAuthSession() ?? null;
}

function cardContext(button) {
    const card = button.closest('.explore-card');
    const publicacionId = Number(card?.dataset.publicacionId);
    return Number.isInteger(publicacionId) && publicacionId > 0 ? { card, publicacionId } : null;
}

async function save(button, type) {
    const context = cardContext(button);
    if (!context) {
        toast('No pudimos identificar esta publicación. Actualiza Explorar e inténtalo de nuevo.');
        return;
    }
    if (!session()) {
        window.location.assign(`login.php?next=${encodeURIComponent('explorar.php')}`);
        return;
    }

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    try {
        const token = await getAccessToken();
        if (!token) throw new Error('La sesión expiró. Entra de nuevo para continuar.');
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${token}`,
            },
            body: JSON.stringify({ publicacionId: context.publicacionId, tipo: type }),
        });
        let payload = null;
        try { payload = await response.json(); } catch { /* Se muestra un mensaje común. */ }
        if (!response.ok || payload?.success !== true) {
            throw new Error(payload?.message || 'No pudimos guardar tu acción.');
        }

        button.dataset.saved = 'true';
        button.setAttribute('aria-label', `${button.textContent.trim()} guardado`);
        toast(type === 'PASAR' ? 'Listo. Guardamos que pasaste esta publicación.' : 'Listo. Tu acción quedó guardada.');
        window.dispatchEvent(new CustomEvent('explore:interaction-saved', {
            detail: { type, card: context.card },
        }));
    } catch (error) {
        toast(error?.message || 'No pudimos guardar tu acción. Inténtalo de nuevo.');
    } finally {
        button.removeAttribute('aria-busy');
        button.disabled = false;
    }
}

function initialize() {
    document.addEventListener('click', (event) => {
        const button = event.target instanceof Element
            ? event.target.closest('[data-explore-action]')
            : null;
        if (!(button instanceof HTMLButtonElement)) return;
        const type = ACTION_TYPES[button.dataset.exploreAction];
        if (!type) return;
        event.preventDefault();
        void save(button, type);
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();
