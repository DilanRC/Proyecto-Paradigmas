// Catálogos del animal (P2-6): especies, tipos, razas y vacunas. api.js se versiona
// junto con la cadena de caché admin (MEMORIA.md, Cuidados #11).
import { request } from './shared/api.js?v=auth-gate-10';
import { createDialogController } from './shared/dialog.js';
import { bindFormErrors, createSubmitGuard, setSaving } from './shared/form.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/v1/admin/catalogos';
const LISTAS = { ESPECIE: 'especies', TIPO: 'tipos', RAZA: 'razas', VACUNA: 'vacunas' };
const SINGULAR = { ESPECIE: 'especie', TIPO: 'tipo de animal', RAZA: 'raza', VACUNA: 'vacuna' };
const SEXOS = { H: 'Hembra', M: 'Macho' };

/** Filas visibles de un catálogo según el filtro de estado. Exportado para pruebas. */
export function filtrarCatalogo(datos, catalogo, estado = 'TODOS') {
    const lista = Array.isArray(datos?.[LISTAS[catalogo]]) ? datos[LISTAS[catalogo]] : [];
    if (estado === 'TODOS') return lista;
    return lista.filter((item) => item.activo === (estado === 'ACTIVO'));
}

/** Tipos y razas pertenecen a una especie; solo los tipos llevan sexo. Exportado para pruebas. */
export function camposCatalogo(catalogo) {
    return { especie: catalogo === 'TIPO' || catalogo === 'RAZA', sexo: catalogo === 'TIPO' };
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const el = {
        body: $('#cuerpo-catalogo'), empty: $('#estado-vacio'), error: $('#estado-error'), errorMessage: $('#mensaje-error'),
        retry: $('#reintentar'), loading: $('#estado-carga'), panel: $('#panel-catalogos'), total: $('#total-catalogo'),
        refresh: $('#actualizar-lista'), add: $('#crear-registro'), filtroCatalogo: $('#filtro-catalogo'), filtroEstado: $('#filtro-estado'),
        columnaEspecie: $('#columna-especie'), columnaSexo: $('#columna-sexo'),
        modal: $('#modal-registro'), form: $('#formulario-registro'), title: $('#titulo-registro'), subtitle: $('#subtitulo-registro'),
        close: $('#cerrar-registro'), cancel: $('#cancelar-registro'), save: $('#guardar-registro'),
        nombre: $('#nombre'), especie: $('#especieId'), sexo: $('#sexo'), campoEspecie: $('#campo-especie'), campoSexo: $('#campo-sexo'),
        confirmModal: $('#modal-desactivar'), confirmMessage: $('#mensaje-desactivar'),
        confirmCancel: $('#cancelar-desactivacion'), confirmOk: $('#confirmar-desactivacion'),
    };
    const toast = createToast({ polite: $('#toast-status'), assertive: $('#toast-alert') });
    const errores = bindFormErrors(el.form);
    const submit = createSubmitGuard();
    const statusChange = createSubmitGuard();
    const dialogs = createDialogController({ isBusy: () => submit.busy || statusChange.busy });
    let datos = {};
    let editando = null;
    let pendiente = null;

    const catalogo = () => el.filtroCatalogo.value;
    const especieNombre = (id) => (datos.especies ?? []).find((item) => item.id === id)?.nombre ?? '—';

    async function list(respuesta = null) {
        el.loading.hidden = false;
        el.error.hidden = true;
        el.panel.setAttribute('aria-busy', 'true');
        el.refresh.disabled = true;
        try {
            datos = respuesta ?? (await request(API_URL)).data ?? {};
            render();
        } catch (error) {
            el.body.replaceChildren();
            el.errorMessage.textContent = error.message;
            el.error.hidden = false;
            el.total.textContent = 'No fue posible cargar los catálogos.';
        } finally {
            el.loading.hidden = true;
            el.panel.setAttribute('aria-busy', 'false');
            el.refresh.disabled = false;
        }
    }

    function cell(label, text = '') {
        const td = document.createElement('td');
        td.dataset.label = label;
        td.textContent = text;
        return td;
    }

    function render() {
        const campos = camposCatalogo(catalogo());
        el.columnaEspecie.hidden = !campos.especie;
        el.columnaSexo.hidden = !campos.sexo;
        const filas = filtrarCatalogo(datos, catalogo(), el.filtroEstado.value);
        const todos = filtrarCatalogo(datos, catalogo());
        el.total.textContent = `${todos.filter((item) => item.activo).length} activos de ${todos.length}.`;
        el.empty.hidden = filas.length > 0;
        el.body.replaceChildren(...filas.map((item) => {
            const tr = document.createElement('tr');
            const especie = cell('Especie', especieNombre(item.especieId));
            const sexo = cell('Sexo', SEXOS[item.sexo] ?? 'Cualquiera');
            especie.hidden = !campos.especie;
            sexo.hidden = !campos.sexo;
            const estado = cell('Estado');
            const badge = document.createElement('span');
            badge.className = `badge badge--${item.activo ? 'active' : 'inactive'}`;
            badge.textContent = item.activo ? 'Activo' : 'Inactivo';
            estado.appendChild(badge);
            const acciones = cell('Acciones');
            acciones.className = 'row-actions';
            for (const [accion, texto] of [['editar', 'Editar'], item.activo ? ['desactivar', 'Desactivar'] : ['reactivar', 'Reactivar']]) {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = `action action--${accion}`;
                boton.dataset.action = accion;
                boton.dataset.id = String(item.id);
                boton.textContent = texto;
                acciones.appendChild(boton);
            }
            tr.append(cell('Nombre', item.nombre), especie, sexo, estado, acciones);
            return tr;
        }));
    }

    function openForm(item = null) {
        editando = item;
        const campos = camposCatalogo(catalogo());
        errores.clearErrors();
        el.form.reset();
        el.subtitle.textContent = item ? 'Editar registro' : 'Nuevo registro';
        el.title.textContent = `${item ? 'Editar' : 'Agregar'} ${SINGULAR[catalogo()]}`;
        el.nombre.value = item?.nombre ?? '';
        el.especie.replaceChildren(...(datos.especies ?? []).filter((e) => e.activo || e.id === item?.especieId)
            .map((e) => new Option(e.nombre, String(e.id))));
        if (item?.especieId) el.especie.value = String(item.especieId);
        el.sexo.value = item?.sexo ?? '';
        // La especie y el sexo se fijan al crear: los animales ya guardados dependen de ellos.
        el.campoEspecie.hidden = !campos.especie;
        el.campoSexo.hidden = !campos.sexo;
        el.especie.disabled = !campos.especie || item !== null;
        el.sexo.disabled = !campos.sexo || item !== null;
        dialogs.open(el.modal, { focus: el.nombre });
    }

    function closeForm() {
        if (submit.busy) return;
        dialogs.close(el.modal);
        errores.clearErrors();
    }

    async function enviar(metodo, cuerpo) {
        const response = await request(API_URL, { method: metodo, body: JSON.stringify({ catalogo: catalogo(), ...cuerpo }) });
        toast.success(response.message);
        await list(response.data);
    }

    function save(event) {
        event.preventDefault();
        return submit.run(async () => {
            errores.clearErrors();
            if (!el.nombre.value.trim()) { errores.showErrors({ nombre: 'El nombre es obligatorio.' }); return; }
            setSaving(el.form, true, { submitButton: el.save });
            try {
                const campos = camposCatalogo(catalogo());
                await enviar(editando ? 'PATCH' : 'POST', editando
                    ? { id: editando.id, nombre: el.nombre.value.trim() }
                    : {
                        nombre: el.nombre.value.trim(),
                        ...(campos.especie && { especieId: Number(el.especie.value) || null }),
                        ...(campos.sexo && { sexo: el.sexo.value || null }),
                    });
                dialogs.close(el.modal);
            } catch (error) {
                if (error.errors) errores.showErrors(error.errors);
                toast.error(error.message);
            } finally {
                setSaving(el.form, false, { submitButton: el.save });
            }
        });
    }

    function changeStatus(item, activo) {
        return statusChange.run(async () => {
            const controls = document.querySelectorAll('[data-action], #confirmar-desactivacion');
            controls.forEach((control) => { control.disabled = true; });
            try {
                await enviar('PATCH', { id: item.id, activo });
                if (!activo) dialogs.close(el.confirmModal);
            } catch (error) {
                toast.error(error.message);
            } finally {
                controls.forEach((control) => { control.disabled = false; });
            }
        });
    }

    el.body.addEventListener('click', (event) => {
        const boton = event.target.closest('[data-action]');
        const item = boton && filtrarCatalogo(datos, catalogo()).find((fila) => String(fila.id) === boton.dataset.id);
        if (!item) return;
        if (boton.dataset.action === 'editar') { openForm(item); return; }
        if (boton.dataset.action === 'reactivar') { changeStatus(item, true); return; }
        pendiente = item;
        el.confirmMessage.textContent = `«${item.nombre}» dejará de aparecer en los formularios. Lo ya registrado no cambia.`;
        dialogs.open(el.confirmModal, { focus: el.confirmCancel });
    });
    el.add.addEventListener('click', () => openForm());
    el.refresh.addEventListener('click', () => list());
    el.retry.addEventListener('click', () => list());
    el.filtroCatalogo.addEventListener('change', render);
    el.filtroEstado.addEventListener('change', render);
    el.form.addEventListener('submit', save);
    el.form.addEventListener('input', errores.clearControlError);
    el.close.addEventListener('click', closeForm);
    el.cancel.addEventListener('click', closeForm);
    el.confirmCancel.addEventListener('click', () => { if (!statusChange.busy) dialogs.close(el.confirmModal); });
    el.confirmOk.addEventListener('click', () => { if (pendiente) changeStatus(pendiente, false); });
    for (const modal of [el.modal, el.confirmModal]) {
        modal.addEventListener('click', dialogs.handleBackdropClick);
        modal.addEventListener('close', dialogs.restoreFocus);
    }

    list();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
