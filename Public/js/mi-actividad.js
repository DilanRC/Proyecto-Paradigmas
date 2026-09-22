import { request } from './shared/api.js';
import { BUSINESS_CAPABILITIES } from './shared/business-rules.js';
import { readAuthSession } from './shared/supabase-auth.js';
import { syncPublicProfile } from './shared/public-profile.js';
import { createToast } from './shared/toast.js';
import { conectarDireccion } from './shared/direccion.js';
import { buscarDireccionPorCoordenadas, crearSelectorPuntoFinca } from './shared/finca-mapa.js';

const ACTIVITY_API = 'api/v1/actividad';
const VEHICLES_API = 'api/v1/mi-vehiculos';
const FARMS_API = 'api/v1/mi-fincas';
let activityData = null;
let vehiclesData = [];
let farmsData = [];
let editingVehicleId = null;
let editingFarmId = null;
let lastVehicleTrigger = null;
let lastFarmTrigger = null;
let farmEditor = null;
const pendingChanges = new Set();
let toast = null;

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

function renderSummary(data) {
    const resumen = data?.resumen ?? {};
    const capacidades = data?.capacidades ?? {};
    const value = (id, fallback) => { const node = document.querySelector(id); if (node) node.textContent = String(resumen[fallback] ?? 0); };
    value('#summary-active', 'actividadesActivas');
    value('#summary-pending', 'actividadesPendientes');
    value('#summary-farms', 'fincas');
    value('#summary-vehicles', 'vehiculos');
    if (!data?.resumen) {
        const active = Object.values(capacidades).filter((detail) => detail?.estado === 'ACTIVO').length;
        const pending = Object.values(capacidades).filter((detail) => detail?.estado === 'NO_CONFIGURADO').length;
        document.querySelector('#summary-active').textContent = String(active);
        document.querySelector('#summary-pending').textContent = String(pending);
    }
}

function renderProfileAndSummary(data) {
    activityData = data;
    renderProfile(data?.persona ?? {});
    renderSummary(data);
    renderActivities(data?.capacidades ?? {});
    renderFarms(data?.capacidades?.PRODUCTOR?.fincas ?? []);
    syncPublicProfile(data);
    setActivityView('content');
}

function toggleLabel(id, state) {
    if (state === 'ACTIVO') return id === 'PRODUCTOR' ? 'Dejar de vender' : id === 'TRANSPORTISTA' ? 'Dejar de ofrecer fletes' : 'Desactivar';
    return id === 'PRODUCTOR' ? 'Volver a vender' : id === 'TRANSPORTISTA' ? 'Volver a ofrecer fletes' : 'Reactivar';
}

function businessLink(id, detail) {
    if (detail?.estado !== 'ACTIVO' || !detail?.destinoActivo) return '';
    const label = id === 'PRODUCTOR' ? 'Publicar ganado' : id === 'COMPRADOR' ? 'Explorar ganado' : 'Ver fletes';
    return `<a class="activity-button activity-button--primary" href="${escapeHtml(detail.destinoActivo)}">${label}</a>`;
}

function setupAction(id, detail) {
    const state = detail?.estado ?? 'NO_CONFIGURADO';
    if (state === 'NO_CONFIGURADO') {
        return `<a class="activity-button activity-button--primary" href="${escapeHtml(detail?.destinoConfiguracion || `registro?capacidad=${id}&next=mi-actividad`)}">Configurar</a>`;
    }
    if (detail?.escrituraDisponible !== true) return `<p class="activity-note">${escapeHtml(detail?.motivoBloqueo || 'La actividad está administrada por el sistema.')}</p>${businessLink(id, detail)}`;
    const nextActive = state !== 'ACTIVO';
    return `<button class="activity-button${nextActive ? ' activity-button--primary' : ''}" type="button" data-toggle-capability="${id}" data-next-active="${nextActive}">${toggleLabel(id, state)}</button>${businessLink(id, detail)}`;
}

function renderActivities(capacidades = {}) {
    const target = document.querySelector('#activity-list');
    if (!target) return;
    target.innerHTML = Object.entries(BUSINESS_CAPABILITIES).map(([id, capability]) => {
        const detail = capacidades[id] ?? { estado: 'NO_CONFIGURADO', escrituraDisponible: false };
        const state = detail.estado ?? 'NO_CONFIGURADO';
        return `<article class="activity-card"><div><h3>${capability.label}</h3><p>${capability.description}</p><span class="activity-state" data-state="${state}">${state === 'NO_CONFIGURADO' ? 'Aún no configurado' : state}</span></div><div class="activity-actions">${setupAction(id, detail)}</div></article>`;
    }).join('');
    target.querySelectorAll('[data-toggle-capability]').forEach((button) => button.addEventListener('click', () => changeCapability(button)));
}

function renderFarms(fincas = []) {
    const target = document.querySelector('#farms-list');
    if (!target) return;
    farmsData = Array.isArray(fincas) ? fincas : [];
    const empty = document.querySelector('#farms-empty');
    if (empty) empty.hidden = farmsData.length !== 0;
    target.innerHTML = farmsData.map((finca) => `<article class="resource-item"><div class="resource-item__top"><h3>${escapeHtml(finca.nombre || 'Finca sin nombre')}</h3><span class="resource-badge">Activa</span></div><p>${finca.direccion ? 'Tiene dirección registrada.' : 'Dirección pendiente de completar.'}</p><div class="resource-item__actions"><button class="activity-button" type="button" data-edit-farm="${Number(finca.fincaId)}">Editar</button><button class="activity-button" type="button" data-remove-farm="${Number(finca.fincaId)}">Desactivar</button></div></article>`).join('');
    target.querySelectorAll('[data-edit-farm]').forEach((button) => button.addEventListener('click', () => openFarmModal(Number(button.dataset.editFarm), button)));
    target.querySelectorAll('[data-remove-farm]').forEach((button) => button.addEventListener('click', () => changeFarmState(Number(button.dataset.removeFarm), false)));
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
    if (!producer || producer.estado === 'NO_CONFIGURADO') {
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
        const summary = document.querySelector('#summary-farms');
        if (summary) summary.textContent = String(response.data?.fincas?.length ?? 0);
        setFarmView('content');
    } catch (error) {
        if (error?.status === 401) window.location.assign('entrar?next=mi-actividad');
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
    target.innerHTML = vehiclesData.map((vehicle) => `<article class="resource-item"><div class="resource-item__top"><h3>${escapeHtml(vehicle.placa)}</h3><span class="activity-state" data-state="${escapeHtml(vehicle.estado)}">${escapeHtml(vehicle.estado)}</span></div><p>${escapeHtml(vehicle.modelo)} · VIN ${escapeHtml(vehicle.vin)}</p><div class="resource-item__actions"><button class="activity-button" type="button" data-edit-vehicle="${vehicle.vehiculoId}" ${vehicle.estado !== 'ACTIVO' ? 'disabled' : ''}>Editar</button>${vehicle.estado === 'ACTIVO' ? `<button class="activity-button" type="button" data-remove-vehicle="${vehicle.vehiculoId}">Desactivar</button>` : `<button class="activity-button activity-button--primary" type="button" data-restore-vehicle="${vehicle.vehiculoId}">Reactivar</button>`}</div></article>`).join('');
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
    if (!transportista || transportista.estado === 'NO_CONFIGURADO') {
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
        const summary = document.querySelector('#summary-vehicles');
        if (summary) summary.textContent = String(response.data?.vehiculos?.length ?? 0);
        setVehicleView('content');
    } catch (error) {
        if (error?.status === 401) window.location.assign('entrar?next=mi-actividad');
        else setVehicleView('error', error?.message || 'No pudimos cargar tus vehículos.');
    }
}

async function loadActivity({ quiet = false } = {}) {
    if (!quiet) setActivityView('loading');
    try {
        const response = await request(ACTIVITY_API);
        renderProfileAndSummary(response.data);
        await loadFarms();
        await loadVehicles();
        return response.data;
    } catch (error) {
        if (error?.status === 401) window.location.assign('entrar?next=mi-actividad');
        else setActivityView('error', error?.message || 'No fue posible consultar tu actividad.');
        return null;
    }
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
        await loadFarms();
        document.querySelector('#activity-status').textContent = response.message || 'Estado de la finca actualizado.';
        toast?.success(response.message || 'Estado de la finca actualizado.');
    } catch (error) {
        document.querySelector('#activity-status').textContent = error?.message || 'No fue posible actualizar la finca.';
        toast?.error(error?.message || 'No fue posible actualizar la finca.');
    }
}

async function changeCapability(button) {
    const id = button.dataset.toggleCapability;
    const nextActive = button.dataset.nextActive === 'true';
    if (!id || pendingChanges.has(id)) return;
    pendingChanges.add(id);
    button.disabled = true;
    const status = document.querySelector('#activity-status');
    if (status) status.textContent = nextActive ? 'Reactivando actividad…' : 'Desactivando actividad…';
    try {
        const response = await request(ACTIVITY_API, { method: 'PATCH', body: JSON.stringify({ contexto: id, activo: nextActive }) });
        const refreshed = await loadActivity({ quiet: true });
        if (status && refreshed) status.textContent = response.message || 'Actividad actualizada correctamente.';
        if (refreshed) toast?.success(response.message || 'Actividad actualizada correctamente.');
    } catch (error) {
        if (status) status.textContent = error?.message || 'No fue posible cambiar la actividad.';
        toast?.error(error?.message || 'No fue posible cambiar la actividad.');
        renderActivities(activityData?.capacidades ?? {});
    } finally {
        pendingChanges.delete(id);
        const current = [...document.querySelectorAll('[data-toggle-capability]')].find((node) => node.dataset.toggleCapability === id);
        if (current) current.disabled = false;
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
        const options = { method: editingVehicleId ? 'PUT' : 'POST', body: JSON.stringify(editingVehicleId ? { ...datos, vehiculoId: editingVehicleId } : datos) };
        const response = await request(VEHICLES_API, options);
        closeVehicleModal();
        await loadVehicles();
        document.querySelector('#activity-status').textContent = response.message || 'Vehículo guardado correctamente.';
        toast?.success(response.message || 'Vehículo guardado correctamente.');
    } catch (error) {
        setFormErrors(error?.errors ?? {});
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
    document.querySelector('#vehicle-add')?.addEventListener('click', () => openVehicleModal());
    document.querySelector('#vehicles-retry')?.addEventListener('click', loadVehicles);
    document.querySelector('#vehicle-form')?.addEventListener('submit', saveVehicle);
    document.querySelector('#vehicle-close')?.addEventListener('click', closeVehicleModal);
    document.querySelector('#vehicle-cancel')?.addEventListener('click', closeVehicleModal);
    document.querySelector('#vehicle-modal')?.addEventListener('cancel', (event) => { event.preventDefault(); closeVehicleModal(); });
}

function initializeFarmUi() {
    document.querySelector('#farm-add')?.addEventListener('click', () => openFarmModal());
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
    const params = new URLSearchParams(window.location.search);
    if (params.get('bienvenida') === '1') document.querySelector('#welcome-banner').hidden = false;
    document.querySelector('#activity-retry')?.addEventListener('click', () => loadActivity());
    loadActivity();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
