// Borradores temporales por formulario en sessionStorage.
//
// Calidad pidio que un fallo de validacion/red no obligue a volver a escribir el
// formulario. Esta capa conserva solo los valores capturados por el usuario,
// separados por recurso y por contexto crear/editar. No persiste contrasenas,
// archivos, errores visuales, loading ni estado de botones.

const VERSION = 1;
const PREFIX = 'tindercows:draft';
const IDENTITY_FIELDS = ['identificacionNumeroOriginal', 'vehiculoId', 'id'];
const controllers = new WeakMap();

function storageDisponible(storage) {
    return storage && typeof storage.getItem === 'function'
        && typeof storage.setItem === 'function'
        && typeof storage.removeItem === 'function';
}

function controlPersistible(control) {
    if (!control?.name) return false;
    const type = String(control.type ?? '').toLowerCase();
    return !['password', 'file', 'submit', 'button', 'reset', 'image'].includes(type);
}

export function draftStorageKey(scope, mode, identity = '') {
    const safeScope = String(scope || 'formulario').trim().replace(/[^a-z0-9_-]+/gi, '-').toLowerCase();
    if (mode === 'edit') {
        return `${PREFIX}:${safeScope}:edit:${encodeURIComponent(String(identity))}`;
    }
    return `${PREFIX}:${safeScope}:create`;
}

/**
 * Obtiene el contexto estable del formulario.
 *
 * Solo habilita borrador automatico cuando existe un campo de identidad oculto:
 * - Productor/Transportista: identificacionNumeroOriginal
 * - Vehiculo: vehiculoId
 * - Metodo de pago: id
 *
 * Esto evita mezclar, por ejemplo, direcciones de fincas distintas, cuyo
 * formulario secundario no tiene hoy una identidad estable en el DOM.
 */
export function resolveDraftContext(form) {
    if (!form?.elements || !form.id) return null;
    const identityName = IDENTITY_FIELDS.find((name) => form.elements.namedItem?.(name));
    if (!identityName) return null;

    const identityControl = form.elements.namedItem(identityName);
    const identity = String(identityControl?.value ?? '').trim();
    const scope = form.id.replace(/^formulario-/, '') || form.id;
    return {
        scope,
        mode: identity === '' ? 'create' : 'edit',
        identity,
        key: draftStorageKey(scope, identity === '' ? 'create' : 'edit', identity),
    };
}

/** Serializa controles con nombre sin guardar metadatos de validacion/UI. */
export function captureFormValues(form) {
    const values = {};
    for (const control of Array.from(form?.elements ?? [])) {
        if (!controlPersistible(control)) continue;
        const name = control.name;
        const type = String(control.type ?? '').toLowerCase();

        if (type === 'checkbox' || type === 'radio') {
            values[name] ??= { kind: 'checked', values: [] };
            if (control.checked) values[name].values.push(String(control.value ?? 'on'));
            continue;
        }
        if (type === 'select-multiple') {
            values[name] = {
                kind: 'multiple',
                values: Array.from(control.selectedOptions ?? []).map((option) => String(option.value)),
            };
            continue;
        }
        values[name] = { kind: 'value', value: String(control.value ?? '') };
    }
    return values;
}

function emitirChange(control) {
    if (typeof control?.dispatchEvent !== 'function') return;
    try {
        const EventCtor = control.ownerDocument?.defaultView?.Event ?? globalThis.Event;
        if (typeof EventCtor === 'function') {
            control.dispatchEvent(new EventCtor('change', { bubbles: true }));
        }
    } catch {
        // Restaurar un borrador nunca debe romper el formulario por un evento.
    }
}

/**
 * Restaura en orden DOM. Al aplicar un select emite change inmediatamente;
 * asi provincia reconstruye cantones antes de restaurar canton, y canton hace
 * lo mismo con distritos. Esto conserva correctamente la cascada territorial.
 */
export function applyFormValues(form, values = {}) {
    for (const control of Array.from(form?.elements ?? [])) {
        if (!controlPersistible(control)) continue;
        const saved = values[control.name];
        if (!saved) continue;
        const type = String(control.type ?? '').toLowerCase();

        if (saved.kind === 'checked' && (type === 'checkbox' || type === 'radio')) {
            control.checked = saved.values.includes(String(control.value ?? 'on'));
            emitirChange(control);
            continue;
        }
        if (saved.kind === 'multiple' && type === 'select-multiple') {
            const seleccionados = new Set(saved.values.map(String));
            Array.from(control.options ?? []).forEach((option) => {
                option.selected = seleccionados.has(String(option.value));
            });
            emitirChange(control);
            continue;
        }
        if (saved.kind === 'value' && 'value' in control) {
            control.value = String(saved.value ?? '');
            if (type === 'select-one') emitirChange(control);
        }
    }
}

export function createFormDraft({
    form,
    storage = globalThis.sessionStorage,
    debounceMs = 250,
    resolveContext = () => resolveDraftContext(form),
    restoreOnOpen = true,
} = {}) {
    if (!form || !storageDisponible(storage)) return null;

    let timer = null;
    let restoring = false;
    let dirty = false;
    let observer = null;

    function context() {
        try { return resolveContext?.() ?? null; } catch { return null; }
    }

    function saveNow() {
        if (restoring) return false;
        const current = context();
        if (!current?.key) return false;
        try {
            storage.setItem(current.key, JSON.stringify({
                version: VERSION,
                savedAt: Date.now(),
                values: captureFormValues(form),
            }));
            dirty = false;
            return true;
        } catch {
            // Quota, modo privado o storage deshabilitado: degradacion segura.
            return false;
        }
    }

    function scheduleSave() {
        if (restoring) return;
        dirty = true;
        if (timer !== null) globalThis.clearTimeout?.(timer);
        timer = globalThis.setTimeout?.(() => {
            timer = null;
            saveNow();
        }, debounceMs) ?? null;
    }

    function restoreNow() {
        const current = context();
        if (!current?.key) return false;
        let parsed;
        try {
            const raw = storage.getItem(current.key);
            if (!raw) return false;
            parsed = JSON.parse(raw);
        } catch {
            return false;
        }
        if (parsed?.version !== VERSION || !parsed.values || typeof parsed.values !== 'object') {
            return false;
        }

        restoring = true;
        try {
            applyFormValues(form, parsed.values);
            dirty = false;
            return true;
        } finally {
            restoring = false;
        }
    }

    function clear() {
        if (timer !== null) {
            globalThis.clearTimeout?.(timer);
            timer = null;
        }
        const current = context();
        if (!current?.key) return false;
        try {
            storage.removeItem(current.key);
            dirty = false;
            return true;
        } catch {
            return false;
        }
    }

    function onPageHide() {
        if (dirty) saveNow();
    }

    form.addEventListener?.('input', scheduleSave);
    form.addEventListener?.('change', scheduleSave);
    globalThis.addEventListener?.('pagehide', onPageHide);

    const dialog = form.closest?.('dialog') ?? null;
    if (restoreOnOpen && dialog) {
        const onOpen = () => { if (dialog.open) restoreNow(); };
        if (typeof MutationObserver !== 'undefined') {
            observer = new MutationObserver((records) => {
                if (records.some((record) => record.attributeName === 'open')) onOpen();
            });
            observer.observe(dialog, { attributes: true, attributeFilter: ['open'] });
        } else {
            dialog.addEventListener?.('toggle', onOpen);
        }
    }

    return {
        saveNow,
        restoreNow,
        clear,
        get context() { return context(); },
        destroy() {
            if (timer !== null) globalThis.clearTimeout?.(timer);
            form.removeEventListener?.('input', scheduleSave);
            form.removeEventListener?.('change', scheduleSave);
            globalThis.removeEventListener?.('pagehide', onPageHide);
            observer?.disconnect();
        },
    };
}

/** Activa una sola instancia por formulario. */
export function enableFormDraft(form, options = {}) {
    if (controllers.has(form)) return controllers.get(form);
    if (!resolveDraftContext(form)) return null;
    const controller = createFormDraft({ form, ...options });
    if (controller) controllers.set(form, controller);
    return controller;
}

/**
 * Se llama al terminar setSaving(false). Si el dialogo ya se cerro, el envio
 * termino con exito y el borrador deja de ser necesario. En un 422/409/500/red
 * el dialogo permanece abierto, por lo que el borrador se conserva.
 */
export function clearFormDraftAfterSuccessfulClose(form) {
    const controller = controllers.get(form);
    if (!controller) return false;
    const dialog = form.closest?.('dialog') ?? null;
    if (!dialog || dialog.open) return false;
    return controller.clear();
}
