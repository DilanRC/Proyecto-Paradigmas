import { BUSINESS_CAPABILITIES } from './shared/business-rules.js';

const PROFILE_KEY = 'tindercows:profile';
const SESSION_KEY = 'tindercows:login';

function readProfile() {
    try { return JSON.parse(sessionStorage.getItem(PROFILE_KEY) || 'null'); } catch { return null; }
}

function writeProfile(profile) {
    sessionStorage.setItem(PROFILE_KEY, JSON.stringify(profile));
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
}

function ensureSession() {
    try {
        const session = JSON.parse(sessionStorage.getItem(SESSION_KEY) || 'null');
        if (session?.authenticated === true) return true;
    } catch {}
    window.location.assign('login.php?next=mi-actividad.php');
    return false;
}

function renderProfile(profile) {
    const target = document.querySelector('#profile-list');
    if (!target) return;
    const persona = profile?.persona ?? {};
    target.innerHTML = `
        <div><dt>Nombre</dt><dd>${escapeHtml(persona.nombre || 'Sin completar')}</dd></div>
        <div><dt>Alias</dt><dd>${escapeHtml(persona.alias || 'No definido')}</dd></div>
        <div><dt>Identificación</dt><dd>${escapeHtml(persona.identificacionNumero || 'Sin completar')}</dd></div>
        <div><dt>Teléfono</dt><dd>${escapeHtml(persona.telefono || 'Sin completar')}</dd></div>
        <div><dt>Correo</dt><dd>${escapeHtml(persona.correoElectronico || 'Sin completar')}</dd></div>`;
}

function actionFor(capability, state) {
    if (state === 'ACTIVO') {
        return `<button class="activity-button" type="button" data-toggle-capability="${capability}" data-next-state="INACTIVO">Dejar de ${capability === 'PRODUCTOR' ? 'vender' : capability === 'COMPRADOR' ? 'comprar' : 'ofrecer fletes'}</button>`;
    }
    return `<button class="activity-button activity-button--primary" type="button" data-toggle-capability="${capability}" data-next-state="ACTIVO">${capability === 'PRODUCTOR' ? 'Volver a vender' : capability === 'COMPRADOR' ? 'Volver a comprar' : 'Volver a ofrecer fletes'}</button>`;
}

function businessLink(id, state) {
    if (state !== 'ACTIVO') return '';
    if (id === 'PRODUCTOR') return '<a class="activity-button activity-button--primary" href="publicar.php">Publicar ganado</a>';
    if (id === 'COMPRADOR') return '<a class="activity-button activity-button--primary" href="explorar.php">Explorar ganado</a>';
    if (id === 'TRANSPORTISTA') return '<a class="activity-button activity-button--primary" href="fletes.php">Ver fletes</a>';
    return '';
}

function renderActivities(profile) {
    const target = document.querySelector('#activity-list');
    if (!target) return;
    const states = profile?.capacidadesEstado ?? {};
    target.innerHTML = Object.entries(BUSINESS_CAPABILITIES).map(([id, capability]) => {
        const state = states[id] ?? 'NO_CONFIGURADO';
        const configured = state !== 'NO_CONFIGURADO';
        return `<article class="activity-card">
            <div>
                <h3>${escapeHtml(capability.label)}</h3>
                <p>${escapeHtml(capability.description)}</p>
                <span class="activity-state" data-state="${escapeHtml(state)}">${configured ? escapeHtml(state) : 'Aún no configurado'}</span>
            </div>
            <div class="activity-actions">
                ${configured ? actionFor(id, state) : `<a class="activity-button activity-button--primary" href="registro.php?capacidad=${id}&next=mi-actividad.php">Configurar</a>`}
                ${businessLink(id, state)}
            </div>
        </article>`;
    }).join('');

    target.querySelectorAll('[data-toggle-capability]').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.dataset.toggleCapability;
            const nextState = button.dataset.nextState;
            profile.capacidadesEstado = { ...(profile.capacidadesEstado ?? {}), [id]: nextState };
            if (!Array.isArray(profile.capacidades)) profile.capacidades = [];
            if (!profile.capacidades.includes(id)) profile.capacidades.push(id);
            writeProfile(profile);
            renderActivities(profile);
            const status = document.querySelector('#activity-status');
            if (status) status.textContent = nextState === 'ACTIVO' ? 'Actividad reactivada.' : 'Actividad desactivada sin borrar tu identidad.';
        });
    });
}

function initialize() {
    if (!ensureSession()) return;
    const profile = readProfile();
    if (!profile) {
        window.location.assign('registro.php');
        return;
    }

    renderProfile(profile);
    renderActivities(profile);

    const params = new URLSearchParams(window.location.search);
    const welcome = document.querySelector('#welcome-banner');
    if (welcome && params.get('bienvenida') === '1') welcome.hidden = false;

    document.querySelector('#cerrar-sesion')?.addEventListener('click', () => {
        sessionStorage.removeItem(SESSION_KEY);
        window.location.assign('./');
    });
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
