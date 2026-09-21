import { getAccessToken } from './shared/supabase-auth.js';

const PROFILE_KEY = 'tindercows:profile';
const SESSION_KEY = 'tindercows:login';
const DRAFT_KEY = 'tindercows:publish-draft';

function readStored(key) {
    try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return null; }
}

function isAuthenticated() {
    return readStored(SESSION_KEY)?.authenticated === true;
}

function capabilityState(profile, capability) {
    return profile?.capacidadesEstado?.[capability] ?? 'NO_CONFIGURADO';
}

function setGate(title, message, actions = []) {
    const gate = document.querySelector('#publish-gate');
    const workspace = document.querySelector('#publish-workspace');
    if (!gate || !workspace) return;
    workspace.hidden = true;
    gate.hidden = false;
    gate.innerHTML = '';

    const wrapper = document.createElement('div');
    wrapper.className = 'flow-gate';
    const heading = document.createElement('h2');
    heading.textContent = title;
    const copy = document.createElement('p');
    copy.textContent = message;
    const buttons = document.createElement('div');
    buttons.className = 'flow-gate__actions';
    actions.forEach(({ label, href, primary = false }) => {
        const link = document.createElement('a');
        link.className = `flow-button${primary ? ' flow-button--primary' : ''}`;
        link.href = href;
        link.textContent = label;
        buttons.append(link);
    });
    wrapper.append(heading, copy, buttons);
    gate.append(wrapper);
}

function showWorkspace(profile) {
    const gate = document.querySelector('#publish-gate');
    const workspace = document.querySelector('#publish-workspace');
    const select = document.querySelector('#publish-finca');
    if (!workspace || !select) return;
    if (gate) gate.hidden = true;
    workspace.hidden = false;

    const farms = Array.isArray(profile?.fincas) ? profile.fincas : [];
    const options = farms
        .map((farm) => String(farm?.nombre ?? '').trim())
        .filter(Boolean);
    select.replaceChildren(new Option('Seleccione una finca', ''));
    options.forEach((name) => select.append(new Option(name, name)));

    if (options.length === 0) {
        setGate(
            'Falta una finca para publicar',
            'La actividad Productor está activa, pero este perfil no tiene una finca disponible. Completa esa información antes de preparar la publicación.',
            [
                { label: 'Completar datos de productor', href: 'registro.php?capacidad=PRODUCTOR&next=publicar.php', primary: true },
                { label: 'Volver a Mi actividad', href: 'mi-actividad.php' },
            ],
        );
    }
}

function restoreDraft(form) {
    const draft = readStored(DRAFT_KEY);
    if (!draft || !form) return;
    for (const [name, value] of Object.entries(draft)) {
        const control = form.elements.namedItem(name);
        if (control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement || control instanceof HTMLSelectElement) {
            control.value = value ?? '';
        }
    }
}

function serialize(form) {
    const data = new FormData(form);
    return Object.fromEntries([...data.entries()].map(([key, value]) => [key, String(value).trim()]));
}

function setStatus(kind, title, message) {
    const target = document.querySelector('#publish-status');
    if (!target) return;
    target.hidden = false;
    target.dataset.kind = kind;
    target.replaceChildren();
    const heading = document.createElement('h2');
    heading.textContent = title;
    const copy = document.createElement('p');
    copy.textContent = message;
    target.append(heading, copy);
}

function validate(form) {
    if (form.checkValidity()) return true;
    const invalid = form.querySelector(':invalid');
    invalid?.focus();
    form.reportValidity();
    return false;
}

async function crearPublicacion(draft) {
    const token = await getAccessToken();
    if (!token) throw new Error('La sesión expiró. Entra de nuevo para publicar.');
    const response = await fetch('api/publicaciones.php', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify(draft),
    });
    let payload = null;
    try { payload = await response.json(); } catch { /* El estado se comunica abajo. */ }
    if (!response.ok || payload?.success !== true) {
        const error = new Error(payload?.message || 'No fue posible guardar la publicación.');
        error.fieldErrors = payload?.errors ?? {};
        error.status = response.status;
        throw error;
    }
    return payload.data;
}

function initialize() {
    const form = document.querySelector('#publish-form');
    const submit = document.querySelector('#publish-submit');
    if (!(form instanceof HTMLFormElement) || !submit) return;

    const profile = readStored(PROFILE_KEY);
    if (!isAuthenticated()) {
        setGate(
            'Entra para publicar',
            'Publicar ganado requiere una Persona autenticada para poder continuar con su contexto de productor.',
            [
                { label: 'Entrar', href: 'login.php?next=publicar.php', primary: true },
                { label: 'Crear cuenta', href: 'registro.php?capacidad=PRODUCTOR&next=publicar.php' },
            ],
        );
        return;
    }

    if (!profile?.persona) {
        setGate(
            'Completa tu cuenta antes de publicar',
            'Todavía no existe una Persona registrada en este prototipo. El registro reutilizará esa identidad para todas las actividades futuras.',
            [{ label: 'Completar registro', href: 'registro.php?capacidad=PRODUCTOR&next=publicar.php', primary: true }],
        );
        return;
    }

    const state = capabilityState(profile, 'PRODUCTOR');
    if (state === 'NO_CONFIGURADO') {
        setGate(
            'Activa tu participación como productor',
            'Solo faltan los datos propios de vender o publicar. No volveremos a pedir tu identidad personal.',
            [{ label: 'Completar datos de productor', href: 'registro.php?capacidad=PRODUCTOR&next=publicar.php', primary: true }],
        );
        return;
    }
    if (state === 'INACTIVO') {
        setGate(
            'Tu actividad de productor está inactiva',
            'La identidad y las fincas se conservan. Reactiva la actividad desde Mi actividad antes de publicar de nuevo.',
            [{ label: 'Ir a Mi actividad', href: 'mi-actividad.php', primary: true }],
        );
        return;
    }

    showWorkspace(profile);
    restoreDraft(form);

    form.addEventListener('input', () => {
        sessionStorage.setItem(DRAFT_KEY, JSON.stringify(serialize(form)));
    });
    form.addEventListener('change', () => {
        sessionStorage.setItem(DRAFT_KEY, JSON.stringify(serialize(form)));
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!validate(form) || submit.disabled) return;
        submit.disabled = true;
        form.setAttribute('aria-busy', 'true');
        const draft = serialize(form);
        sessionStorage.setItem(DRAFT_KEY, JSON.stringify(draft));

        try {
            const resultado = await crearPublicacion(draft);
            sessionStorage.removeItem(DRAFT_KEY);
            setStatus('success', 'Publicación guardada',
                `Tu publicación quedó activa. Código de publicación: ${resultado.publicacionId}.`);
        } catch (error) {
            setStatus('error', 'No se guardó la publicación', error.message);
        } finally {
            form.setAttribute('aria-busy', 'false');
            submit.disabled = false;
        }
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();
