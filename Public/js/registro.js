import {
    buildRegistrationSummary,
    normalizeCapabilities,
    requiredRegistrationSteps,
    validateCapabilities,
    validateFincas,
    validatePersonaDraft,
} from './shared/business-rules.js';
import { conectarDireccion } from './shared/direccion.js';
import { crearSelectorPuntoFinca } from './shared/finca-mapa.js';
import { inicializarUbicacionAutomatica } from './shared/ubicacion-sesion.js';
import { request } from './shared/api.js';
import { readAuthSession, signUpWithPassword } from './shared/supabase-auth.js';
import { syncPublicProfile } from './shared/public-profile.js';
import { aplicarRestriccionIdentificacion } from './shared/identificacion.js';

const DRAFT_KEY = 'tindercows:registration-draft';
const PROFILE_KEY = 'tindercows:profile';
const SAFE_NEXT = new Set(['explorar.php', 'mi-actividad.php', 'fletes.php', 'publicar.php']);
const editoresFinca = new WeakMap();
let secuenciaFinca = 0;

function readStored(key) {
    try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return null; }
}

function resolveNext(fallback) {
    const requested = new URLSearchParams(window.location.search).get('next');
    return requested && SAFE_NEXT.has(requested) ? requested : fallback;
}

function formPersona(form) {
    const data = new FormData(form);
    const nombres = String(data.get('nombres') ?? '').trim();
    const apellidos = String(data.get('apellidos') ?? '').trim();
    return {
        identificacionTipo: String(data.get('identificacionTipo') ?? ''),
        identificacionNumero: String(data.get('identificacionNumero') ?? ''),
        nombres,
        apellidos,
        nombre: [nombres, apellidos].filter(Boolean).join(' '),
        alias: String(data.get('alias') ?? ''),
        telefono: String(data.get('telefono') ?? ''),
        correoElectronico: String(data.get('correoElectronico') ?? ''),
        password: String(data.get('password') ?? ''),
        passwordConfirmacion: String(data.get('passwordConfirmacion') ?? ''),
    };
}

function selectedCapabilities(form) {
    return normalizeCapabilities([...form.querySelectorAll('input[name="capacidades"]:checked')].map((input) => input.value));
}

function montarDireccionFinca(card, direccionInicial = null) {
    const details = card.querySelector('.finca-address');
    if (!details) return;
    const numero = ++secuenciaFinca;
    const listaId = `registro-pueblos-finca-${numero}`;
    details.innerHTML = `
        <summary>Dirección de la finca <span>opcional</span></summary>
        <div class="farm-address-editor">
            <p class="fieldset-help">Puede escribir la dirección o marcar el punto exacto en el mapa. No es obligatorio completarlo ahora.</p>
            <div class="farm-address-editor__grid">
                <label class="field"><span>Provincia</span><select data-finca-provincia></select></label>
                <label class="field"><span>Cantón</span><select data-finca-canton></select></label>
                <label class="field"><span>Distrito</span><select data-finca-distrito disabled><option value="">Seleccione un distrito</option></select></label>
                <label class="field"><span>Pueblo</span><input data-finca-pueblo maxlength="150" list="${listaId}" autocomplete="off" disabled><datalist id="${listaId}"></datalist></label>
                <label class="field field--full"><span>Señas</span><textarea data-finca-senas maxlength="500" rows="2"></textarea></label>
            </div>
            <div data-finca-mapa></div>
        </div>`;

    const direccion = conectarDireccion({
        provincia: details.querySelector('[data-finca-provincia]'),
        canton: details.querySelector('[data-finca-canton]'),
        distrito: details.querySelector('[data-finca-distrito]'),
        pueblo: details.querySelector('[data-finca-pueblo]'),
        listaPueblos: details.querySelector(`#${listaId}`),
    });
    direccion.aplicar(direccionInicial ?? {});
    details.querySelector('[data-finca-senas]').value = direccionInicial?.senas ?? '';
    const mapa = crearSelectorPuntoFinca({
        mount: details.querySelector('[data-finca-mapa]'),
        puntoInicial: {
            latitud: direccionInicial?.latitud ?? null,
            longitud: direccionInicial?.longitud ?? null,
        },
    });
    editoresFinca.set(card, { direccion, mapa });
}

function leerDireccionFinca(card) {
    const punto = editoresFinca.get(card)?.mapa?.obtenerPunto?.() ?? null;
    const direccion = {
        provincia: String(card.querySelector('[data-finca-provincia]')?.value ?? '').trim(),
        canton: String(card.querySelector('[data-finca-canton]')?.value ?? '').trim(),
        distrito: String(card.querySelector('[data-finca-distrito]')?.value ?? '').trim(),
        pueblo: String(card.querySelector('[data-finca-pueblo]')?.value ?? '').trim() || null,
        senas: String(card.querySelector('[data-finca-senas]')?.value ?? '').trim() || null,
        latitud: punto?.latitud ?? null,
        longitud: punto?.longitud ?? null,
    };
    const tieneAlgo = Boolean(
        direccion.provincia || direccion.canton || direccion.distrito || direccion.pueblo
        || direccion.senas || direccion.latitud || direccion.longitud
    );
    return tieneAlgo ? direccion : null;
}

function readFincas() {
    return [...document.querySelectorAll('[data-finca]')].map((card) => ({
        nombre: String(card.querySelector('[data-finca-nombre]')?.value ?? '').trim(),
        direccion: leerDireccionFinca(card),
    }));
}

function validarDireccionesFinca(fincas) {
    for (const finca of fincas) {
        if (!finca.direccion) continue;
        if (!finca.direccion.provincia || !finca.direccion.canton || !finca.direccion.distrito) {
            return {
                fincas: `Complete provincia, cantón y distrito de ${finca.nombre || 'la finca'} o deje toda su dirección vacía.`,
            };
        }
    }
    return {};
}

function setErrors(errors = {}) {
    document.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
    document.querySelectorAll('[aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
    for (const [field, message] of Object.entries(errors)) {
        const errorNode = document.querySelector(`[data-error-for="${CSS.escape(field)}"]`);
        if (errorNode) errorNode.textContent = message;
        const control = document.querySelector(`[name="${CSS.escape(field)}"]`);
        if (control) control.setAttribute('aria-invalid', 'true');
    }
}

function snapshot(form, existingProfile = null) {
    const persona = existingProfile?.persona ? { ...existingProfile.persona } : formPersona(form);
    return { persona, capacidades: selectedCapabilities(form), fincas: readFincas() };
}

function persistDraft(form, existingProfile = null) {
    const draft = snapshot(form, existingProfile);
    sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    return draft;
}

function addFinca(valor = {}) {
    const finca = typeof valor === 'string' ? { nombre: valor, direccion: null } : (valor ?? {});
    const template = document.querySelector('#finca-template');
    const list = document.querySelector('#fincas-list');
    if (!(template instanceof HTMLTemplateElement) || !list) return;
    const node = template.content.firstElementChild.cloneNode(true);
    const input = node.querySelector('[data-finca-nombre]');
    if (input) input.value = finca.nombre ?? '';
    montarDireccionFinca(node, finca.direccion ?? null);
    node.querySelector('[data-remove-finca]')?.addEventListener('click', () => {
        editoresFinca.get(node)?.mapa?.destruir?.();
        node.remove();
        if (!document.querySelector('[data-finca]')) addFinca();
    });
    list.append(node);
}

function fillPersona(form, persona = {}) {
    const nombreCompleto = String(persona.nombre ?? '').trim();
    const nombres = persona.nombres ?? (nombreCompleto ? nombreCompleto.split(/\s+/).slice(0, -1).join(' ') : '');
    const apellidos = persona.apellidos ?? (nombreCompleto ? nombreCompleto.split(/\s+/).slice(-1).join(' ') : '');
    const valores = { ...persona, nombres, apellidos };
    for (const [name, value] of Object.entries(valores)) {
        const control = form.elements.namedItem(name);
        if (control instanceof HTMLInputElement || control instanceof HTMLSelectElement) control.value = String(value ?? '');
    }
}

function restoreDraft(form, existingProfile) {
    const draft = readStored(DRAFT_KEY);
    if (existingProfile?.persona) fillPersona(form, existingProfile.persona);
    if (draft && !existingProfile) fillPersona(form, draft.persona ?? {});

    const storedCapabilities = existingProfile?.capacidades ?? draft?.capacidades ?? [];
    for (const capability of storedCapabilities) {
        const control = form.querySelector(`input[name="capacidades"][value="${CSS.escape(capability)}"]`);
        if (control instanceof HTMLInputElement) control.checked = true;
    }
    const requested = new URLSearchParams(window.location.search).get('capacidad');
    if (requested) {
        const normalized = normalizeCapabilities([requested])[0];
        const control = normalized ? form.querySelector(`input[name="capacidades"][value="${CSS.escape(normalized)}"]`) : null;
        if (control instanceof HTMLInputElement) control.checked = true;
    }

    const fincas = existingProfile?.fincas ?? draft?.fincas ?? [];
    if (Array.isArray(fincas)) fincas.forEach((finca) => addFinca(finca));
}

function renderSummary(draft, extending) {
    const target = document.querySelector('#registro-resumen');
    if (!target) return;
    const summary = buildRegistrationSummary(draft);
    const capabilities = summary.capacidades.map((cap) => ({
        COMPRADOR: 'Explorar como comprador', PRODUCTOR: 'Vender o publicar', TRANSPORTISTA: 'Ofrecer fletes',
    }[cap] ?? cap));
    target.innerHTML = `
        <section><h3>${extending ? 'Identidad reutilizada' : 'Persona'}</h3><dl>
            <div><dt>Nombres</dt><dd>${escapeHtml(summary.persona.nombres)}</dd></div>
            <div><dt>Apellidos</dt><dd>${escapeHtml(summary.persona.apellidos)}</dd></div>
            <div><dt>Identificación</dt><dd>${escapeHtml(summary.persona.identificacionNumero)}</dd></div>
            <div><dt>Teléfono</dt><dd>${escapeHtml(summary.persona.telefono)}</dd></div>
            <div><dt>Correo</dt><dd>${escapeHtml(summary.persona.correoElectronico)}</dd></div>
        </dl></section>
        <section><h3>Actividades elegidas</h3><p>${capabilities.map(escapeHtml).join(' · ') || 'Ninguna'}</p></section>
        ${summary.capacidades.includes('PRODUCTOR') ? `<section><h3>Fincas</h3><ul>${summary.fincas.map((f) => `<li>${escapeHtml(f.nombre)}${f.direccion?.latitud ? ' · punto exacto agregado' : ''}</li>`).join('')}</ul></section>` : ''}
        <section><h3>Reglas respetadas</h3><ul class="rules-list">
            <li>Tu identidad se registra una sola vez.</li>
            <li>Comprar, vender y transportar son actividades de negocio, no roles administrativos.</li>
            <li>Una misma persona puede participar de varias formas.</li>
            <li>Desactivar una actividad no elimina tu identidad.</li>
        </ul></section>`;
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
}

function initialize() {
    const form = document.querySelector('#registro-form');
    const nextButton = document.querySelector('#registro-siguiente');
    const previousButton = document.querySelector('#registro-anterior');
    const finishButton = document.querySelector('#registro-finalizar');
    const status = document.querySelector('#registro-status');
    if (!(form instanceof HTMLFormElement) || !nextButton || !previousButton || !finishButton || !status) return;

    inicializarUbicacionAutomatica();

    const authSession = readAuthSession();
    const existingProfile = readStored(PROFILE_KEY);
    const extending = Boolean(existingProfile?.persona && authSession);
    if (authSession && !existingProfile?.persona) {
        const email = form.elements.namedItem('correoElectronico');
        const password = form.elements.namedItem('password');
        const confirmation = form.elements.namedItem('passwordConfirmacion');
        if (email instanceof HTMLInputElement) {
            email.value = authSession.email;
            email.readOnly = true;
        }
        for (const control of [password, confirmation]) {
            if (control instanceof HTMLInputElement) {
                control.required = false;
                control.closest('label')?.setAttribute('hidden', '');
            }
        }
    }
    restoreDraft(form, existingProfile);
    if (!document.querySelector('[data-finca]')) addFinca();

    if (extending) {
        const title = document.querySelector('#registro-title');
        if (title) title.textContent = 'Amplía cómo quieres usar TinderCows.';
        const intro = title?.nextElementSibling;
        if (intro) intro.textContent = 'Ya conocemos tu identidad. Solo preguntaremos los datos adicionales que requiera la nueva actividad.';
    }

    let stepIndex = 0;
    const computeSteps = () => {
        const base = requiredRegistrationSteps(selectedCapabilities(form));
        return extending ? base.filter((step) => step !== 'persona') : base;
    };
    let steps = computeSteps();

    const sync = () => {
        steps = computeSteps();
        if (stepIndex >= steps.length) stepIndex = steps.length - 1;
        const active = steps[stepIndex];
        document.querySelectorAll('[data-step]').forEach((section) => { section.hidden = section.dataset.step !== active; });
        document.querySelectorAll('[data-progress]').forEach((item) => {
            const step = item.dataset.progress;
            item.hidden = !steps.includes(step);
            item.classList.toggle('is-active', step === active);
            item.classList.toggle('is-complete', steps.indexOf(step) > -1 && steps.indexOf(step) < stepIndex);
        });
        previousButton.hidden = stepIndex === 0;
        nextButton.hidden = stepIndex === steps.length - 1;
        finishButton.hidden = stepIndex !== steps.length - 1;
        status.textContent = '';
        // La validación técnica y la persistencia son internas; la persona solo
        // necesita ver que el registro está listo para terminar.
    };

    const validateCurrent = () => {
        const active = steps[stepIndex];
        let errors = {};
        if (active === 'persona') {
            errors = validatePersonaDraft(formPersona(form), { requirePassword: !readAuthSession() });
        }
        if (active === 'intereses') errors = validateCapabilities(selectedCapabilities(form));
        if (active === 'fincas') {
            const fincas = readFincas();
            errors = { ...validateFincas(fincas, selectedCapabilities(form)), ...validarDireccionesFinca(fincas) };
        }
        setErrors(errors);
        const first = Object.keys(errors)[0];
        if (first) {
            const control = form.elements.namedItem(first) || document.querySelector(`[data-error-for="${CSS.escape(first)}"]`);
            control?.focus?.();
            status.textContent = 'Revise los datos señalados antes de continuar.';
            return false;
        }
        return true;
    };

    nextButton.addEventListener('click', () => {
        if (!validateCurrent()) return;
        persistDraft(form, existingProfile);
        stepIndex += 1;
        sync();
    });
    previousButton.addEventListener('click', () => { stepIndex = Math.max(0, stepIndex - 1); sync(); });
    document.querySelector('#agregar-finca')?.addEventListener('click', () => addFinca());
    const identificacionTipo = form.elements.namedItem('identificacionTipo');
    const identificacionNumero = form.elements.namedItem('identificacionNumero');
    const identificacionHint = form.querySelector('[data-identificacion-hint]');
    const actualizarIdentificacion = () => {
        if (identificacionTipo instanceof HTMLSelectElement && identificacionNumero instanceof HTMLInputElement) {
            aplicarRestriccionIdentificacion(identificacionNumero, identificacionTipo.value, { hint: identificacionHint });
        }
    };
    identificacionTipo?.addEventListener('change', actualizarIdentificacion);
    actualizarIdentificacion();
    form.addEventListener('input', () => { setErrors({}); persistDraft(form, existingProfile); });
    form.addEventListener('change', () => { persistDraft(form, existingProfile); });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!validateCurrent() || finishButton.disabled) return;
        const draft = persistDraft(form, existingProfile);
        form.setAttribute('aria-busy', 'true');
        finishButton.setAttribute('aria-busy', 'true');
        finishButton.querySelector('i')?.classList.add('is-spinning');
        finishButton.disabled = true;

        const summary = buildRegistrationSummary(draft);
        try {
            if (!readAuthSession()) {
                const auth = await signUpWithPassword(summary.persona.correoElectronico, draft.persona.password);
                if (!auth.session) {
                    status.textContent = 'Cuenta creada. Confirma tu correo y luego entra para completar el registro.';
                    finishButton.disabled = false;
                    return;
                }
            }

            status.textContent = 'Guardando tu identidad y actividades…';
            await request('api/registro.php', {
                method: 'POST',
                body: JSON.stringify({
                    persona: summary.persona,
                    capacidades: summary.capacidades,
                    fincas: summary.fincas,
                }),
            });

            const activity = await request('api/mi-actividad.php');
            syncPublicProfile(activity.data);
            sessionStorage.removeItem(DRAFT_KEY);
            status.textContent = extending
                ? 'Actividad actualizada. Volviendo a tu perfil…'
                : 'Registro completado. Preparando tu perfil…';
            const fallback = extending ? 'mi-actividad.php?actualizado=1' : 'mi-actividad.php?bienvenida=1';
            window.location.assign(resolveNext(fallback));
        } catch (error) {
            const fieldErrors = error?.errors ?? {};
            if (Object.keys(fieldErrors).length > 0) setErrors(fieldErrors);
            status.textContent = error?.message || 'No fue posible completar el registro. Intenta nuevamente.';
            finishButton.disabled = false;
        } finally {
            form.setAttribute('aria-busy', 'false');
            finishButton.setAttribute('aria-busy', 'false');
            finishButton.querySelector('i')?.classList.remove('is-spinning');
        }
    });

    sync();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
