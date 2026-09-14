import {
    buildRegistrationSummary,
    normalizeCapabilities,
    requiredRegistrationSteps,
    validateCapabilities,
    validateFincas,
    validatePersonaDraft,
} from './shared/business-rules.js';

const DRAFT_KEY = 'tindercows:registration-draft';
const PROFILE_KEY = 'tindercows:profile';
const SESSION_KEY = 'tindercows:login';

function readStored(key) {
    try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return null; }
}

function formPersona(form) {
    const data = new FormData(form);
    return {
        identificacionTipo: String(data.get('identificacionTipo') ?? ''),
        identificacionNumero: String(data.get('identificacionNumero') ?? ''),
        nombre: String(data.get('nombre') ?? ''),
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

function readFincas() {
    return [...document.querySelectorAll('[data-finca]')].map((card) => ({
        nombre: String(card.querySelector('[data-finca-nombre]')?.value ?? '').trim(),
    }));
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

function addFinca(name = '') {
    const template = document.querySelector('#finca-template');
    const list = document.querySelector('#fincas-list');
    if (!(template instanceof HTMLTemplateElement) || !list) return;
    const node = template.content.firstElementChild.cloneNode(true);
    const input = node.querySelector('[data-finca-nombre]');
    if (input) input.value = name;
    node.querySelector('[data-remove-finca]')?.addEventListener('click', () => {
        node.remove();
        if (!document.querySelector('[data-finca]')) addFinca();
    });
    list.append(node);
}

function fillPersona(form, persona = {}) {
    for (const [name, value] of Object.entries(persona)) {
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
    if (Array.isArray(fincas)) fincas.forEach((finca) => addFinca(finca.nombre));
}

function renderSummary(draft, extending) {
    const target = document.querySelector('#registro-resumen');
    if (!target) return;
    const summary = buildRegistrationSummary(draft);
    const capabilities = summary.capacidades.map((cap) => ({
        COMPRADOR: 'Comprar ganado', PRODUCTOR: 'Vender o publicar', TRANSPORTISTA: 'Ofrecer fletes',
    }[cap] ?? cap));
    target.innerHTML = `
        <section><h3>${extending ? 'Identidad reutilizada' : 'Persona'}</h3><dl>
            <div><dt>Nombre</dt><dd>${escapeHtml(summary.persona.nombre)}</dd></div>
            <div><dt>Identificación</dt><dd>${escapeHtml(summary.persona.identificacionNumero)}</dd></div>
            <div><dt>Teléfono</dt><dd>${escapeHtml(summary.persona.telefono)}</dd></div>
            <div><dt>Correo</dt><dd>${escapeHtml(summary.persona.correoElectronico)}</dd></div>
        </dl></section>
        <section><h3>Actividades elegidas</h3><p>${capabilities.map(escapeHtml).join(' · ') || 'Ninguna'}</p></section>
        ${summary.capacidades.includes('PRODUCTOR') ? `<section><h3>Fincas</h3><ul>${summary.fincas.map((f) => `<li>${escapeHtml(f.nombre)}</li>`).join('')}</ul></section>` : ''}
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

    const existingProfile = readStored(PROFILE_KEY);
    const extending = Boolean(existingProfile?.persona);
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
        if (active === 'revision') renderSummary(snapshot(form, existingProfile), extending);
    };

    const validateCurrent = () => {
        const active = steps[stepIndex];
        let errors = {};
        if (active === 'persona') errors = validatePersonaDraft(formPersona(form));
        if (active === 'intereses') errors = validateCapabilities(selectedCapabilities(form));
        if (active === 'fincas') errors = validateFincas(readFincas(), selectedCapabilities(form));
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
    form.addEventListener('input', () => { setErrors({}); persistDraft(form, existingProfile); });
    form.addEventListener('change', () => { persistDraft(form, existingProfile); });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!validateCurrent()) return;
        const draft = persistDraft(form, existingProfile);
        form.setAttribute('aria-busy', 'true');
        finishButton.disabled = true;

        const summary = buildRegistrationSummary(draft);
        const previousStates = existingProfile?.capacidadesEstado ?? {};
        const profile = {
            ...(existingProfile ?? {}),
            ...summary,
            capacidadesEstado: Object.fromEntries(summary.capacidades.map((cap) => [cap, previousStates[cap] ?? 'ACTIVO'])),
            onboardingCompletedAt: existingProfile?.onboardingCompletedAt ?? new Date().toISOString(),
            updatedAt: new Date().toISOString(),
            persistence: 'frontend-prototype',
        };
        sessionStorage.setItem(PROFILE_KEY, JSON.stringify(profile));
        sessionStorage.setItem(SESSION_KEY, JSON.stringify({
            authenticated: true,
            version: 2,
            email: profile.persona.correoElectronico,
            startedAt: new Date().toISOString(),
            mode: 'frontend-prototype',
        }));
        sessionStorage.removeItem(DRAFT_KEY);
        status.textContent = extending ? 'Actividad actualizada. Volviendo a tu espacio…' : 'Registro completado. Preparando tu espacio…';
        window.location.assign(`mi-actividad.php?${extending ? 'actualizado' : 'bienvenida'}=1`);
    });

    sync();
}

if (typeof document !== 'undefined') document.addEventListener('DOMContentLoaded', initialize);
