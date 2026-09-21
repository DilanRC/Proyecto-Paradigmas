import { request } from './shared/api.js';
import { BUSINESS_CAPABILITIES } from './shared/business-rules.js';
import { readAuthSession, signOut } from './shared/supabase-auth.js';
import { clearPublicProfile, syncPublicProfile } from './shared/public-profile.js';

const API_URL = 'api/v1/actividad';
let activityData = null;
const pendingChanges = new Set();

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
}

function ensureSession() {
    if (readAuthSession()) return true;
    window.location.assign('entrar?next=mi-actividad');
    return false;
}

function setView(view, message = '') {
    const loading = document.querySelector('#activity-loading');
    const error = document.querySelector('#activity-error');
    const content = document.querySelector('#activity-content');
    if (loading) loading.hidden = view !== 'loading';
    if (error) error.hidden = view !== 'error';
    if (content) content.hidden = view !== 'content';
    const errorMessage = document.querySelector('#activity-error-message');
    if (errorMessage && message) errorMessage.textContent = message;
}

function renderProfile(persona = {}) {
    const target = document.querySelector('#profile-list');
    if (!target) return;
    target.innerHTML = `
        <div><dt>Nombre</dt><dd>${escapeHtml(persona.nombre || 'Sin completar')}</dd></div>
        <div><dt>Alias</dt><dd>${escapeHtml(persona.alias || 'No definido')}</dd></div>
        <div><dt>Identificación</dt><dd>${escapeHtml(persona.identificacionNumero || 'Sin completar')}</dd></div>
        <div><dt>Teléfono</dt><dd>${escapeHtml(persona.telefono || 'Sin completar')}</dd></div>
        <div><dt>Correo</dt><dd>${escapeHtml(persona.correoElectronico || 'Sin completar')}</dd></div>`;
}

function toggleLabel(id, state) {
    if (state === 'ACTIVO') {
        return id === 'PRODUCTOR' ? 'Dejar de vender'
            : id === 'TRANSPORTISTA' ? 'Dejar de ofrecer fletes'
                : 'Desactivar';
    }
    return id === 'PRODUCTOR' ? 'Volver a vender'
        : id === 'TRANSPORTISTA' ? 'Volver a ofrecer fletes'
            : 'Reactivar';
}

function businessLink(id, detail) {
    if (detail?.estado !== 'ACTIVO') return '';
    const href = detail?.destinoActivo;
    if (!href) return '';
    const label = id === 'PRODUCTOR' ? 'Publicar ganado'
        : id === 'COMPRADOR' ? 'Explorar ganado'
            : 'Ver fletes';
    return `<a class="activity-button activity-button--primary" href="${escapeHtml(href)}">${escapeHtml(label)}</a>`;
}

function setupAction(id, detail) {
    const state = detail?.estado ?? 'NO_CONFIGURADO';
    if (state !== 'NO_CONFIGURADO') {
        if (detail?.escrituraDisponible !== true) {
            const explanation = detail?.motivoBloqueo
                ? `<p class="activity-note">${escapeHtml(detail.motivoBloqueo)}</p>`
                : '';
            return `${explanation}${businessLink(id, detail)}`;
        }
        const nextActive = state !== 'ACTIVO';
        const primary = nextActive ? ' activity-button--primary' : '';
        return `<button class="activity-button${primary}" type="button" data-toggle-capability="${escapeHtml(id)}" data-next-active="${nextActive}">${escapeHtml(toggleLabel(id, state))}</button>${businessLink(id, detail)}`;
    }

    const rutasRegistro = {
        PRODUCTOR: 'registro/productor',
        COMPRADOR: 'registro/comprador',
        TRANSPORTISTA: 'registro/transportista',
    };
    const next = detail?.destinoConfiguracion || `${rutasRegistro[id] ?? 'registro'}?next=mi-actividad`;
    return `<a class="activity-button activity-button--primary" href="${escapeHtml(next)}">Configurar</a>`;
}

function renderActivities(capacidades = {}) {
    const target = document.querySelector('#activity-list');
    if (!target) return;
    target.innerHTML = Object.entries(BUSINESS_CAPABILITIES).map(([id, capability]) => {
        const detail = capacidades[id] ?? { estado: 'NO_CONFIGURADO', escrituraDisponible: false };
        const state = detail.estado ?? 'NO_CONFIGURADO';
        return `<article class="activity-card">
            <div>
                <h3>${escapeHtml(capability.label)}</h3>
                <p>${escapeHtml(capability.description)}</p>
                <span class="activity-state" data-state="${escapeHtml(state)}">${state === 'NO_CONFIGURADO' ? 'Aún no configurado' : escapeHtml(state)}</span>
            </div>
            <div class="activity-actions">${setupAction(id, detail)}</div>
        </article>`;
    }).join('');

    target.querySelectorAll('[data-toggle-capability]').forEach((button) => {
        button.addEventListener('click', () => changeCapability(button));
    });
}

function render(data) {
    activityData = data;
    renderProfile(data?.persona ?? {});
    renderActivities(data?.capacidades ?? {});
    syncPublicProfile(data);
    setView('content');
}

async function loadActivity({ quiet = false } = {}) {
    if (!quiet) setView('loading');
    try {
        const response = await request(API_URL);
        render(response.data);
        return response.data;
    } catch (error) {
        if (error?.status === 401) {
            window.location.assign('entrar?next=mi-actividad');
            return null;
        }
        setView('error', error?.message || 'No fue posible consultar tu actividad.');
        return null;
    }
}

async function changeCapability(button) {
    const id = button.dataset.toggleCapability;
    const nextActive = button.dataset.nextActive === 'true';
    if (!id || pendingChanges.has(id)) return;

    pendingChanges.add(id);
    const status = document.querySelector('#activity-status');
    const content = document.querySelector('#activity-content');
    button.disabled = true;
    content?.setAttribute('aria-busy', 'true');
    if (status) status.textContent = nextActive ? 'Reactivando actividad…' : 'Desactivando actividad…';

    try {
        const response = await request(API_URL, {
            method: 'PATCH',
            body: JSON.stringify({ contexto: id, activo: nextActive }),
        });
        const refreshed = await loadActivity({ quiet: true });
        if (status && refreshed) {
            status.textContent = response.message || (nextActive
                ? 'Actividad reactivada correctamente.'
                : 'Actividad desactivada sin borrar tu identidad.');
        }
    } catch (error) {
        if (error?.status === 401) {
            window.location.assign('entrar?next=mi-actividad');
            return;
        }
        if (status) status.textContent = error?.message || 'No fue posible cambiar la actividad.';
        // El estado visible permanece exactamente como lo devolvió el último GET;
        // nunca anticipamos éxito ni mutamos el cache si PHP rechazó la transición.
        renderActivities(activityData?.capacidades ?? {});
    } finally {
        pendingChanges.delete(id);
        content?.setAttribute('aria-busy', 'false');
        const currentButton = document.querySelector(`[data-toggle-capability="${CSS.escape(id)}"]`);
        if (currentButton) currentButton.disabled = false;
    }
}

async function closeSession(button) {
    if (button.disabled) return;
    const status = document.querySelector('#activity-status');
    button.disabled = true;
    if (status) status.textContent = 'Cerrando sesión…';
    try {
        await signOut();
    } catch {
        // signOut limpia sessionStorage en finally aunque Supabase no responda.
    } finally {
        clearPublicProfile();
        window.location.assign('./');
    }
}

function initialize() {
    if (!ensureSession()) return;

    const params = new URLSearchParams(window.location.search);
    const welcome = document.querySelector('#welcome-banner');
    if (welcome && params.get('bienvenida') === '1') welcome.hidden = false;

    document.querySelector('#activity-retry')?.addEventListener('click', () => loadActivity());
    const logout = document.querySelector('#cerrar-sesion');
    logout?.addEventListener('click', () => closeSession(logout));
    loadActivity();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
