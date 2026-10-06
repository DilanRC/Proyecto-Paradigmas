// Página Fletes: ofertas de flete cercanas (cualquier persona con sesión) y
// "Mis ofertas" para quien tiene Transportista activo. API: api/v1/fletes y api/v1/mi-ofertas.
import { request } from './shared/api.js';
import { endExpiredSession, readAuthSession } from './shared/supabase-auth.js?v=session-2';
import { UBICACION_USUARIO_EVENT, capturarUbicacionAutomatica, leerUbicacionUsuario } from './shared/ubicacion-sesion.js';
import { formatLocation, formatPrice, safeImageUrl } from './explore.js?v=foto-3';
import { montarEditorDireccion } from './shared/editor-direccion.js?v=oferta-1';

const FLETES_API = 'api/v1/fletes';
const OFERTAS_API = 'api/v1/mi-ofertas';
const VEHICLES_API = 'api/v1/mi-vehiculos';

const $ = (selector) => document.querySelector(selector);
let ofertas = [];
let vehiculos = [];
let editandoId = null;
let editor = null;
let disparador = null;
let turnoCercanos = 0;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
}

function miniatura(url) {
    const segura = safeImageUrl(url);
    return segura
        ? `<img class="panel-thumb" src="${escapeHtml(segura)}" alt="" loading="lazy" referrerpolicy="no-referrer">`
        : '<span class="panel-thumb panel-thumb--empty" aria-hidden="true"><i class="fa-solid fa-truck"></i></span>';
}

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

// ---------- Fletes disponibles (cercanos a la ubicación de la persona) ----------

function filaCercana(oferta) {
    const km = Number(oferta.distanciaKm).toLocaleString('es-CR', { maximumFractionDigits: 1 });
    return `<article class="panel-row panel-row--media">${miniatura(oferta.vehiculo?.fotoUrl)}<div><h3>${escapeHtml(oferta.vehiculo?.modelo || 'Flete')} · hasta ${Number(oferta.capacidad)} cabezas</h3><p>${escapeHtml(formatLocation(oferta.zona))} · a ${km} km (cubre ${Number(oferta.radioKm)} km)</p><p>${escapeHtml(oferta.transportista || '')} · ${escapeHtml(formatPrice(oferta.precio))}</p>${oferta.descripcion ? `<p>${escapeHtml(oferta.descripcion)}</p>` : ''}</div></article>`;
}

async function cargarCercanos() {
    const estado = $('#cercanos-estado');
    const lista = $('#cercanos-lista');
    const boton = $('#cercanos-ubicacion');
    const cuenta = $('#cercanos-count');
    const ubicacion = leerUbicacionUsuario();
    if (!ubicacion) {
        lista.replaceChildren();
        cuenta.textContent = '';
        estado.textContent = 'Necesitamos tu ubicación para mostrarte los fletes que cubren tu zona.';
        estado.hidden = false;
        boton.hidden = false;
        return;
    }
    boton.hidden = true;
    estado.hidden = false;
    estado.textContent = 'Buscando fletes cerca de ti…';
    const turno = ++turnoCercanos;
    const capacidad = Math.min(200, Math.max(1, Math.trunc(Number($('#cercanos-capacidad').value)) || 1));
    const consulta = new URLSearchParams({
        latitud: ubicacion.latitud, longitud: ubicacion.longitud, capacidadMinima: String(capacidad), tamanoPagina: '50',
    });
    try {
        const respuesta = await request(`${FLETES_API}?${consulta}`);
        if (turno !== turnoCercanos) return;
        const encontradas = respuesta.data?.ofertas ?? [];
        lista.innerHTML = encontradas.map(filaCercana).join('');
        cuenta.textContent = encontradas.length ? String(respuesta.data.total ?? encontradas.length) : '';
        estado.hidden = encontradas.length > 0;
        if (!encontradas.length) estado.textContent = 'Todavía no hay fletes que cubran tu zona con esa capacidad.';
    } catch (error) {
        if (turno !== turnoCercanos) return;
        if (error?.status === 401) { endExpiredSession(); return; }
        lista.replaceChildren();
        estado.hidden = false;
        estado.textContent = error?.message || 'No pudimos cargar los fletes.';
    }
}

async function usarMiUbicacion() {
    const estado = $('#cercanos-estado');
    estado.textContent = 'Obteniendo tu ubicación…';
    try {
        await capturarUbicacionAutomatica({ forzar: true });
        await cargarCercanos();
    } catch (error) {
        estado.textContent = error?.kind === 'unsupported'
            ? 'Este navegador no ofrece geolocalización.'
            : 'No pudimos obtener tu ubicación. Revisa el permiso de ubicación del navegador e inténtalo de nuevo.';
    }
}

// ---------- Mis ofertas (Transportista activo) ----------

function filaPropia(oferta) {
    const activa = oferta.estado === 'ACTIVA';
    const boton = (accion, etiqueta) => `<button class="activity-button activity-button--text" type="button" data-oferta-accion="${accion}" data-oferta-id="${oferta.ofertaId}">${etiqueta}</button>`;
    return `<article class="panel-row panel-row--media">${miniatura(oferta.vehiculo?.fotoUrl)}<div><h3>${escapeHtml(oferta.vehiculo?.modelo)} · ${escapeHtml(oferta.vehiculo?.placa)}</h3><p>${escapeHtml(formatLocation(oferta.zona))} · hasta ${Number(oferta.capacidad)} cabezas · cubre ${Number(oferta.radioKm)} km</p><p>${escapeHtml(formatPrice(oferta.precio))}</p></div><div class="panel-row__actions"><span class="activity-state" data-state="${activa ? 'ACTIVO' : 'INACTIVO'}">${activa ? 'Activa' : 'Pausada'}</span>${boton('editar', 'Editar')}${boton(activa ? 'PAUSADA' : 'ACTIVA', activa ? 'Pausar' : 'Reactivar')}</div></article>`;
}

function mostrarMisOfertas() {
    const estado = $('#ofertas-estado');
    $('#ofertas-lista').innerHTML = ofertas.map(filaPropia).join('');
    $('#ofertas-count').textContent = ofertas.length ? String(ofertas.length) : '';
    $('#ofertas-sin-vehiculo').hidden = vehiculos.length > 0;
    $('#oferta-nueva').hidden = vehiculos.length === 0;
    estado.hidden = ofertas.length > 0 || vehiculos.length === 0;
    if (!estado.hidden) estado.textContent = 'Aún no has publicado ofertas. Publica la primera para que los clientes de tu zona te encuentren.';
}

async function cargarMisOfertas() {
    $('#ofertas-panel').hidden = false;
    const estado = $('#ofertas-estado');
    try {
        const [propias, propiosVehiculos] = await Promise.all([request(OFERTAS_API), request(VEHICLES_API)]);
        ofertas = propias.data?.ofertas ?? [];
        vehiculos = (propiosVehiculos.data?.vehiculos ?? []).filter((vehiculo) => vehiculo.estado === 'ACTIVO');
        mostrarMisOfertas();
    } catch (error) {
        if (error?.status === 401) { endExpiredSession(); return; }
        estado.hidden = false;
        estado.textContent = error?.message || 'No pudimos cargar tus ofertas.';
    }
}

function mostrarErrores(errores = {}) {
    document.querySelectorAll('[data-oferta-error]').forEach((nodo) => { nodo.textContent = ''; });
    document.querySelectorAll('#oferta-form [aria-invalid="true"]').forEach((nodo) => nodo.removeAttribute('aria-invalid'));
    for (const [campo, mensaje] of Object.entries(errores)) {
        const clave = campo.startsWith('direccion') ? 'direccion' : campo;
        const nodo = document.querySelector(`[data-oferta-error="${clave}"]`);
        if (nodo && !nodo.textContent) nodo.textContent = String(mensaje);
        document.querySelector(`#oferta-form [name="${clave}"]`)?.setAttribute('aria-invalid', 'true');
    }
}

function liberarEditor() {
    editor?.destruir();
    editor = null;
}

function abrirOferta(id = null, trigger = null) {
    const dialogo = $('#oferta-modal');
    const form = $('#oferta-form');
    const oferta = ofertas.find((item) => item.ofertaId === id);
    liberarEditor();
    editandoId = oferta ? id : null;
    disparador = trigger;
    $('#oferta-modal-title').textContent = oferta ? 'Editar oferta' : 'Publicar oferta';
    $('#oferta-save').textContent = oferta ? 'Guardar cambios' : 'Publicar oferta';
    form.elements.vehiculoId.innerHTML = '<option value="">Elige un vehículo</option>'
        + vehiculos.map((vehiculo) => `<option value="${vehiculo.vehiculoId}">${escapeHtml(vehiculo.placa)} · ${escapeHtml(vehiculo.modelo)}</option>`).join('');
    form.elements.vehiculoId.value = oferta ? String(oferta.vehiculo.vehiculoId) : (vehiculos.length === 1 ? String(vehiculos[0].vehiculoId) : '');
    form.elements.radioKm.value = oferta?.radioKm ?? '';
    form.elements.capacidad.value = oferta?.capacidad ?? '';
    form.elements.precio.value = oferta?.precio ?? '';
    form.elements.descripcion.value = oferta?.descripcion ?? '';
    mostrarErrores();
    $('#oferta-form-status').textContent = '';
    editor = montarEditorDireccion($('#oferta-zona'), oferta?.zona ?? null, { titulo: 'Punto exacto de tu zona base', opcional: false, lugar: 'tu zona base' });
    if (typeof dialogo.showModal === 'function') dialogo.showModal();
    else { dialogo.hidden = false; dialogo.setAttribute('open', ''); }
    form.elements.vehiculoId.focus();
}

function cerrarOferta() {
    const dialogo = $('#oferta-modal');
    liberarEditor();
    if (typeof dialogo.close === 'function' && dialogo.open) dialogo.close();
    else { dialogo.hidden = true; dialogo.removeAttribute('open'); }
    disparador?.focus?.();
    editandoId = null;
}

async function guardarOferta(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const estado = $('#oferta-form-status');
    const guardar = $('#oferta-save');
    const numero = (valor) => (String(valor).trim() === '' ? null : Number(valor));
    const cuerpo = {
        vehiculoId: numero(form.elements.vehiculoId.value),
        radioKm: numero(form.elements.radioKm.value),
        capacidad: numero(form.elements.capacidad.value),
        precio: numero(form.elements.precio.value),
        descripcion: form.elements.descripcion.value.trim() || null,
        direccion: editor?.leer(),
    };
    if (editandoId) cuerpo.ofertaId = editandoId;
    mostrarErrores();
    guardar.disabled = true;
    form.setAttribute('aria-busy', 'true');
    estado.textContent = 'Guardando oferta…';
    try {
        const respuesta = await request(OFERTAS_API, { method: editandoId ? 'PUT' : 'POST', body: JSON.stringify(cuerpo) });
        ofertas = respuesta.data?.ofertas ?? ofertas;
        cerrarOferta();
        mostrarMisOfertas();
        $('#ofertas-estado').hidden = false;
        $('#ofertas-estado').textContent = respuesta.message || 'Oferta guardada.';
    } catch (error) {
        if (error?.status === 401) { endExpiredSession(); return; }
        mostrarErrores(error?.errors ?? {});
        estado.textContent = error?.message || 'No fue posible guardar la oferta.';
    } finally {
        guardar.disabled = false;
        form.setAttribute('aria-busy', 'false');
    }
}

async function cambiarEstado(id, estado) {
    const pregunta = estado === 'PAUSADA' ? '¿Pausar esta oferta? Dejará de mostrarse a los clientes.' : '¿Reactivar esta oferta?';
    if (!window.confirm(pregunta)) return;
    const aviso = $('#ofertas-estado');
    try {
        const respuesta = await request(OFERTAS_API, { method: 'PATCH', body: JSON.stringify({ ofertaId: id, estado }) });
        ofertas = respuesta.data?.ofertas ?? ofertas;
        mostrarMisOfertas();
        aviso.hidden = false;
        aviso.textContent = respuesta.message;
    } catch (error) {
        if (error?.status === 401) { endExpiredSession(); return; }
        aviso.hidden = false;
        aviso.textContent = error?.message || 'No fue posible actualizar la oferta.';
    }
}

function conectarEventos() {
    let espera = null;
    $('#cercanos-capacidad')?.addEventListener('input', () => {
        clearTimeout(espera);
        espera = setTimeout(cargarCercanos, 300);
    });
    $('#cercanos-filtro')?.addEventListener('submit', (event) => event.preventDefault());
    $('#cercanos-ubicacion')?.addEventListener('click', usarMiUbicacion);
    window.addEventListener(UBICACION_USUARIO_EVENT, cargarCercanos);

    $('#oferta-nueva')?.addEventListener('click', (event) => abrirOferta(null, event.currentTarget));
    $('#ofertas-lista')?.addEventListener('click', (event) => {
        const boton = event.target.closest('[data-oferta-accion]');
        if (!boton) return;
        const id = Number(boton.dataset.ofertaId);
        if (boton.dataset.ofertaAccion === 'editar') abrirOferta(id, boton);
        else cambiarEstado(id, boton.dataset.ofertaAccion);
    });
    $('#oferta-form')?.addEventListener('submit', guardarOferta);
    $('#oferta-close')?.addEventListener('click', cerrarOferta);
    $('#oferta-cancel')?.addEventListener('click', cerrarOferta);
    $('#oferta-modal')?.addEventListener('cancel', (event) => { event.preventDefault(); cerrarOferta(); });
}

// ---------- Estado de la actividad Transportista (columna lateral) ----------

async function initialize() {
    const stateBox = $('#fletes-state');
    const primary = $('#fletes-primary');
    if (!stateBox || !(primary instanceof HTMLAnchorElement)) return;

    const session = readAuthSession();

    if (!session) {
        renderState(stateBox, 'fa-solid fa-circle-info', 'Inicia sesión o crea una cuenta para ofrecer fletes.');
        primary.href = 'entrar?next=fletes';
        primary.textContent = 'Entrar para continuar';
        return;
    }

    conectarEventos();
    cargarCercanos();

    let state;
    try {
        state = stateFromActivity(await request('api/v1/actividad'));
    } catch {
        renderState(stateBox, 'fa-solid fa-triangle-exclamation', 'No pudimos comprobar el estado de tu servicio. Revisa Mi panel e inténtalo nuevamente.');
        primary.href = 'mi-actividad';
        primary.textContent = 'Abrir Mi panel';
        return;
    }

    if (state === 'ACTIVO') {
        renderState(stateBox, 'fa-solid fa-circle-check', 'Tu servicio de fletes está activo. Publica y administra tus ofertas aquí.');
        primary.href = 'mi-actividad';
        primary.textContent = 'Administrar mis vehículos';
        cargarMisOfertas();
        return;
    }

    if (state === 'INACTIVO') {
        renderState(stateBox, 'fa-solid fa-pause', 'Tu servicio de fletes está inactivo. Tu identidad y datos se conservan para poder reactivarlo.');
        primary.href = 'ajustes';
        primary.textContent = 'Reactivar servicio';
        return;
    }

    renderState(stateBox, 'fa-solid fa-circle-info', 'Aún no ofreces fletes. Activa Transportista en Ajustes → Cómo participo.');
    primary.href = 'ajustes';
    primary.textContent = 'Quiero ofrecer fletes';
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
