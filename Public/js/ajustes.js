// Ajustes de cuenta: perfil (solo lectura, con datos sensibles enmascarados)
// y "Cómo participo" (activar/desactivar actividades). Usa los mismos
// endpoints que antes vivían en Mi actividad; aquí solo cambió el lugar.

import { request } from './shared/api.js';
import { BUSINESS_CAPABILITIES } from './shared/business-rules.js?v=panel-2';
import { endExpiredSession, readAuthSession } from './shared/supabase-auth.js?v=session-2';
import { syncPublicProfile } from './shared/public-profile.js';
import { createToast } from './shared/toast.js';
import { subirImagenPublicacion, validarImagen } from './shared/storage.js';
import { safeImageUrl } from './explore.js?v=foto-3';

const ACTIVITY_API = 'api/v1/actividad';
const REGISTRO_API = 'api/v1/registro';
const PERFIL_API = 'api/v1/mi-perfil';
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

/** Solo lo que cambió: el API no toca lo que no se envía. */
export function cambiosPerfil(valores = {}, persona = {}) {
    const cambios = {};
    const alias = String(valores.alias ?? '').trim();
    if (alias !== String(persona.alias ?? '')) cambios.alias = alias === '' ? null : alias;
    const telefono = String(valores.telefono ?? '').trim();
    if (telefono !== String(persona.telefono ?? '')) cambios.telefono = telefono;
    return cambios;
}

function renderAvatar(persona = {}) {
    const avatar = document.querySelector('#profile-avatar');
    const foto = safeImageUrl(persona.fotoUrl);
    const inicial = String(persona.nombre ?? '').trim().charAt(0).toUpperCase() || 'U';
    avatar.replaceChildren();
    if (foto) {
        const img = document.createElement('img');
        img.src = foto;
        img.alt = '';
        img.referrerPolicy = 'no-referrer';
        // Si la imagen ya no existe, vuelve la inicial en vez de un recuadro roto.
        img.addEventListener('error', () => { avatar.textContent = inicial; }, { once: true });
        avatar.append(img);
    } else {
        avatar.textContent = inicial;
    }
    document.querySelector('#profile-photo-remove').hidden = !foto;
}

async function patchPerfil(cuerpo) {
    const response = await request(PERFIL_API, { method: 'PATCH', body: JSON.stringify(cuerpo) });
    activityData = { ...activityData, persona: { ...activityData.persona, ...response.data.persona } };
    renderProfile(activityData.persona);
    syncPublicProfile(activityData);
    return response;
}

async function changePhoto(event) {
    const archivo = event.target.files?.[0];
    event.target.value = '';
    const status = document.querySelector('#profile-photo-status');
    const problema = archivo ? validarImagen(archivo) : null;
    if (!archivo || problema) { if (problema) status.textContent = problema; return; }
    const botones = document.querySelectorAll('#profile-photo-change, #profile-photo-remove');
    botones.forEach((boton) => { boton.disabled = true; });
    status.textContent = 'Subiendo foto…';
    try {
        const url = await subirImagenPublicacion(archivo);
        await patchPerfil({ fotoUrl: url });
        status.textContent = 'Foto actualizada. Se verá en el encabezado al recargar.';
        toast?.success('Foto de perfil actualizada.');
    } catch (error) {
        status.textContent = error?.errors?.fotoUrl || error?.message || 'No pudimos cambiar la foto.';
        toast?.error(status.textContent);
    } finally {
        botones.forEach((boton) => { boton.disabled = false; });
    }
}

async function removePhoto() {
    const status = document.querySelector('#profile-photo-status');
    try {
        await patchPerfil({ fotoUrl: null });
        status.textContent = 'Quitamos tu foto.';
        toast?.success('Foto de perfil quitada.');
    } catch (error) {
        status.textContent = error?.message || 'No pudimos quitar la foto.';
        toast?.error(status.textContent);
    }
}

function setProfileErrors(errors = {}) {
    document.querySelectorAll('[data-profile-error]').forEach((node) => { node.textContent = ''; });
    document.querySelectorAll('#profile-form [aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
    for (const [campo, mensaje] of Object.entries(errors)) {
        const nodo = document.querySelector(`[data-profile-error="${campo}"]`);
        const control = document.querySelector(`#profile-form [name="${campo}"]`);
        if (nodo) nodo.textContent = String(mensaje);
        if (control) control.setAttribute('aria-invalid', 'true');
    }
}

function toggleProfileForm(abrir) {
    const form = document.querySelector('#profile-form');
    form.hidden = !abrir;
    document.querySelector('#profile-edit').hidden = abrir;
    if (!abrir) return;
    form.elements.alias.value = activityData?.persona?.alias ?? '';
    form.elements.telefono.value = activityData?.persona?.telefono ?? '';
    setProfileErrors({});
    document.querySelector('#profile-form-status').textContent = '';
    form.elements.alias.focus();
}

async function saveProfile(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const status = document.querySelector('#profile-form-status');
    const guardar = document.querySelector('#profile-save');
    const cambios = cambiosPerfil(Object.fromEntries(new FormData(form).entries()), activityData?.persona);
    if (Object.keys(cambios).length === 0) { toggleProfileForm(false); return; }
    setProfileErrors({});
    guardar.disabled = true;
    status.textContent = 'Guardando…';
    try {
        const response = await patchPerfil(cambios);
        toggleProfileForm(false);
        toast?.success(response.message || 'Perfil actualizado.');
    } catch (error) {
        setProfileErrors(error?.errors ?? {});
        status.textContent = error?.message || 'No pudimos guardar tus datos.';
    } finally {
        guardar.disabled = false;
    }
}

function initializeProfileUi() {
    document.querySelector('#profile-edit')?.addEventListener('click', () => toggleProfileForm(true));
    document.querySelector('#profile-cancel')?.addEventListener('click', () => toggleProfileForm(false));
    document.querySelector('#profile-form')?.addEventListener('submit', saveProfile);
    document.querySelector('#profile-photo-change')?.addEventListener('click', () => document.querySelector('#profile-photo-file').click());
    document.querySelector('#profile-photo-file')?.addEventListener('change', changePhoto);
    document.querySelector('#profile-photo-remove')?.addEventListener('click', removePhoto);
}

function renderProfile(persona = {}) {
    renderAvatar(persona);
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

/**
 * Vendedor necesita fincas: "Configurar" abre solo ese formulario. Comprador y
 * Transportista no piden datos extra: se activan con el interruptor.
 */
export function necesitaFormulario(id) {
    return id === 'PRODUCTOR';
}

function activityControl(id, detail) {
    const state = detail?.estado ?? 'NO_CONFIGURADO';
    if (state === 'NO_CONFIGURADO' && necesitaFormulario(id)) {
        return `<a class="activity-button activity-button--sm" href="registro/productor?next=ajustes">Configurar</a>`;
    }
    if (state === 'NO_CONFIGURADO') {
        return `<input class="settings-switch" type="checkbox" role="switch" data-toggle-capability="${id}" data-configurar="true" aria-labelledby="cap-${id}-title" aria-describedby="cap-${id}-desc">`;
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

export function mensajeActivacion(id, activa, mensajeServidor = '') {
    if (activa && id === 'TRANSPORTISTA') return 'Listo: ya ofreces fletes. Agrega tu vehículo desde Mi panel.';
    if (activa && id === 'COMPRADOR') return 'Listo: ya puedes explorar y guardar ganado.';
    return mensajeServidor || (activa ? 'Actividad activada correctamente.' : 'Actividad desactivada correctamente.');
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
        // Primera activación de Transportista: la misma ampliación de cuenta que
        // hacía el formulario de registro, sin datos extra que pedir.
        const response = input.dataset.configurar === 'true' && id === 'TRANSPORTISTA'
            ? await request(REGISTRO_API, { method: 'POST', body: JSON.stringify({ capacidades: [id], fincas: [] }) })
            : await request(ACTIVITY_API, { method: 'PATCH', body: JSON.stringify({ contexto: id, activo: nextActive }) });
        const refreshed = await loadActivity({ quiet: true });
        if (refreshed) {
            const mensaje = mensajeActivacion(id, nextActive, response.message);
            status.textContent = mensaje;
            toast?.success(mensaje);
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
    initializeProfileUi();
    loadActivity().then(() => {
        // El contenido aparece después de cargar: recién ahí existe el ancla.
        const destino = document.getElementById(window.location.hash.slice(1));
        destino?.scrollIntoView({ block: 'start' });
    });
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
