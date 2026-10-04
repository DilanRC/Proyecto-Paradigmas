// Ajustes de cuenta: perfil (solo lectura, con datos sensibles enmascarados)
// y "Cómo participo" (activar/desactivar actividades). Usa los mismos
// endpoints que antes vivían en Mi actividad; aquí solo cambió el lugar.

import { request } from './shared/api.js';
import { BUSINESS_CAPABILITIES } from './shared/business-rules.js?v=panel-2';
import { endExpiredSession, readAuthSession } from './shared/supabase-auth.js?v=session-2';
import { syncPublicProfile } from './shared/public-profile.js';
import { createToast } from './shared/toast.js';

const ACTIVITY_API = 'api/v1/actividad';
let activityData = null;
const pendingChanges = new Set();
let toast = null;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
}

/** "112340817" → "•••• 0817". Lo vacío no se enmascara: se dice que falta. */
export function maskTail(value, visibles = 4) {
    const texto = String(value ?? '').trim();
    if (texto === '') return 'Sin completar';
    const alnum = texto.replace(/[^0-9A-Za-z]/g, '');
    return `•••• ${alnum.slice(-visibles)}`;
}

function ensureSession() {
    if (readAuthSession()) return true;
    window.location.assign('entrar?next=ajustes');
    return false;
}

function setView(view, message = '') {
    document.querySelector('#activity-loading').hidden = view !== 'loading';
    document.querySelector('#activity-error').hidden = view !== 'error';
    document.querySelector('#activity-content').hidden = view !== 'content';
    const errorMessage = document.querySelector('#activity-error-message');
    if (errorMessage && message) errorMessage.textContent = message;
}

function sensitiveRow(label, value, key) {
    const real = String(value ?? '').trim();
    if (real === '') return `<div><dt>${label}</dt><dd>Sin completar</dd></div>`;
    return `<div><dt>${label}</dt><dd><span data-sensitive="${key}" data-masked="${escapeHtml(maskTail(real))}" data-real="${escapeHtml(real)}">${escapeHtml(maskTail(real))}</span><button class="activity-button activity-button--text" type="button" data-reveal="${key}" aria-pressed="false" aria-label="Mostrar ${label.toLowerCase()}">Mostrar</button></dd></div>`;
}

function renderProfile(persona = {}) {
    const target = document.querySelector('#profile-list');
    target.innerHTML = `
        <div><dt>Nombre</dt><dd>${escapeHtml(persona.nombre || 'Sin completar')}</dd></div>
        <div><dt>Alias</dt><dd>${escapeHtml(persona.alias || 'No definido')}</dd></div>
        ${sensitiveRow('Identificación', persona.identificacionNumero, 'identificacion')}
        ${sensitiveRow('Teléfono', persona.telefono, 'telefono')}
        <div><dt>Correo</dt><dd>${escapeHtml(persona.correoElectronico || 'Sin completar')}</dd></div>`;
    target.querySelectorAll('[data-reveal]').forEach((button) => button.addEventListener('click', () => {
        const valor = target.querySelector(`[data-sensitive="${button.dataset.reveal}"]`);
        const mostrar = button.getAttribute('aria-pressed') !== 'true';
        valor.textContent = mostrar ? valor.dataset.real : valor.dataset.masked;
        button.setAttribute('aria-pressed', String(mostrar));
        button.textContent = mostrar ? 'Ocultar' : 'Mostrar';
    }));
}

function activityControl(id, detail) {
    const state = detail?.estado ?? 'NO_CONFIGURADO';
    if (state === 'NO_CONFIGURADO') {
        return `<a class="activity-button activity-button--sm" href="${escapeHtml(detail?.destinoConfiguracion || `registro?capacidad=${id}&next=ajustes`)}">Configurar</a>`;
    }
    if (detail?.escrituraDisponible !== true) {
        return `<p class="settings-hint">${escapeHtml(detail?.motivoBloqueo || 'La actividad está administrada por el sistema.')}</p>`;
    }
    return `<input class="settings-switch" type="checkbox" role="switch" data-toggle-capability="${id}" aria-labelledby="cap-${id}-title" aria-describedby="cap-${id}-desc"${state === 'ACTIVO' ? ' checked' : ''}>`;
}

function renderActivities(capacidades = {}) {
    const target = document.querySelector('#activity-list');
    target.innerHTML = Object.entries(BUSINESS_CAPABILITIES).map(([id, capability]) => {
        const detail = capacidades[id] ?? { estado: 'NO_CONFIGURADO', escrituraDisponible: false };
        const estado = detail.estado === 'ACTIVO' ? 'Activa' : detail.estado === 'INACTIVO' ? 'Inactiva' : 'Sin configurar';
        return `<div class="settings-toggle"><div><h3 id="cap-${id}-title">${capability.shortLabel}</h3><p id="cap-${id}-desc">${capability.description} <span class="settings-state">${estado}</span></p></div>${activityControl(id, detail)}</div>`;
    }).join('');
    target.querySelectorAll('[data-toggle-capability]').forEach((input) => input.addEventListener('change', () => changeCapability(input)));
}

async function loadActivity({ quiet = false } = {}) {
    if (!quiet) setView('loading');
    try {
        const response = await request(ACTIVITY_API);
        activityData = response.data;
        renderProfile(activityData?.persona ?? {});
        renderActivities(activityData?.capacidades ?? {});
        syncPublicProfile(activityData);
        setView('content');
        return activityData;
    } catch (error) {
        if (error?.status === 401) endExpiredSession();
        else setView('error', error?.message || 'No fue posible consultar tus ajustes.');
        return null;
    }
}

async function changeCapability(input) {
    const id = input.dataset.toggleCapability;
    const nextActive = input.checked;
    const nombre = BUSINESS_CAPABILITIES[id]?.shortLabel ?? 'esta actividad';
    if (!id || pendingChanges.has(id)) return;
    if (!nextActive && !window.confirm(`¿Desactivar ${nombre}? Tus otros datos no se borran y puedes volver a activarla.`)) {
        input.checked = true;
        return;
    }
    pendingChanges.add(id);
    input.disabled = true;
    const status = document.querySelector('#activity-status');
    status.textContent = nextActive ? 'Reactivando actividad…' : 'Desactivando actividad…';
    try {
        const response = await request(ACTIVITY_API, { method: 'PATCH', body: JSON.stringify({ contexto: id, activo: nextActive }) });
        const refreshed = await loadActivity({ quiet: true });
        if (refreshed) {
            status.textContent = response.message || 'Actividad actualizada correctamente.';
            toast?.success(response.message || 'Actividad actualizada correctamente.');
        }
    } catch (error) {
        status.textContent = error?.message || 'No fue posible cambiar la actividad.';
        toast?.error(error?.message || 'No fue posible cambiar la actividad.');
        renderActivities(activityData?.capacidades ?? {});
    } finally {
        pendingChanges.delete(id);
        const current = document.querySelector(`[data-toggle-capability="${id}"]`);
        if (current) current.disabled = false;
    }
}

/** Marca en el menú lateral la sección del hash (#perfil por defecto). */
function markCurrentSection() {
    const actual = window.location.hash.replace('#', '') || 'perfil';
    document.querySelectorAll('[data-settings-link]').forEach((link) => {
        if (link.dataset.settingsLink === actual) link.setAttribute('aria-current', 'location');
        else link.removeAttribute('aria-current');
    });
}

function initialize() {
    if (!ensureSession()) return;
    toast = createToast({ polite: document.querySelector('#toast-status'), assertive: document.querySelector('#toast-alert') });
    document.querySelector('#activity-retry')?.addEventListener('click', () => loadActivity());
    window.addEventListener('hashchange', markCurrentSection);
    markCurrentSection();
    loadActivity().then(() => {
        // El contenido aparece después de cargar: recién ahí existe el ancla.
        const destino = document.getElementById(window.location.hash.slice(1));
        destino?.scrollIntoView({ block: 'start' });
    });
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
