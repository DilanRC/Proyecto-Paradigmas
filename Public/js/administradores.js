// Administradores del panel (P3-4). api.js se versiona: un api.js viejo en caché
// carga el auth-gate anterior, que no conoce esta ruta y deja el panel oculto
// (ver MEMORIA.md, "Administrador: moderar publicaciones").
import { request } from './shared/api.js?v=auth-gate-9';
import { createDialogController } from './shared/dialog.js';
import { bindFormErrors, createSubmitGuard, setSaving } from './shared/form.js';
import { createToast } from './shared/toast.js';

const API_URL = 'api/v1/admin/administradores';

/** Texto del resumen de la lista. Exportado para pruebas. */
export function resumenAdministradores(lista = []) {
    const activos = lista.filter((admin) => admin.estado === 'ACTIVO').length;
    if (lista.length === 0) return 'No hay administradores registrados.';
    return `${activos} ${activos === 1 ? 'administrador activo' : 'administradores activos'} de ${lista.length}.`;
}

/**
 * Qué puede hacerse con cada fila. La API vuelve a comprobarlo; aquí solo se
 * evita ofrecer lo que de seguro fallaría. Exportado para pruebas.
 */
export function accionAdministrador(admin, activos) {
    if (admin.estado !== 'ACTIVO') return 'reactivar';
    if (admin.esUsted) return null; // nadie se quita su propio acceso
    if (activos <= 1) return null; // debe quedar al menos uno
    return 'desactivar';
}

function initialize() {
    const $ = (selector) => document.querySelector(selector);
    const elements = {
        body: $('#cuerpo-administradores'), error: $('#estado-error'), errorMessage: $('#mensaje-error'),
        retry: $('#reintentar'), loading: $('#estado-carga'), panel: $('#panel-administradores'),
        total: $('#total-administradores'), refresh: $('#actualizar-lista'), add: $('#agregar-administrador'),
        modal: $('#modal-administrador'), form: $('#formulario-administrador'), close: $('#cerrar-administrador'),
        cancel: $('#cancelar-administrador'), save: $('#guardar-administrador'), email: $('#correoElectronico'),
        confirmModal: $('#modal-desactivar'), confirmMessage: $('#mensaje-desactivar'),
        confirmCancel: $('#cancelar-desactivacion'), confirmOk: $('#confirmar-desactivacion'),
        toastPolite: $('#toast-status'), toastAssertive: $('#toast-alert'),
    };
    const toast = createToast({ polite: elements.toastPolite, assertive: elements.toastAssertive });
    const errores = bindFormErrors(elements.form);
    const submit = createSubmitGuard();
    const statusChange = createSubmitGuard();
    const dialogs = createDialogController({ isBusy: () => submit.busy || statusChange.busy });
    let administradores = [];
    let pendiente = null;

    async function list() {
        elements.loading.hidden = false;
        elements.error.hidden = true;
        elements.panel.setAttribute('aria-busy', 'true');
        elements.refresh.disabled = true;
        try {
            const response = await request(API_URL);
            administradores = Array.isArray(response.data?.administradores) ? response.data.administradores : [];
            render();
        } catch (error) {
            elements.body.replaceChildren();
            elements.errorMessage.textContent = error.message;
            elements.error.hidden = false;
            elements.total.textContent = 'No fue posible cargar los administradores.';
        } finally {
            elements.loading.hidden = true;
            elements.panel.setAttribute('aria-busy', 'false');
            elements.refresh.disabled = false;
        }
    }

    function render() {
        const activos = administradores.filter((admin) => admin.estado === 'ACTIVO').length;
        elements.total.textContent = resumenAdministradores(administradores);
        const fragment = document.createDocumentFragment();
        administradores.forEach((admin) => fragment.appendChild(createRow(admin, activos)));
        elements.body.replaceChildren(fragment);
    }

    function createCell(label, text = '') {
        const cell = document.createElement('td');
        cell.dataset.label = label;
        cell.textContent = text;
        return cell;
    }

    function createRow(admin, activos) {
        const row = document.createElement('tr');
        const email = createCell('Correo', admin.correoElectronico);
        if (admin.esUsted) email.append(' (usted)');
        const statusCell = createCell('Estado');
        const badge = document.createElement('span');
        badge.className = `badge badge--${admin.estado === 'ACTIVO' ? 'active' : 'inactive'}`;
        badge.textContent = admin.estado === 'ACTIVO' ? 'Activo' : 'Inactivo';
        statusCell.appendChild(badge);
        const actionsCell = createCell('Acciones');
        actionsCell.className = 'row-actions';
        const accion = accionAdministrador(admin, activos);
        if (accion) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `action action--${accion}`;
            button.dataset.action = accion;
            button.dataset.id = String(admin.administradorId);
            button.textContent = accion === 'desactivar' ? 'Desactivar' : 'Reactivar';
            actionsCell.appendChild(button);
        }
        row.append(email, statusCell, actionsCell);
        return row;
    }

    function openAdd() {
        errores.clearErrors();
        elements.form.reset();
        dialogs.open(elements.modal, { focus: elements.email });
    }

    function closeAdd() {
        if (submit.busy) return;
        dialogs.close(elements.modal);
        errores.clearErrors();
    }

    function saveAdd(event) {
        event.preventDefault();
        return submit.run(async () => {
            errores.clearErrors();
            if (!elements.form.checkValidity()) { errores.markFirstInvalid(); return; }
            setSaving(elements.form, true, { submitButton: elements.save });
            try {
                const response = await request(API_URL, {
                    method: 'POST', body: JSON.stringify({ correoElectronico: elements.email.value.trim() }),
                });
                dialogs.close(elements.modal);
                toast.success(response.message);
                await list();
            } catch (error) {
                if (error.errors) errores.showErrors(error.errors);
                toast.error(error.message);
            } finally {
                setSaving(elements.form, false, { submitButton: elements.save });
            }
        });
    }

    function changeStatus(admin, activo) {
        return statusChange.run(async () => {
            const controls = document.querySelectorAll('[data-action], #confirmar-desactivacion');
            controls.forEach((control) => { control.disabled = true; });
            try {
                const response = await request(API_URL, {
                    method: 'PATCH', body: JSON.stringify({ administradorId: admin.administradorId, activo }),
                });
                if (!activo) dialogs.close(elements.confirmModal);
                toast.success(response.message);
                await list();
            } catch (error) {
                toast.error(error.message);
            } finally {
                controls.forEach((control) => { control.disabled = false; });
            }
        });
    }

    function handleTableAction(event) {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const admin = administradores.find((item) => String(item.administradorId) === button.dataset.id);
        if (!admin) return;
        if (button.dataset.action === 'reactivar') { changeStatus(admin, true); return; }
        pendiente = admin;
        elements.confirmMessage.textContent = `${admin.correoElectronico} ya no podrá entrar al panel de administración.`;
        dialogs.open(elements.confirmModal, { focus: elements.confirmCancel });
    }

    elements.add.addEventListener('click', openAdd);
    elements.refresh.addEventListener('click', list);
    elements.retry.addEventListener('click', list);
    elements.form.addEventListener('submit', saveAdd);
    elements.form.addEventListener('invalid', errores.markNativeError, true);
    elements.form.addEventListener('input', errores.clearControlError);
    elements.close.addEventListener('click', closeAdd);
    elements.cancel.addEventListener('click', closeAdd);
    elements.body.addEventListener('click', handleTableAction);
    elements.confirmCancel.addEventListener('click', () => { if (!statusChange.busy) dialogs.close(elements.confirmModal); });
    elements.confirmOk.addEventListener('click', () => { if (pendiente) changeStatus(pendiente, false); });
    for (const modal of [elements.modal, elements.confirmModal]) {
        modal.addEventListener('click', dialogs.handleBackdropClick);
        modal.addEventListener('close', dialogs.restoreFocus);
    }

    list();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', initialize);
}
