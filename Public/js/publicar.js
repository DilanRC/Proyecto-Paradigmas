import { getAccessToken, readAuthSession } from './shared/supabase-auth.js';
import { request } from './shared/api.js';
import { subirImagenPublicacion, validarImagen } from './shared/storage.js';
import { safeImageUrl } from './explore.js?v=foto-1';

const DRAFT_KEY = 'tindercows:publish-draft';
// La foto elegida no viaja en el borrador: un File no se puede guardar.
const CAMPOS_FUERA_DEL_ENVIO = new Set(['imagenModo']);

function readStored(key) {
    try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return null; }
}

function isAuthenticated() {
    return readAuthSession() !== null;
}

function setGate(title, message, actions = []) {
    const gate = document.querySelector('#publish-gate');
    const workspace = document.querySelector('#publish-workspace');
    if (!gate || !workspace) return;
    workspace.hidden = true;
    gate.hidden = false;
    gate.innerHTML = '';

    const wrapper = document.createElement('div');
    wrapper.className = 'publish-gate__body';
    const heading = document.createElement('h2');
    heading.textContent = title;
    const copy = document.createElement('p');
    copy.textContent = message;
    const buttons = document.createElement('div');
    buttons.className = 'publish-gate__actions';
    actions.forEach(({ label, href, primary = false }) => {
        const link = document.createElement('a');
        link.className = primary ? 'public-cta' : 'public-secondary';
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
    // Con una sola finca no hay nada que elegir.
    if (options.length === 1) select.value = options[0];

    if (options.length === 0) {
        setGate(
            'Falta una finca para publicar',
            'Tu actividad de vendedor está activa, pero no tienes una finca disponible. Agrégala antes de publicar.',
            [
                { label: 'Agregar finca', href: 'registro/productor?next=publicar', primary: true },
                { label: 'Volver a Mi panel', href: 'mi-actividad' },
            ],
        );
    }
}

function restoreDraft(form) {
    const draft = readStored(DRAFT_KEY);
    if (!draft || !form) return;
    for (const [name, value] of Object.entries(draft)) {
        const control = form.elements.namedItem(name);
        if (control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement
            || control instanceof HTMLSelectElement || control instanceof RadioNodeList) {
            control.value = value ?? '';
        }
    }
}

/** Campos de texto del formulario; los archivos nunca se serializan. */
export function serialize(form) {
    const data = new FormData(form);
    return Object.fromEntries([...data.entries()]
        .filter(([, value]) => typeof value === 'string')
        .map(([key, value]) => [key, value.trim()]));
}

/** Cuerpo para la API: sin el modo de imagen y con imagenUrl solo si aplica. */
export function cuerpoPublicacion(draft, imagenUrl) {
    const cuerpo = Object.fromEntries(Object.entries(draft).filter(([key]) => !CAMPOS_FUERA_DEL_ENVIO.has(key)));
    if (imagenUrl) cuerpo.imagenUrl = imagenUrl;
    else delete cuerpo.imagenUrl;
    return cuerpo;
}

function setStatus(kind, title, message, enlace = null) {
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
    if (enlace) {
        const link = document.createElement('a');
        link.href = enlace.href;
        link.textContent = enlace.label;
        target.append(link);
    }
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
    const response = await fetch('api/v1/publicaciones', {
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

/**
 * Foto de la publicación: archivo del dispositivo (clic o arrastrar) o URL
 * https. Devuelve cómo leer la imagen elegida al publicar.
 */
function montarFoto(form) {
    const archivoInput = form.querySelector('#publish-imagen-archivo');
    const urlInput = form.querySelector('#publish-imagen-url');
    const zona = form.querySelector('.publish-dropzone');
    const vista = form.querySelector('[data-imagen-preview]');
    const vistaImg = form.querySelector('[data-imagen-preview-img]');
    const error = form.querySelector('[data-error-for="imagenUrl"]');
    let archivo = null;
    let vistaUrl = null;

    const modo = () => form.elements.namedItem('imagenModo')?.value || 'archivo';
    const mostrarError = (mensaje = '') => { if (error) error.textContent = mensaje; };
    const mostrarVista = (src) => {
        if (vistaUrl) URL.revokeObjectURL(vistaUrl);
        vistaUrl = src?.startsWith('blob:') ? src : null;
        vista.hidden = !src;
        if (src) vistaImg.src = src;
        else vistaImg.removeAttribute('src');
    };
    const elegir = (nuevo) => {
        const problema = validarImagen(nuevo);
        if (problema) { mostrarError(problema); return; }
        mostrarError();
        archivo = nuevo;
        mostrarVista(URL.createObjectURL(nuevo));
    };
    const sincronizarModo = () => {
        form.querySelectorAll('[data-imagen-panel]').forEach((panel) => { panel.hidden = panel.dataset.imagenPanel !== modo(); });
        mostrarError();
        if (modo() === 'archivo') mostrarVista(archivo ? URL.createObjectURL(archivo) : null);
        else mostrarVista(safeImageUrl(urlInput.value));
    };

    archivoInput.addEventListener('change', () => { if (archivoInput.files?.[0]) elegir(archivoInput.files[0]); });
    for (const tipo of ['dragenter', 'dragover']) {
        zona.addEventListener(tipo, (event) => { event.preventDefault(); zona.classList.add('is-dragging'); });
    }
    for (const tipo of ['dragleave', 'drop']) {
        zona.addEventListener(tipo, () => zona.classList.remove('is-dragging'));
    }
    zona.addEventListener('drop', (event) => {
        event.preventDefault();
        const soltado = event.dataTransfer?.files?.[0];
        if (soltado) elegir(soltado);
    });
    urlInput.addEventListener('input', () => {
        mostrarError();
        mostrarVista(safeImageUrl(urlInput.value));
    });
    // Una URL que no carga se avisa antes de publicar, no después.
    vistaImg.addEventListener('error', () => {
        if (modo() === 'url' && urlInput.value.trim()) mostrarError('No pudimos cargar esa imagen. Revisa que el enlace sea directo a la foto.');
    });
    form.querySelectorAll('input[name="imagenModo"]').forEach((radio) => radio.addEventListener('change', sincronizarModo));
    form.querySelector('[data-imagen-quitar]').addEventListener('click', () => {
        archivo = null;
        archivoInput.value = '';
        urlInput.value = '';
        mostrarVista(null);
        mostrarError();
    });
    sincronizarModo();

    return {
        /** URL final de la foto (subiendo el archivo si hace falta) o null. */
        async resolver() {
            if (modo() === 'archivo') return archivo ? subirImagenPublicacion(archivo) : null;
            const texto = urlInput.value.trim();
            if (!texto) return null;
            const segura = safeImageUrl(texto);
            if (!segura) throw new Error('Usa una dirección de imagen que empiece con https://.');
            return segura;
        },
        reiniciar() {
            archivo = null;
            archivoInput.value = '';
            urlInput.value = '';
            mostrarVista(null);
        },
        mostrarError,
    };
}

async function initialize() {
    const form = document.querySelector('#publish-form');
    const submit = document.querySelector('#publish-submit');
    if (!(form instanceof HTMLFormElement) || !submit) return;

    if (!isAuthenticated()) {
        setGate(
            'Entra para publicar',
            'Para publicar ganado necesitas una cuenta con la actividad de vendedor.',
            [
                { label: 'Entrar', href: 'entrar?next=publicar', primary: true },
                { label: 'Crear cuenta', href: 'registro' },
            ],
        );
        return;
    }

    let activity;
    try {
        activity = (await request('api/v1/actividad')).data;
    } catch (error) {
        setGate(
            'No pudimos comprobar tu actividad',
            error?.message || 'No fue posible consultar tu cuenta. Inténtalo nuevamente desde Mi panel.',
            [{ label: 'Ir a Mi panel', href: 'mi-actividad', primary: true }],
        );
        return;
    }

    if (!activity?.persona) {
        setGate(
            'Completa tu cuenta antes de publicar',
            'Todavía no terminamos tu registro. Complétalo y vuelve a publicar.',
            [{ label: 'Completar registro', href: 'registro?next=publicar', primary: true }],
        );
        return;
    }

    const capacidades = activity.capacidades ?? {};
    const state = capacidades.PRODUCTOR?.estado ?? 'NO_CONFIGURADO';
    if (state === 'NO_CONFIGURADO') {
        setGate(
            'Activa tu cuenta de vendedor',
            'Solo falta registrar una finca. No volveremos a pedir tus datos personales.',
            [{ label: 'Activar vendedor', href: 'registro/productor?next=publicar', primary: true }],
        );
        return;
    }
    if (state === 'INACTIVO') {
        setGate(
            'Tu actividad de vendedor está inactiva',
            'Tus fincas se conservan. Reactívala desde Ajustes de cuenta para volver a publicar.',
            [{ label: 'Ir a Ajustes', href: 'ajustes#participacion', primary: true }],
        );
        return;
    }

    showWorkspace({
        persona: activity.persona,
        fincas: capacidades.PRODUCTOR?.fincas ?? [],
    });
    restoreDraft(form);
    const foto = montarFoto(form);

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
            let imagenUrl = null;
            try {
                imagenUrl = await foto.resolver();
            } catch (error) {
                foto.mostrarError(error.message);
                throw error;
            }
            const resultado = await crearPublicacion(cuerpoPublicacion(draft, imagenUrl));
            sessionStorage.removeItem(DRAFT_KEY);
            form.reset();
            foto.reiniciar();
            setStatus('success', 'Publicación guardada',
                'Tu publicación ya está activa y la verán compradores cercanos.',
                { href: `explorar?publicacion=${Number(resultado.publicacionId)}`, label: 'Ver mi publicación' });
        } catch (error) {
            if (error?.fieldErrors?.imagenUrl) foto.mostrarError(error.fieldErrors.imagenUrl);
            setStatus('error', 'No se guardó la publicación', error.message);
        } finally {
            form.setAttribute('aria-busy', 'false');
            submit.disabled = false;
        }
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
}
