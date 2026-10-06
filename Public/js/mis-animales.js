// Mis animales (P2-4): inventario del vendedor en Mi panel. Registrar un animal sin publicarlo,
// llevar su historial de vacunas y publicarlo cuando quiera venderlo.
import { request } from './shared/api.js';
import { errorArete, formatearArete } from './shared/arete.js';
import { formatDate } from './explore.js?v=foto-3';
import { montarCampoFoto } from './shared/foto-campo.js?v=foto-campo-1';

const API = 'api/v1/mi-animales';
const VACUNAS_API = 'api/v1/mi-animales/vacunas';
const ESTADOS = { ACTIVO: 'En inventario', PUBLICADO: 'Publicado', VENDIDO: 'Vendido', INACTIVO: 'Inactivo' };
const SEXO_POR_TIPO = { H: 'HEMBRA', M: 'MACHO' };

const $ = (selector) => document.querySelector(selector);
const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]));

/** Nombre corto del animal: arete, o tipo y raza, o su número. */
export function nombreAnimal(animal) {
    if (animal.arete) return `Arete ${formatearArete(animal.arete)}`;
    const partes = [animal.tipo?.nombre, animal.raza].filter(Boolean);
    return partes.length ? partes.join(' · ') : `Animal #${Number(animal.animalId)}`;
}

/** Botones de cada fila: vacunas solo si sigue en el inventario; publicar solo si no tiene publicación abierta. */
export function accionesAnimal(animal) {
    const id = Number(animal.animalId);
    const boton = (accion, texto) => `<button class="activity-button activity-button--text" type="button" data-animal-accion="${accion}" data-animal-id="${id}">${texto}</button>`;
    const vigente = animal.estado === 'ACTIVO' || animal.estado === 'PUBLICADO';
    const publicacion = Number(animal.publicacion?.publicacionId);
    return [
        vigente && boton('vacunas', 'Vacunas'),
        animal.estado === 'ACTIVO' && boton('publicar', 'Publicar'),
        animal.estado === 'PUBLICADO' && publicacion > 0 && `<a class="activity-button activity-button--text" href="explorar?publicacion=${publicacion}">Ver publicación</a>`,
    ].filter(Boolean).join('');
}

function detalle(animal) {
    return [
        animal.especie?.nombre,
        animal.sexo && animal.sexo.charAt(0) + animal.sexo.slice(1).toLowerCase(),
        animal.peso != null && `${animal.peso} kg`,
        animal.fechaNacimiento && `nació ${formatDate(animal.fechaNacimiento)}${animal.fechaNacimientoEstimada ? ' (aprox.)' : ''}`,
        `${Number(animal.vacunas) || 0} vacuna${Number(animal.vacunas) === 1 ? '' : 's'}`,
    ].filter(Boolean).join(' · ');
}

function abrir(dialog) {
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else { dialog.hidden = false; dialog.setAttribute('open', ''); }
}

function cerrar(dialog, foco) {
    if (typeof dialog.close === 'function' && dialog.open) dialog.close();
    else { dialog.hidden = true; dialog.removeAttribute('open'); }
    foco?.focus?.();
}

function mostrarErrores(form, errores = {}) {
    form.querySelectorAll('[data-animal-error]').forEach((nodo) => { nodo.textContent = ''; });
    form.querySelectorAll('[aria-invalid="true"]').forEach((nodo) => nodo.removeAttribute('aria-invalid'));
    for (const [campo, mensaje] of Object.entries(errores)) {
        const nodo = form.querySelector(`[data-animal-error="${campo}"]`);
        if (nodo) nodo.textContent = String(mensaje);
        form.elements.namedItem(campo)?.setAttribute?.('aria-invalid', 'true');
    }
}

/** Lee el formulario como JSON: los vacíos no se envían y los números van como número. */
function cuerpoDe(form, numericos = []) {
    const cuerpo = {};
    for (const [clave, valor] of new FormData(form)) {
        const texto = String(valor).trim();
        if (texto === '' || valor instanceof File) continue;
        cuerpo[clave] = numericos.includes(clave) ? Number(texto) : texto;
    }
    return cuerpo;
}

/**
 * Monta la sección. `fincas()` devuelve las fincas activas del panel; `alPublicar()` recarga "Mis publicaciones".
 * Devuelve `cargar()`, que la sección llama cuando la actividad Vendedor está activa.
 */
export function montarMisAnimales({ toast, fincas, alPublicar }) {
    const panel = $('#animals-panel');
    if (!panel) return { cargar: async () => {} };
    const lista = $('#animals-list');
    const formAnimal = $('#animal-form');
    const formPublicar = $('#animal-publish-form');
    const formVacuna = $('#animal-vaccine-form');
    const foto = montarCampoFoto(formPublicar.querySelector('[data-foto-campo="animal-publish"]'));
    let animales = [];
    let catalogo = null;
    let animalActual = null;
    let disparador = null;

    const vista = (estado, mensaje = '') => {
        $('#animals-loading').hidden = estado !== 'loading';
        $('#animals-error').hidden = estado !== 'error';
        if (mensaje) $('#animals-error-message').textContent = mensaje;
    };

    const render = (datos) => {
        animales = Array.isArray(datos) ? datos : [];
        $('#animals-count').textContent = animales.length ? ` (${animales.length})` : '';
        lista.innerHTML = animales.map((animal) => `<article class="panel-row"><div><h3>${escapeHtml(nombreAnimal(animal))}</h3><p>${escapeHtml(detalle(animal))}</p></div><div class="panel-row__actions"><span class="activity-state" data-state="${escapeHtml(animal.estado)}">${escapeHtml(ESTADOS[animal.estado] ?? animal.estado)}</span>${accionesAnimal(animal)}</div></article>`).join('');
        $('#animals-empty').hidden = animales.length > 0;
    };

    const cargar = async () => {
        vista('loading');
        try {
            render((await request(API)).data?.animales);
            vista('content');
        } catch (error) {
            vista('error', error?.message || 'No pudimos cargar tus animales.');
        }
    };

    const cargarCatalogo = async () => {
        if (catalogo) return catalogo;
        catalogo = (await request('api/v1/catalogos')).data;
        const especie = formAnimal.elements.especieId;
        for (const item of catalogo.especies) especie.append(new Option(item.nombre, String(item.especieId)));
        for (const item of catalogo.vacunas ?? []) formVacuna.elements.vacunaId.append(new Option(item.nombre, String(item.vacunaId)));
        return catalogo;
    };

    // Especie -> tipo y raza; el tipo fija el sexo; los partos solo aplican a hembras (igual que Publicar).
    const { especieId, tipoId, razaId, sexo, partos, arete, fechaNacimiento, fechaNacimientoEstimada } = formAnimal.elements;
    const llenar = (select, items, clave, vacio) => {
        select.replaceChildren(new Option(vacio, ''));
        items.forEach((item) => select.append(new Option(item.nombre, String(item[clave]))));
    };
    const actualizarSexo = () => {
        const tipo = catalogo?.tipos.find((item) => String(item.tipoId) === tipoId.value);
        const fijo = SEXO_POR_TIPO[tipo?.sexo] ?? null;
        if (fijo) sexo.value = fijo;
        sexo.disabled = fijo !== null;
        const hembra = sexo.value === 'HEMBRA';
        formAnimal.querySelector('[data-animal-partos]').hidden = !hembra;
        if (!hembra) partos.value = '';
    };
    especieId.addEventListener('change', () => {
        const id = Number(especieId.value);
        const hay = id > 0;
        llenar(tipoId, hay ? catalogo.tipos.filter((item) => item.especieId === id) : [], 'tipoId', hay ? 'Sin indicar' : 'Elige primero la especie');
        llenar(razaId, hay ? catalogo.razas.filter((item) => item.especieId === id) : [], 'razaId', hay ? 'Sin indicar' : 'Elige primero la especie');
        tipoId.disabled = !hay;
        razaId.disabled = !hay;
        actualizarSexo();
    });
    tipoId.addEventListener('change', actualizarSexo);
    sexo.addEventListener('change', actualizarSexo);
    arete.addEventListener('input', () => {
        arete.value = formatearArete(arete.value);
        formAnimal.querySelector('[data-animal-error="arete"]').textContent = errorArete(arete.value) ?? '';
    });
    fechaNacimiento.addEventListener('input', () => {
        fechaNacimientoEstimada.disabled = !fechaNacimiento.value;
        if (!fechaNacimiento.value) fechaNacimientoEstimada.checked = false;
    });

    const abrirRegistro = async (boton) => {
        disparador = boton;
        try { await cargarCatalogo(); } catch {
            toast?.error('No pudimos cargar los catálogos. Intenta de nuevo.');
            return;
        }
        formAnimal.reset();
        especieId.dispatchEvent(new Event('change'));
        fechaNacimientoEstimada.disabled = true;
        fechaNacimiento.max = new Date().toLocaleDateString('en-CA');
        mostrarErrores(formAnimal);
        $('#animal-form-status').textContent = '';
        abrir($('#animal-modal'));
        especieId.focus();
    };

    const enviar = async (form, estado, accion) => {
        const boton = form.querySelector('[type="submit"]');
        boton.disabled = true;
        form.setAttribute('aria-busy', 'true');
        estado.textContent = 'Guardando…';
        try {
            await accion();
        } catch (error) {
            mostrarErrores(form, error?.errors ?? {});
            estado.textContent = error?.message || 'No fue posible guardar.';
        } finally {
            boton.disabled = false;
            form.setAttribute('aria-busy', 'false');
        }
    };

    formAnimal.addEventListener('submit', (event) => {
        event.preventDefault();
        const problema = errorArete(arete.value);
        if (problema) { mostrarErrores(formAnimal, { arete: problema }); return; }
        enviar(formAnimal, $('#animal-form-status'), async () => {
            const cuerpo = cuerpoDe(formAnimal, ['especieId', 'tipoId', 'razaId', 'partos', 'peso', 'edadMeses']);
            if (sexo.disabled) delete cuerpo.sexo;
            if (cuerpo.arete) cuerpo.arete = cuerpo.arete.replace(/\D/g, '');
            if (cuerpo.fechaNacimiento) cuerpo.fechaNacimientoEstimada = fechaNacimientoEstimada.checked;
            const respuesta = await request(API, { method: 'POST', body: JSON.stringify(cuerpo) });
            render(respuesta.data?.animales);
            cerrar($('#animal-modal'), disparador);
            toast?.success(respuesta.message || 'Animal registrado.');
        });
    });

    const abrirPublicar = (animal, boton) => {
        const disponibles = fincas();
        if (disponibles.length === 0) { toast?.error('Primero registra una finca: tus publicaciones salen desde ahí.'); return; }
        animalActual = animal;
        disparador = boton;
        formPublicar.reset();
        const finca = formPublicar.elements.fincaNombre;
        finca.replaceChildren(...disponibles.map((item) => new Option(item.nombre, item.nombre)));
        formPublicar.elements.titulo.value = [animal.tipo?.nombre, animal.raza].filter(Boolean).join(' ') || '';
        foto.reiniciar(null);
        mostrarErrores(formPublicar);
        $('#animal-publish-status').textContent = '';
        $('#animal-publish-name').textContent = nombreAnimal(animal);
        abrir($('#animal-publish-modal'));
        formPublicar.elements.titulo.focus();
    };

    formPublicar.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!formPublicar.elements.titulo.value.trim()) { mostrarErrores(formPublicar, { titulo: 'Este dato es obligatorio.' }); return; }
        enviar(formPublicar, $('#animal-publish-status'), async () => {
            const imagenUrl = await foto.resolver();
            const cuerpo = { ...cuerpoDe(formPublicar, ['precio']), animalId: animalActual.animalId, accion: 'PUBLICAR', ...(imagenUrl && { imagenUrl }) };
            const respuesta = await request(API, { method: 'PATCH', body: JSON.stringify(cuerpo) });
            render(respuesta.data?.animales);
            cerrar($('#animal-publish-modal'), disparador);
            toast?.success(respuesta.message || 'Animal publicado.');
            await alPublicar?.();
        });
    });

    const renderVacunas = (vacunas = []) => {
        $('#animal-vaccines-list').innerHTML = vacunas.length
            ? vacunas.map((v) => `<li><strong>${escapeHtml(v.vacuna)}</strong> · ${escapeHtml(formatDate(v.fecha))}${v.dosis ? ` · ${escapeHtml(v.dosis)}` : ''}${v.proximaDosis ? ` · próxima ${escapeHtml(formatDate(v.proximaDosis))}` : ''}</li>`).join('')
            : '<li>Sin vacunas registradas.</li>';
    };

    const abrirVacunas = async (animal, boton) => {
        animalActual = animal;
        disparador = boton;
        formVacuna.reset();
        formVacuna.elements.fecha.max = new Date().toLocaleDateString('en-CA');
        mostrarErrores(formVacuna);
        $('#animal-vaccine-status').textContent = '';
        $('#animal-vaccines-name').textContent = nombreAnimal(animal);
        $('#animal-vaccines-list').innerHTML = '<li>Cargando…</li>';
        abrir($('#animal-vaccines-modal'));
        try {
            await cargarCatalogo();
            renderVacunas((await request(`${VACUNAS_API}?animalId=${Number(animal.animalId)}`)).data?.vacunas);
        } catch (error) {
            $('#animal-vaccines-list').innerHTML = `<li>${escapeHtml(error?.message || 'No pudimos cargar el historial.')}</li>`;
        }
    };

    formVacuna.addEventListener('submit', (event) => {
        event.preventDefault();
        enviar(formVacuna, $('#animal-vaccine-status'), async () => {
            const cuerpo = { ...cuerpoDe(formVacuna, ['vacunaId']), animalId: animalActual.animalId };
            const respuesta = await request(VACUNAS_API, { method: 'POST', body: JSON.stringify(cuerpo) });
            renderVacunas(respuesta.data?.vacunas);
            formVacuna.reset();
            $('#animal-vaccine-status').textContent = respuesta.message || 'Vacuna registrada.';
            await cargar();
        });
    });

    $('#animal-add').addEventListener('click', (event) => abrirRegistro(event.currentTarget));
    $('#animals-retry').addEventListener('click', cargar);
    lista.addEventListener('click', (event) => {
        const boton = event.target.closest('[data-animal-accion]');
        const animal = boton && animales.find((item) => Number(item.animalId) === Number(boton.dataset.animalId));
        if (!animal) return;
        if (boton.dataset.animalAccion === 'publicar') abrirPublicar(animal, boton);
        else abrirVacunas(animal, boton);
    });
    for (const id of ['animal-modal', 'animal-publish-modal', 'animal-vaccines-modal']) {
        const dialog = $(`#${id}`);
        dialog.addEventListener('cancel', (event) => { event.preventDefault(); cerrar(dialog, disparador); });
        dialog.querySelectorAll('[data-animal-cerrar]').forEach((boton) => boton.addEventListener('click', () => cerrar(dialog, disparador)));
    }

    return { cargar };
}

