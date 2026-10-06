import { request } from './shared/api.js';
import { BUSINESS_CAPABILITIES } from './shared/business-rules.js?v=panel-2';
import { endExpiredSession, readAuthSession } from './shared/supabase-auth.js?v=session-2';
import { syncPublicProfile } from './shared/public-profile.js';
import { safeImageUrl } from './explore.js?v=foto-3';
import { montarCampoFoto } from './shared/foto-campo.js?v=foto-campo-1';
import { createToast } from './shared/toast.js';
import { conectarDireccion } from './shared/direccion.js';
import { buscarDireccionPorCoordenadas, crearSelectorPuntoFinca } from './shared/finca-mapa.js';

const ACTIVITY_API = 'api/v1/actividad';
const VEHICLES_API = 'api/v1/mi-vehiculos';
const FARMS_API = 'api/v1/mi-fincas';
const PUBLICATIONS_API = 'api/v1/publicaciones';
const SOLICITUDES_API = 'api/v1/solicitudes-compra';
let activityData = null;
let vehiclesData = [];
let farmsData = [];
let publicationsData = [];
let editingPublicationId = null;
let lastPublicationTrigger = null;
let editingVehicleId = null;
let editingFarmId = null;
let lastVehicleTrigger = null;
let lastFarmTrigger = null;
let farmEditor = null;
let toast = null;
let vehiclePhoto = null;
let publicationPhoto = null;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
}

function ensureSession() {
    if (readAuthSession()) return true;
    window.location.assign('entrar?next=mi-actividad');
    return false;
}

function setActivityView(view, message = '') {
    const loading = document.querySelector('#activity-loading');
    const error = document.querySelector('#activity-error');
    const content = document.querySelector('#activity-content');
    if (loading) loading.hidden = view !== 'loading';
    if (error) error.hidden = view !== 'error';
    if (content) content.hidden = view !== 'content';
    const errorMessage = document.querySelector('#activity-error-message');
    if (errorMessage && message) errorMessage.textContent = message;
}

/** Acciones del encabezado: solo las de actividades activas; Publicar es la principal. */
export function panelActions(capacidades = {}) {
    const activa = (id) => capacidades[id]?.estado === 'ACTIVO';
    return [
        activa('COMPRADOR') && { label: 'Explorar ganado', href: capacidades.COMPRADOR.destinoActivo || 'explorar', primary: false },
        activa('TRANSPORTISTA') && { label: 'Ver fletes', href: capacidades.TRANSPORTISTA.destinoActivo || 'fletes', primary: false },
        activa('PRODUCTOR') && { label: 'Publicar ganado', href: capacidades.PRODUCTOR.destinoActivo || 'publicar', primary: true },
    ].filter(Boolean);
}

function isActive(id) {
    return activityData?.capacidades?.[id]?.estado === 'ACTIVO';
}

function renderHello(data) {
    const persona = data?.persona ?? {};
    const capacidades = data?.capacidades ?? {};
    const saludo = String(persona.alias || persona.nombre || '').trim().split(/\s+/)[0];
    document.querySelector('#panel-alias').textContent = saludo ? `, ${saludo}` : '';

    const activas = Object.keys(BUSINESS_CAPABILITIES).filter((id) => capacidades[id]?.estado === 'ACTIVO');
    const roles = document.querySelector('#panel-roles');
    roles.innerHTML = activas.map((id) => `<span class="activity-state" data-state="ACTIVO">${BUSINESS_CAPABILITIES[id].shortLabel}</span>`).join('')
        + '<a class="panel-link" href="ajustes#participacion">Gestionar</a>';
    document.querySelector('#panel-no-activity').hidden = activas.length > 0;

    document.querySelector('#panel-actions').innerHTML = panelActions(capacidades)
        .map((action) => `<a class="activity-button${action.primary ? ' activity-button--primary' : ''}" href="${escapeHtml(action.href)}">${action.primary ? '<i class="fa-solid fa-plus" aria-hidden="true"></i>' : ''}${action.label}</a>`)
        .join('');
}

function renderProfileCard(persona = {}) {
    const nombre = persona.nombre || 'Tu perfil';
    document.querySelector('#profile-avatar').textContent = String(nombre).trim().charAt(0).toUpperCase() || 'U';
    document.querySelector('#profile-title').textContent = nombre;
    document.querySelector('#profile-alias').textContent = persona.alias ? `Alias: ${persona.alias}` : 'Sin alias';
}

function setCount(id, total) {
    const node = document.querySelector(id);
    if (node) node.textContent = String(total);
}

function renderPanel(data) {
    activityData = data;
    renderHello(data);
    renderProfileCard(data?.persona ?? {});
    document.querySelector('#farms-panel').hidden = !isActive('PRODUCTOR');
    document.querySelector('#publications-panel').hidden = !isActive('PRODUCTOR');
    document.querySelector('#vehicles-panel').hidden = !isActive('TRANSPORTISTA');
    // Sin actividad de vendedor la columna principal queda vacía: el lateral ocupa todo.
    document.querySelector('.panel-grid').classList.toggle('panel-grid--sin-principal', !isActive('PRODUCTOR'));
    syncPublicProfile(data);
    setActivityView('content');
}

function renderFarms(fincas = []) {
    const target = document.querySelector('#farms-list');
    if (!target) return;
    farmsData = Array.isArray(fincas) ? fincas : [];
    const empty = document.querySelector('#farms-empty');
    if (empty) empty.hidden = farmsData.length !== 0;
    setCount('#farms-count', farmsData.length);
    target.innerHTML = farmsData.map((finca) => `<article class="panel-row"><div><h3>${escapeHtml(finca.nombre || 'Finca sin nombre')}</h3><p>${finca.direccion ? 'Dirección registrada' : 'Dirección pendiente'}</p></div><div class="panel-row__actions"><span class="activity-state" data-state="ACTIVO">Activa</span><button class="activity-button activity-button--text" type="button" data-edit-farm="${Number(finca.fincaId)}" aria-label="Editar ${escapeHtml(finca.nombre || 'finca')}">Editar</button></div></article>`).join('');
    target.querySelectorAll('[data-edit-farm]').forEach((button) => button.addEventListener('click', () => openFarmModal(Number(button.dataset.editFarm), button)));
}

function setFarmView(view, message = '') {
    const loading = document.querySelector('#farms-loading');
    const error = document.querySelector('#farms-error');
    const content = document.querySelector('#farms-content');
    if (loading) loading.hidden = view !== 'loading';
    if (error) error.hidden = view !== 'error';
    if (content) content.hidden = view !== 'content';
    const errorMessage = document.querySelector('#farms-error-message');
    if (errorMessage && message) errorMessage.textContent = message;
}

async function loadFarms() {
    const producer = activityData?.capacidades?.PRODUCTOR;
    const add = document.querySelector('#farm-add');
    if (!isActive('PRODUCTOR')) {
        if (add) add.hidden = true;
        setFarmView('content');
        renderFarms([]);
        return;
    }
    if (add) add.hidden = producer.escrituraDisponible !== true;
    setFarmView('loading');
    try {
        const response = await request(FARMS_API);
        renderFarms(response.data?.fincas ?? []);
        setFarmView('content');
        await loadPublications();
    } catch (error) {
        if (error?.status === 401) endExpiredSession();
        else setFarmView('error', error?.message || 'No pudimos cargar tus fincas.');
    }
}

function renderVehicles(vehiculos = []) {
    vehiclesData = Array.isArray(vehiculos) ? vehiculos : [];
    const target = document.querySelector('#vehicles-list');
    const empty = document.querySelector('#vehicles-empty');
    const content = document.querySelector('#vehicles-content');
    if (!target || !empty || !content) return;
    content.hidden = false;
    empty.hidden = vehiclesData.length !== 0;
    // Sin vehículos el aviso ya trae su propio "Agregar vehículo".
    const add = document.querySelector('#vehicle-add');
    if (add && vehiclesData.length === 0) add.hidden = true;
    setCount('#vehicles-count', vehiclesData.length);
    target.innerHTML = vehiclesData.map((vehicle) => `<article class="panel-row panel-row--media">${miniatura({ imagenUrl: vehicle.fotoUrl }, 'fa-truck')}<div><h3>${escapeHtml(vehicle.placa)}</h3><p>${escapeHtml(vehicle.modelo)}</p></div><div class="panel-row__actions"><span class="activity-state" data-state="${escapeHtml(vehicle.estado)}">${vehicle.estado === 'ACTIVO' ? 'Activo' : 'Inactivo'}</span>${vehicle.estado === 'ACTIVO' ? `<button class="activity-button activity-button--text" type="button" data-edit-vehicle="${vehicle.vehiculoId}">Editar</button><button class="activity-button activity-button--text" type="button" data-remove-vehicle="${vehicle.vehiculoId}">Desactivar</button>` : `<button class="activity-button activity-button--text" type="button" data-restore-vehicle="${vehicle.vehiculoId}">Reactivar</button>`}</div></article>`).join('');
    target.querySelectorAll('[data-edit-vehicle]').forEach((button) => button.addEventListener('click', () => openVehicleModal(Number(button.dataset.editVehicle), button)));
    target.querySelectorAll('[data-remove-vehicle]').forEach((button) => button.addEventListener('click', () => changeVehicleState(Number(button.dataset.removeVehicle), false)));
    target.querySelectorAll('[data-restore-vehicle]').forEach((button) => button.addEventListener('click', () => changeVehicleState(Number(button.dataset.restoreVehicle), true)));
}

function setVehicleView(view, message = '') {
    const loading = document.querySelector('#vehicles-loading');
    const error = document.querySelector('#vehicles-error');
    const content = document.querySelector('#vehicles-content');
    if (loading) loading.hidden = view !== 'loading';
    if (error) error.hidden = view !== 'error';
    if (content) content.hidden = view !== 'content';
    const errorMessage = document.querySelector('#vehicles-error-message');
    if (errorMessage && message) errorMessage.textContent = message;
}

async function loadVehicles() {
    const transportista = activityData?.capacidades?.TRANSPORTISTA;
    const add = document.querySelector('#vehicle-add');
    if (!isActive('TRANSPORTISTA')) {
        if (add) add.hidden = true;
        setVehicleView('content');
        renderVehicles([]);
        return;
    }
    if (add) add.hidden = transportista.escrituraDisponible !== true;
    setVehicleView('loading');
    try {
        const response = await request(VEHICLES_API);
        renderVehicles(response.data?.vehiculos ?? []);
        setVehicleView('content');
    } catch (error) {
        if (error?.status === 401) endExpiredSession();
        else setVehicleView('error', error?.message || 'No pudimos cargar tus vehículos.');
    }
}

async function loadActivity({ quiet = false } = {}) {
    if (!quiet) setActivityView('loading');
    try {
        const response = await request(ACTIVITY_API);
        renderPanel(response.data);
        await loadFarms();
        await loadVehicles();
        await loadSolicitudes();
        return response.data;
    } catch (error) {
        if (error?.status === 401) endExpiredSession();
        else setActivityView('error', error?.message || 'No fue posible consultar tu actividad.');
        return null;
    }
}

function formatColones(precio) {
    if (typeof precio !== 'number' || !Number.isFinite(precio)) return 'Precio a convenir';
    return `₡${String(Math.round(precio)).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
}

function miniatura(item, icono = 'fa-cow') {
    const url = safeImageUrl(item?.imagenUrl);
    return url
        ? `<img class="panel-thumb" src="${escapeHtml(url)}" alt="" loading="lazy" referrerpolicy="no-referrer">`
        : `<span class="panel-thumb panel-thumb--empty" aria-hidden="true"><i class="fa-solid ${icono}"></i></span>`;
}

const SOLICITUD_ESTADOS = { PENDIENTE: 'Pendiente', ACEPTADA: 'Aceptada', RECHAZADA: 'Rechazada', CANCELADA: 'Cancelada' };
const FLETE_ESTADOS = { PENDIENTE: 'por confirmar', ACEPTADA: 'aceptado', RECHAZADA: 'rechazado', CANCELADA: 'cancelado' };
let solicitudesData = { hechas: [], recibidas: [], fletes: [] };

function contactoTexto(parte) {
    if (!parte) return '';
    return parte.telefono ? `${escapeHtml(parte.nombre)} · ${escapeHtml(parte.telefono)}` : escapeHtml(parte.nombre);
}

function solicitudAccion(accion, etiqueta, id) {
    return `<button class="activity-button activity-button--text" type="button" data-solicitud-accion="${accion}" data-solicitud-id="${id}">${etiqueta}</button>`;
}

/** Fila común: título, precio, flete y el estado; cada bandeja agrega sus líneas y botones. */
function filaSolicitud(s, lineas, botones, estado = { texto: SOLICITUD_ESTADOS[s.estado] ?? '', vigente: s.estado === 'ACEPTADA' || s.estado === 'PENDIENTE' }) {
    const flete = s.flete ? `<p>Flete: ${escapeHtml(s.flete.vehiculo || 'Transporte')} · ${escapeHtml(s.flete.transportista.nombre)} (${FLETE_ESTADOS[s.flete.estado] ?? ''})</p>` : '';
    const pago = s.pagoMetodo ? `<p>Método de pago: ${escapeHtml(s.pagoMetodo.nombre)}</p>` : '';
    return `<article class="panel-row panel-row--media">${miniatura({ imagenUrl: s.publicacion.imagenUrl })}<div><h3>${escapeHtml(s.publicacion.titulo || 'Publicación')}</h3><p>${formatColones(s.precio)}</p>${pago}${flete}${lineas}</div><div class="panel-row__actions"><span class="activity-state" data-state="${estado.vigente ? 'ACTIVO' : 'INACTIVO'}">${estado.texto}</span>${botones}</div></article>`;
}

function filaHecha(s) {
    const aceptada = s.estado === 'ACEPTADA';
    const lineas = (aceptada ? `<p>Contacta al vendedor: ${contactoTexto(s.vendedor)}</p>` : `<p>Vendedor: ${escapeHtml(s.vendedor.nombre)}</p>`)
        + (aceptada && s.flete?.estado === 'ACEPTADA' ? `<p>Transportista: ${contactoTexto(s.flete.transportista)}</p>` : '')
        + (s.respuestaMotivo ? `<p>Motivo: ${escapeHtml(s.respuestaMotivo)}</p>` : '');
    return filaSolicitud(s, lineas, s.estado === 'PENDIENTE' ? solicitudAccion('CANCELAR', 'Cancelar', s.solicitudId) : '');
}

function filaRecibida(s) {
    const lineas = `<p>Comprador: ${contactoTexto(s.comprador)}</p>${s.mensaje ? `<p>“${escapeHtml(s.mensaje)}”</p>` : ''}`;
    const botones = s.estado === 'PENDIENTE' ? solicitudAccion('ACEPTAR', 'Aceptar', s.solicitudId) + solicitudAccion('RECHAZAR', 'Rechazar', s.solicitudId) : '';
    return filaSolicitud(s, lineas, botones);
}

function filaFlete(s) {
    const lineas = `<p>Comprador: ${contactoTexto(s.comprador)}</p><p>Vendedor: ${contactoTexto(s.vendedor)}</p>`;
    const botones = s.flete?.estado === 'PENDIENTE' ? solicitudAccion('ACEPTAR_FLETE', 'Aceptar flete', s.solicitudId) + solicitudAccion('RECHAZAR_FLETE', 'Rechazar', s.solicitudId) : '';
    const estadoFlete = s.flete?.estado;
    return filaSolicitud(s, lineas, botones, { texto: `Flete ${FLETE_ESTADOS[estadoFlete] ?? ''}`, vigente: estadoFlete === 'ACEPTADA' || estadoFlete === 'PENDIENTE' });
}

function renderSolicitudes(datos) {
    solicitudesData = { hechas: datos?.hechas ?? [], recibidas: datos?.recibidas ?? [], fletes: datos?.fletes ?? [] };
    const bandejas = [['hechas', filaHecha], ['recibidas', filaRecibida], ['fletes', filaFlete]];
    let alguna = false;
    for (const [clave, fila] of bandejas) {
        const lista = solicitudesData[clave];
        document.querySelector(`#sol-${clave}-panel`).hidden = lista.length === 0;
        document.querySelector(`#sol-${clave}-list`).innerHTML = lista.map(fila).join('');
        setCount(`#sol-${clave}-count`, lista.length);
        alguna ||= lista.length > 0;
    }
    // Una persona sin vendedor pero con solicitudes también necesita la columna principal.
    document.querySelector('.panel-grid').classList.toggle('panel-grid--sin-principal', !isActive('PRODUCTOR') && !alguna);
    // El carrito del encabezado enlaza a mi-actividad#mis-solicitudes; la bandeja nace oculta, así que el ancla nativa no alcanza.
    if (globalThis.location?.hash === '#mis-solicitudes' && solicitudesData.hechas.length) {
        document.querySelector('#mis-solicitudes')?.scrollIntoView();
    }
}

async function loadSolicitudes() {
    try {
        const response = await request(SOLICITUDES_API);
        renderSolicitudes(response.data);
    } catch (error) {
        if (error?.status === 401) endExpiredSession();
        else toast?.error(error?.message || 'No pudimos cargar tus solicitudes.');
    }
}

async function responderSolicitud(id, accion) {
    const solicitud = [...solicitudesData.hechas, ...solicitudesData.recibidas, ...solicitudesData.fletes].find((s) => s.solicitudId === id);
    if (!solicitud) return;
    const cuerpo = { solicitudId: id, accion };
    if (accion === 'ACEPTAR') {
        if (!window.confirm('¿Aceptar esta solicitud? La publicación se marcará como vendida y las demás solicitudes se rechazarán.')) return;
        if (solicitud.precio === null) {
            const texto = window.prompt('La publicación no tiene precio. Indica el precio acordado en colones:');
            if (texto === null) return;
            const precio = Number(String(texto).replace(/\D/g, ''));
            if (!(precio > 0)) { toast?.error('Indica un precio válido.'); return; }
            cuerpo.precio = precio;
        }
    } else if (accion === 'RECHAZAR') {
        const motivo = window.prompt('Motivo del rechazo (opcional):');
        if (motivo === null) return;
        if (motivo.trim()) cuerpo.motivo = motivo.trim();
    } else if (!window.confirm(accion === 'CANCELAR' ? '¿Cancelar esta solicitud?' : '¿Confirmas tu respuesta sobre el flete?')) {
        return;
    }
    try {
        const response = await request(SOLICITUDES_API, { method: 'PATCH', body: JSON.stringify(cuerpo) });
        toast?.success(response.message || 'Listo.');
        await loadSolicitudes();
        if (accion === 'ACEPTAR' && isActive('PRODUCTOR')) await loadPublications();
    } catch (error) {
        if (error?.status === 401) endExpiredSession();
        else toast?.error(error?.errors?.precio || error?.message || 'No pudimos responder la solicitud.');
    }
}

function setPublicationsView(view, message = '') {
    document.querySelector('#publications-loading').hidden = view !== 'loading';
    document.querySelector('#publications-error').hidden = view !== 'error';
    const errorMessage = document.querySelector('#publications-error-message');
    if (errorMessage && message) errorMessage.textContent = message;
}

const PUBLICATION_STATES = { ACTIVO: 'Activa', PAUSADO: 'Pausada', VENDIDO: 'Vendida', RETIRADO: 'Retirada' };

function publicationActions(item) {
    const id = Number(item.publicacionId);
    const button = (action, label) => `<button class="activity-button activity-button--text" type="button" data-publication-action="${action}" data-publication-id="${id}">${label}</button>`;
    if (item.estado === 'ACTIVO') return `<a class="activity-button activity-button--text" href="explorar?publicacion=${id}">Ver</a>${button('editar', 'Editar')}${button('PAUSADO', 'Pausar')}${button('VENDIDO', 'Vendida')}`;
    if (item.estado === 'PAUSADO') return `${button('editar', 'Editar')}${button('ACTIVO', 'Reactivar')}${button('VENDIDO', 'Vendida')}`;
    return '';
}

function renderPublications(publicaciones) {
    publicationsData = publicaciones;
    const list = document.querySelector('#publications-list');
    const empty = document.querySelector('#publications-empty');
    setCount('#publications-count', publicaciones.length);
    list.innerHTML = publicaciones.map((item) => `<article class="panel-row panel-row--media">${miniatura(item)}<div><h3>${escapeHtml(item.titulo || 'Publicación')}</h3><p>${escapeHtml(item.finca?.nombre || '')} · ${formatColones(item.precio)}</p></div><div class="panel-row__actions"><span class="activity-state" data-state="${escapeHtml(item.estado)}">${escapeHtml(PUBLICATION_STATES[item.estado] ?? String(item.estado ?? '').toLowerCase())}</span>${publicationActions(item)}</div></article>`).join('');
    empty.hidden = publicaciones.length > 0;
    if (publicaciones.length > 0) return;
    // Sin fincas no se puede publicar: el siguiente paso es registrar una.
    empty.innerHTML = farmsData.length > 0
        ? '<strong>Aún no has publicado ganado</strong><p>Ya tienes una finca registrada, así que puedes publicar tu primer lote ahora.</p><a class="activity-button activity-button--sm" href="publicar">Publicar ganado</a>'
        : '<strong>Aún no has publicado ganado</strong><p>Primero registra una finca: tus publicaciones salen desde ahí.</p><button class="activity-button activity-button--sm" type="button" data-empty-add-farm>Agregar finca</button>';
    empty.querySelector('[data-empty-add-farm]')?.addEventListener('click', (event) => openFarmModal(null, event.currentTarget));
}

async function loadPublications() {
    if (!isActive('PRODUCTOR')) return;
    setPublicationsView('loading');
    try {
        const response = await request(PUBLICATIONS_API, {
            method: 'POST',
            body: JSON.stringify({ consulta: { mias: true, estado: 'TODOS', pagina: '1', tamanoPagina: '100' } }),
        });
        renderPublications(response.data?.publicaciones ?? []);
        setPublicationsView('content');
    } catch (error) {
        if (error?.status === 401) endExpiredSession();
        else setPublicationsView('error', error?.message || 'No pudimos cargar tus publicaciones.');
    }
}

async function patchPublication(cuerpo) {
    return request(PUBLICATIONS_API, { method: 'PATCH', body: JSON.stringify(cuerpo) });
}

async function changePublicationState(id, estado) {
    const preguntas = { PAUSADO: '¿Pausar esta publicación? Dejará de verse en Explorar.', ACTIVO: '¿Reactivar esta publicación?', VENDIDO: '¿Marcar como vendida? Ya no podrás reactivarla.' };
    if (!id || !window.confirm(preguntas[estado] ?? '¿Cambiar el estado?')) return;
    try {
        const response = await patchPublication({ publicacionId: id, estado });
        await loadPublications();
        toast?.success(response.message || 'Publicación actualizada.');
    } catch (error) {
        toast?.error(error?.message || 'No fue posible actualizar la publicación.');
    }
}

function setPublicationErrors(errors = {}) {
    document.querySelectorAll('[data-publication-error]').forEach((node) => { node.textContent = ''; });
    document.querySelectorAll('#publication-form [aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
    for (const [field, message] of Object.entries(errors)) {
        const messageNode = document.querySelector(`[data-publication-error="${field}"]`);
        const control = document.querySelector(`#publication-form [name="${field}"]`);
        if (messageNode) messageNode.textContent = String(message);
        if (control) control.setAttribute('aria-invalid', 'true');
    }
}

function openPublicationModal(id, trigger = null) {
    const dialog = document.querySelector('#publication-modal');
    const form = document.querySelector('#publication-form');
    const item = publicationsData.find((publicacion) => Number(publicacion.publicacionId) === id);
    if (!dialog || !form || !item) return;
    editingPublicationId = id;
    lastPublicationTrigger = trigger;
    form.elements.titulo.value = item.titulo ?? '';
    form.elements.precio.value = item.precio ?? '';
    form.elements.descripcion.value = item.descripcion ?? '';
    setPublicationErrors({});
    publicationPhoto.reiniciar(safeImageUrl(item.imagenUrl));
    document.querySelector('#publication-form-status').textContent = '';
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else { dialog.hidden = false; dialog.setAttribute('open', ''); }
    form.elements.titulo.focus();
}

function closePublicationModal() {
    const dialog = document.querySelector('#publication-modal');
    if (!dialog) return;
    if (typeof dialog.close === 'function' && dialog.open) dialog.close();
    else { dialog.hidden = true; dialog.removeAttribute('open'); }
    lastPublicationTrigger?.focus?.();
    editingPublicationId = null;
}

async function savePublication(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const status = document.querySelector('#publication-form-status');
    const save = document.querySelector('#publication-save');
    const titulo = form.elements.titulo.value.trim();
    if (!titulo) { setPublicationErrors({ titulo: 'Este dato es obligatorio.' }); status.textContent = 'Revise los datos señalados.'; return; }
    setPublicationErrors({});
    save.disabled = true;
    form.setAttribute('aria-busy', 'true');
    status.textContent = 'Guardando publicación…';
    try {
        const imagenUrl = await publicationPhoto.resolver();
        const response = await patchPublication({
            publicacionId: editingPublicationId,
            titulo,
            precio: form.elements.precio.value.trim() === '' ? null : Number(form.elements.precio.value),
            descripcion: form.elements.descripcion.value.trim() || null,
            ...(imagenUrl !== undefined && { imagenUrl }),
        });
        closePublicationModal();
        await loadPublications();
        toast?.success(response.message || 'Publicación actualizada.');
    } catch (error) {
        setPublicationErrors(error?.errors ?? {});
        if (error?.errors?.imagenUrl) publicationPhoto.mostrarError(error.errors.imagenUrl);
        status.textContent = error?.message || 'No fue posible guardar la publicación.';
    } finally {
        save.disabled = false;
        form.setAttribute('aria-busy', 'false');
    }
}

function initializeSolicitudesUi() {
    for (const clave of ['hechas', 'recibidas', 'fletes']) {
        document.querySelector(`#sol-${clave}-list`)?.addEventListener('click', (event) => {
            const boton = event.target.closest('[data-solicitud-accion]');
            if (boton) responderSolicitud(Number(boton.dataset.solicitudId), boton.dataset.solicitudAccion);
        });
    }
}

function initializePublicationUi() {
    publicationPhoto = montarCampoFoto(document.querySelector('[data-foto-campo="publication"]'));
    document.querySelector('#publications-list')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-publication-action]');
        if (!button) return;
        const id = Number(button.dataset.publicationId);
        if (button.dataset.publicationAction === 'editar') openPublicationModal(id, button);
        else changePublicationState(id, button.dataset.publicationAction);
    });
    document.querySelector('#publication-form')?.addEventListener('submit', savePublication);
    document.querySelector('#publication-close')?.addEventListener('click', closePublicationModal);
    document.querySelector('#publication-cancel')?.addEventListener('click', closePublicationModal);
    document.querySelector('#publication-modal')?.addEventListener('cancel', (event) => { event.preventDefault(); closePublicationModal(); });
}

function disposeFarmEditor() {
    farmEditor?.cancelarGeocodificacion?.();
    farmEditor?.mapa?.destruir?.();
    farmEditor = null;
}

function openFarmModal(id = null, trigger = null) {
    const dialog = document.querySelector('#farm-modal');
    const form = document.querySelector('#farm-form');
    if (!dialog || !form) return;
    disposeFarmEditor();
    editingFarmId = id;
    lastFarmTrigger = trigger;
    const farm = farmsData.find((item) => Number(item.fincaId) === id);
    document.querySelector('#farm-modal-title').textContent = farm ? 'Editar finca' : 'Agregar finca';
    document.querySelector('#farm-deactivate').hidden = !farm;
    form.elements.nombreFinca.value = farm?.nombre ?? '';
    setFarmErrors({});
    document.querySelector('#farm-form-status').textContent = '';
    const details = document.querySelector('#farm-address');
    if (details) details.open = Boolean(farm?.direccion);
    const province = details?.querySelector('[data-farm-province]');
    const canton = details?.querySelector('[data-farm-canton]');
    const district = details?.querySelector('[data-farm-district]');
    const town = details?.querySelector('[data-farm-town]');
    const directions = details?.querySelector('[data-farm-directions]');
    const lista = details?.querySelector('[data-farm-town-list]');
    if (!province || !canton || !district || !town || !directions || !lista) return;
    const direccion = conectarDireccion({ provincia: province, canton, distrito: district, pueblo: town, listaPueblos: lista });
    direccion.aplicar(farm?.direccion ?? {});
    directions.value = farm?.direccion?.senas ?? '';
    let geoTurn = 0;
    let geoAbort = null;
    const completarDesdePunto = async (punto) => {
        geoAbort?.abort();
        const turn = ++geoTurn;
        if (!punto) return;
        const controller = new AbortController();
        geoAbort = controller;
        try {
            const encontrada = await buscarDireccionPorCoordenadas(punto, { signal: controller.signal });
            if (turn !== geoTurn || !dialog.isConnected) return;
            direccion.aplicar({ ...encontrada, pueblo: encontrada.pueblo || town.value });
        } catch {
            // El usuario puede conservar el punto y completar la dirección manualmente.
        } finally {
            if (geoAbort === controller) geoAbort = null;
        }
    };
    const mapa = crearSelectorPuntoFinca({
        mount: details.querySelector('[data-farm-map]'),
        puntoInicial: { latitud: farm?.direccion?.latitud ?? null, longitud: farm?.direccion?.longitud ?? null },
        onPuntoChange: completarDesdePunto,
    });
    farmEditor = { direccion, mapa, cancelarGeocodificacion: () => { geoTurn += 1; geoAbort?.abort(); geoAbort = null; } };
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else { dialog.hidden = false; dialog.setAttribute('open', ''); }
    document.querySelector('#farm-name')?.focus();
}

function closeFarmModal() {
    const dialog = document.querySelector('#farm-modal');
    if (!dialog) return;
    disposeFarmEditor();
    if (typeof dialog.close === 'function' && dialog.open) dialog.close();
    else { dialog.hidden = true; dialog.removeAttribute('open'); }
    lastFarmTrigger?.focus?.();
    editingFarmId = null;
}

function setFarmErrors(errors = {}) {
    document.querySelectorAll('[data-farm-error]').forEach((node) => { node.textContent = ''; });
    document.querySelectorAll('#farm-form [aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
    for (const [field, message] of Object.entries(errors)) {
        const key = field.split('.').at(-1);
        const messageNode = document.querySelector(`[data-farm-error="${field}"], [data-farm-error="${key}"]`);
        const control = document.querySelector(`#farm-form [name="${key}"]`);
        if (messageNode) messageNode.textContent = String(message);
        if (control) control.setAttribute('aria-invalid', 'true');
    }
}

function readFarmPayload() {
    const form = document.querySelector('#farm-form');
    const details = document.querySelector('#farm-address');
    const point = farmEditor?.mapa?.obtenerPunto?.() ?? null;
    const direccion = {
        provincia: String(details?.querySelector('[data-farm-province]')?.value ?? '').trim(),
        canton: String(details?.querySelector('[data-farm-canton]')?.value ?? '').trim(),
        distrito: String(details?.querySelector('[data-farm-district]')?.value ?? '').trim(),
        pueblo: String(details?.querySelector('[data-farm-town]')?.value ?? '').trim() || null,
        senas: String(details?.querySelector('[data-farm-directions]')?.value ?? '').trim() || null,
        latitud: point?.latitud ?? null,
        longitud: point?.longitud ?? null,
    };
    const tieneDireccion = Object.entries(direccion).some(([key, value]) => !['latitud', 'longitud'].includes(key) && value !== null && value !== '') || point !== null;
    return { nombreFinca: String(form?.elements.nombreFinca?.value ?? '').trim(), direccion: tieneDireccion ? direccion : null };
}

async function saveFarm(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const payload = readFarmPayload();
    const errors = {};
    if (!payload.nombreFinca) errors.nombreFinca = 'El nombre es obligatorio.';
    if (payload.nombreFinca.length > 150) errors.nombreFinca = 'El nombre no puede superar 150 caracteres.';
    if (payload.direccion && (!payload.direccion.provincia || !payload.direccion.canton || !payload.direccion.distrito)) {
        errors.direccionFinca = 'Completa provincia, cantón y distrito, o deja toda la dirección vacía.';
    }
    if (Object.keys(errors).length) { setFarmErrors(errors); return; }
    const save = document.querySelector('#farm-save');
    save.disabled = true;
    form.setAttribute('aria-busy', 'true');
    try {
        const body = { nombreFinca: payload.nombreFinca };
        if (payload.direccion) body.direccionFinca = payload.direccion;
        if (editingFarmId) { body.fincaId = editingFarmId; }
        const response = await request(FARMS_API, { method: editingFarmId ? 'PUT' : 'POST', body: JSON.stringify(body) });
        closeFarmModal();
        await loadFarms();
        document.querySelector('#activity-status').textContent = response.message || 'Finca guardada correctamente.';
        toast?.success(response.message || 'Finca guardada correctamente.');
    } catch (error) {
        if (error?.status === 422 && error.errors) setFarmErrors(error.errors);
        document.querySelector('#farm-form-status').textContent = error?.message || 'No fue posible guardar la finca.';
        toast?.error(error?.message || 'No fue posible guardar la finca.');
    } finally {
        save.disabled = false;
        form.setAttribute('aria-busy', 'false');
    }
}

async function changeFarmState(id, activo) {
    if (!id || !window.confirm(activo ? '¿Reactivar esta finca?' : '¿Desactivar esta finca?')) return;
    try {
        const response = await request(FARMS_API, { method: activo ? 'PATCH' : 'DELETE', body: JSON.stringify({ fincaId: id }) });
        closeFarmModal();
        await loadFarms();
        document.querySelector('#activity-status').textContent = response.message || 'Estado de la finca actualizado.';
        toast?.success(response.message || 'Estado de la finca actualizado.');
    } catch (error) {
        document.querySelector('#activity-status').textContent = error?.message || 'No fue posible actualizar la finca.';
        toast?.error(error?.message || 'No fue posible actualizar la finca.');
    }
}

function setFormErrors(errors = {}) {
    document.querySelectorAll('[data-vehicle-error]').forEach((node) => { node.textContent = ''; });
    document.querySelectorAll('#vehicle-form [aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
    for (const [field, message] of Object.entries(errors)) {
        const messageNode = document.querySelector(`[data-vehicle-error="${field}"]`);
        const control = document.querySelector(`#vehicle-form [name="${field}"]`);
        if (messageNode) messageNode.textContent = String(message);
        if (control) control.setAttribute('aria-invalid', 'true');
    }
}

function openVehicleModal(id = null, trigger = null) {
    const dialog = document.querySelector('#vehicle-modal');
    const form = document.querySelector('#vehicle-form');
    if (!dialog || !form) return;
    editingVehicleId = id;
    lastVehicleTrigger = trigger;
    const vehicle = vehiclesData.find((item) => Number(item.vehiculoId) === id);
    document.querySelector('#vehicle-modal-title').textContent = vehicle ? 'Editar vehículo' : 'Agregar vehículo';
    for (const field of ['placa', 'vin', 'modelo']) form.elements[field].value = vehicle?.[field] ?? '';
    setFormErrors({});
    vehiclePhoto.reiniciar(safeImageUrl(vehicle?.fotoUrl));
    document.querySelector('#vehicle-form-status').textContent = '';
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else { dialog.hidden = false; dialog.setAttribute('open', ''); }
    document.querySelector('#vehicle-placa')?.focus();
}

function closeVehicleModal() {
    const dialog = document.querySelector('#vehicle-modal');
    if (!dialog) return;
    if (typeof dialog.close === 'function' && dialog.open) dialog.close();
    else { dialog.hidden = true; dialog.removeAttribute('open'); }
    lastVehicleTrigger?.focus?.();
    editingVehicleId = null;
}

async function saveVehicle(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const status = document.querySelector('#vehicle-form-status');
    const save = document.querySelector('#vehicle-save');
    const datos = Object.fromEntries(new FormData(form).entries());
    const errors = Object.fromEntries(['placa', 'vin', 'modelo'].filter((field) => !String(datos[field] ?? '').trim()).map((field) => [field, 'Este dato es obligatorio.']));
    setFormErrors(errors);
    if (Object.keys(errors).length > 0) { status.textContent = 'Revise los datos señalados.'; return; }
    save.disabled = true;
    form.setAttribute('aria-busy', 'true');
    status.textContent = 'Guardando vehículo…';
    try {
        // Sin cambio no se envía fotoUrl: en PUT eso conserva la foto guardada.
        const fotoUrl = await vehiclePhoto.resolver();
        if (fotoUrl !== undefined) datos.fotoUrl = fotoUrl;
        const options = { method: editingVehicleId ? 'PUT' : 'POST', body: JSON.stringify(editingVehicleId ? { ...datos, vehiculoId: editingVehicleId } : datos) };
        const response = await request(VEHICLES_API, options);
        closeVehicleModal();
        await loadVehicles();
        document.querySelector('#activity-status').textContent = response.message || 'Vehículo guardado correctamente.';
        toast?.success(response.message || 'Vehículo guardado correctamente.');
    } catch (error) {
        setFormErrors(error?.errors ?? {});
        if (error?.errors?.fotoUrl) vehiclePhoto.mostrarError(error.errors.fotoUrl);
        status.textContent = error?.message || 'No fue posible guardar el vehículo.';
        toast?.error(error?.message || 'No fue posible guardar el vehículo.');
    } finally {
        save.disabled = false;
        form.setAttribute('aria-busy', 'false');
    }
}

async function changeVehicleState(id, activo) {
    if (!id || !window.confirm(activo ? '¿Reactivar este vehículo?' : '¿Desactivar este vehículo?')) return;
    try {
        const response = await request(VEHICLES_API, { method: activo ? 'PATCH' : 'DELETE', body: JSON.stringify({ vehiculoId: id }) });
        await loadVehicles();
        document.querySelector('#activity-status').textContent = response.message || 'Estado del vehículo actualizado.';
        toast?.success(response.message || 'Estado del vehículo actualizado.');
    } catch (error) {
        document.querySelector('#activity-status').textContent = error?.message || 'No fue posible actualizar el vehículo.';
        toast?.error(error?.message || 'No fue posible actualizar el vehículo.');
    }
}

function initializeVehicleUi() {
    vehiclePhoto = montarCampoFoto(document.querySelector('[data-foto-campo="vehicle"]'));
    document.querySelector('#vehicle-add')?.addEventListener('click', () => openVehicleModal());
    document.querySelector('#vehicle-add-empty')?.addEventListener('click', (event) => openVehicleModal(null, event.currentTarget));
    document.querySelector('#vehicles-retry')?.addEventListener('click', loadVehicles);
    document.querySelector('#vehicle-form')?.addEventListener('submit', saveVehicle);
    document.querySelector('#vehicle-close')?.addEventListener('click', closeVehicleModal);
    document.querySelector('#vehicle-cancel')?.addEventListener('click', closeVehicleModal);
    document.querySelector('#vehicle-modal')?.addEventListener('cancel', (event) => { event.preventDefault(); closeVehicleModal(); });
}

function initializeFarmUi() {
    document.querySelector('#farm-add')?.addEventListener('click', () => openFarmModal());
    document.querySelector('#farm-deactivate')?.addEventListener('click', () => changeFarmState(editingFarmId, false));
    document.querySelector('#publications-retry')?.addEventListener('click', loadPublications);
    document.querySelector('#farms-retry')?.addEventListener('click', loadFarms);
    document.querySelector('#farm-form')?.addEventListener('submit', saveFarm);
    document.querySelector('#farm-close')?.addEventListener('click', closeFarmModal);
    document.querySelector('#farm-cancel')?.addEventListener('click', closeFarmModal);
    document.querySelector('#farm-modal')?.addEventListener('cancel', (event) => { event.preventDefault(); closeFarmModal(); });
}

function initialize() {
    if (!ensureSession()) return;
    toast = createToast({ polite: document.querySelector('#toast-status'), assertive: document.querySelector('#toast-alert') });
    initializeFarmUi();
    initializeVehicleUi();
    initializePublicationUi();
    initializeSolicitudesUi();
    const params = new URLSearchParams(window.location.search);
    if (params.get('bienvenida') === '1') document.querySelector('#welcome-banner').hidden = false;
    document.querySelector('#activity-retry')?.addEventListener('click', () => loadActivity());
    loadActivity();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
