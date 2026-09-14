import { request } from './shared/api.js';

const PROFILE_KEY = 'tindercows:profile';
const SESSION_KEY = 'tindercows:login';
const PENDING_KEY = 'tindercows:pending-commerce-action';
const INTENT_KEY = 'tindercows:purchase-intents';
const API_URL = 'api/publicaciones.php';

let previousFocus = null;
let currentContext = null;
let verifiedPublication = null;
let verifying = false;
let submitting = false;

function readStored(key) {
    try { return JSON.parse(sessionStorage.getItem(key) || 'null'); } catch { return null; }
}

function writeStored(key, value) {
    sessionStorage.setItem(key, JSON.stringify(value));
}

function ensureStyles() {
    if (document.querySelector('link[data-front2-flow]')) return;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'css/front2-flow.css?v=front2-1';
    link.dataset.front2Flow = 'true';
    document.head.append(link);
}

function buildDialog() {
    let dialog = document.querySelector('#purchase-dialog');
    if (dialog) return dialog;

    dialog = document.createElement('dialog');
    dialog.id = 'purchase-dialog';
    dialog.className = 'purchase-dialog';
    dialog.setAttribute('aria-labelledby', 'purchase-dialog-title');
    dialog.innerHTML = `
        <div class="purchase-dialog__header">
            <div><p class="section-kicker">Comprar / pujar</p><h2 id="purchase-dialog-title" tabindex="-1">Revisar oportunidad</h2></div>
            <button class="flow-button" type="button" data-commerce-close aria-label="Cerrar">×</button>
        </div>
        <div class="purchase-dialog__body" data-commerce-body></div>
        <div class="purchase-dialog__actions" data-commerce-actions></div>`;
    document.body.append(dialog);

    dialog.querySelector('[data-commerce-close]')?.addEventListener('click', closeDialog);
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) closeDialog();
    });
    dialog.addEventListener('close', () => {
        currentContext = null;
        verifiedPublication = null;
        verifying = false;
        submitting = false;
        previousFocus?.focus?.();
        previousFocus = null;
    });
    return dialog;
}

function openDialog() {
    const dialog = buildDialog();
    previousFocus = document.activeElement;
    if (!dialog.open) dialog.showModal();
    requestAnimationFrame(() => dialog.querySelector('#purchase-dialog-title')?.focus());
    return dialog;
}

function closeDialog() {
    const dialog = document.querySelector('#purchase-dialog');
    if (dialog?.open) dialog.close();
}

function body() { return document.querySelector('[data-commerce-body]'); }
function actions() { return document.querySelector('[data-commerce-actions]'); }

function clearDialog() {
    body()?.replaceChildren();
    actions()?.replaceChildren();
}

function actionLink(label, href, primary = false) {
    const link = document.createElement('a');
    link.className = `flow-button${primary ? ' flow-button--primary' : ''}`;
    link.href = href;
    link.textContent = label;
    return link;
}

function renderGate(title, message, links) {
    clearDialog();
    const target = body();
    if (!target) return;
    const result = document.createElement('div');
    result.className = 'purchase-result';
    const heading = document.createElement('h3');
    heading.textContent = title;
    const copy = document.createElement('p');
    copy.textContent = message;
    result.append(heading, copy);
    target.append(result);
    links.forEach((link) => actions()?.append(actionLink(link.label, link.href, link.primary)));
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'flow-button';
    close.textContent = 'Cancelar';
    close.addEventListener('click', closeDialog);
    actions()?.prepend(close);
}

function renderLoading() {
    clearDialog();
    const target = body();
    if (!target) return;
    const loader = document.createElement('div');
    loader.className = 'purchase-loader';
    loader.setAttribute('role', 'status');
    loader.setAttribute('aria-live', 'polite');
    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-circle-notch fa-spin';
    icon.setAttribute('aria-hidden', 'true');
    const text = document.createElement('span');
    text.textContent = 'Verificando que la publicación siga disponible…';
    loader.append(icon, text);
    target.append(loader);
}

function renderError(message) {
    clearDialog();
    const target = body();
    if (!target) return;
    const result = document.createElement('div');
    result.className = 'purchase-result';
    result.setAttribute('role', 'alert');
    const heading = document.createElement('h3');
    heading.textContent = 'No pudimos verificar esta publicación';
    const copy = document.createElement('p');
    copy.textContent = message;
    result.append(heading, copy);
    target.append(result);

    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'flow-button';
    cancel.textContent = 'Cerrar';
    cancel.addEventListener('click', closeDialog);
    const retry = document.createElement('button');
    retry.type = 'button';
    retry.className = 'flow-button flow-button--primary';
    retry.textContent = 'Reintentar';
    retry.addEventListener('click', verifyCurrentPublication);
    actions()?.append(cancel, retry);
}

function contextFromCard(card) {
    const meta = card.querySelectorAll('.explore-card__meta span');
    return {
        title: card.querySelector('h2')?.textContent?.trim() ?? '',
        animalIdentification: meta[1]?.textContent?.trim() ?? '',
        price: card.querySelector('.explore-card__price strong')?.textContent?.trim() ?? '',
        seller: card.querySelector('.explore-card__seller span')?.textContent?.trim() ?? '',
        location: meta[0]?.textContent?.trim() ?? '',
    };
}

function normalize(value) {
    return String(value ?? '').trim().toLocaleLowerCase('es');
}

function publicationMatches(item, context) {
    if (normalize(item?.titulo) !== normalize(context.title)) return false;
    const expectedAnimal = normalize(context.animalIdentification);
    return expectedAnimal === '' || normalize(item?.animal?.identificacion) === expectedAnimal;
}

async function verifyCurrentPublication() {
    if (!currentContext || verifying) return;
    verifying = true;
    renderLoading();
    try {
        const params = new URLSearchParams({
            estado: 'ACTIVO', pagina: '1', tamanoPagina: '100', q: currentContext.title,
        });
        const response = await request(`${API_URL}?${params}`);
        const items = Array.isArray(response.data?.publicaciones) ? response.data.publicaciones : [];
        const matches = items.filter((item) => publicationMatches(item, currentContext));
        if (matches.length !== 1) {
            throw new Error(matches.length === 0
                ? 'La publicación ya no aparece como activa o cambió desde que se mostró en Explorar.'
                : 'Hay más de una publicación con los mismos datos visibles y no es seguro elegir una automáticamente.');
        }
        verifiedPublication = matches[0];
        renderVerified();
    } catch (error) {
        verifiedPublication = null;
        renderError(error.message || 'Ocurrió un error al consultar el catálogo actual.');
    } finally {
        verifying = false;
    }
}

function renderVerified() {
    clearDialog();
    const target = body();
    if (!target || !verifiedPublication) return;

    const summary = document.createElement('div');
    summary.className = 'purchase-summary';
    const heading = document.createElement('strong');
    heading.textContent = verifiedPublication.titulo || 'Publicación';
    const seller = document.createElement('span');
    seller.textContent = `Vendedor: ${verifiedPublication.vendedor?.nombre || 'No informado'}`;
    const farm = document.createElement('span');
    farm.textContent = `Finca: ${verifiedPublication.finca?.nombre || 'No informada'}`;
    const state = document.createElement('span');
    state.textContent = `Estado verificado: ${verifiedPublication.estado || '—'}`;
    summary.append(heading, seller, farm, state);

    const choice = document.createElement('div');
    choice.className = 'purchase-choice';
    const explanation = document.createElement('p');
    explanation.textContent = 'La publicación sigue activa. Puedes preparar una intención de compra. La puja real solo se habilitará cuando el API indique que existe una subasta activa para esta publicación.';
    const bid = document.createElement('button');
    bid.type = 'button';
    bid.className = 'flow-button purchase-disabled';
    bid.disabled = true;
    bid.textContent = 'Pujar — subasta no informada por el API';
    choice.append(explanation, bid);
    target.append(summary, choice);

    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'flow-button';
    cancel.textContent = 'Cancelar';
    cancel.addEventListener('click', closeDialog);
    const buy = document.createElement('button');
    buy.type = 'button';
    buy.className = 'flow-button flow-button--primary';
    buy.textContent = 'Preparar intención de compra';
    buy.addEventListener('click', () => preparePurchaseIntent(buy));
    actions()?.append(cancel, buy);
}

function preparePurchaseIntent(button) {
    if (!verifiedPublication || submitting) return;
    submitting = true;
    button.disabled = true;
    const dialog = document.querySelector('#purchase-dialog');
    dialog?.setAttribute('aria-busy', 'true');
    try {
        const intents = readStored(INTENT_KEY);
        const list = Array.isArray(intents) ? intents : [];
        list.push({
            publicacionId: verifiedPublication.publicacionId,
            animalId: verifiedPublication.animalId,
            tipo: 'COMPRA',
            createdAt: new Date().toISOString(),
            persistence: 'frontend-prototype',
        });
        writeStored(INTENT_KEY, list);
        clearDialog();
        const result = document.createElement('div');
        result.className = 'purchase-result';
        result.setAttribute('role', 'status');
        result.setAttribute('aria-live', 'polite');
        const heading = document.createElement('h3');
        heading.textContent = 'Intención preparada';
        const copy = document.createElement('p');
        copy.textContent = 'El frontend conservó esta intención en la sesión. No anunciamos una compra real: el controlador actual de publicaciones solo acepta GET y todavía no existe un contrato HTTP aprobado para registrar la compra.';
        result.append(heading, copy);
        body()?.append(result);
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'flow-button flow-button--primary';
        close.textContent = 'Entendido';
        close.addEventListener('click', closeDialog);
        actions()?.append(close);
    } finally {
        dialog?.setAttribute('aria-busy', 'false');
        submitting = false;
    }
}

function guardAndOpen(context) {
    currentContext = context;
    openDialog();
    const session = readStored(SESSION_KEY);
    const profile = readStored(PROFILE_KEY);

    if (session?.authenticated !== true) {
        writeStored(PENDING_KEY, context);
        renderGate('Entra para continuar', 'Guardaremos esta publicación mientras inicias sesión.', [
            { label: 'Entrar', href: 'login.php?next=explorar.php', primary: true },
            { label: 'Crear cuenta', href: 'registro.php?capacidad=COMPRADOR&next=explorar.php' },
        ]);
        return;
    }

    if (!profile?.persona) {
        writeStored(PENDING_KEY, context);
        renderGate('Completa tu registro', 'Primero necesitamos una única identidad. Luego volverás a esta publicación.', [
            { label: 'Completar registro', href: 'registro.php?capacidad=COMPRADOR&next=explorar.php', primary: true },
        ]);
        return;
    }

    const state = profile?.capacidadesEstado?.COMPRADOR ?? 'NO_CONFIGURADO';
    if (state === 'NO_CONFIGURADO') {
        writeStored(PENDING_KEY, context);
        renderGate('Activa la compra para esta Persona', 'No volveremos a pedir identificación, nombre, teléfono ni correo; Comprador no duplica la identidad.', [
            { label: 'Habilitar compra', href: 'registro.php?capacidad=COMPRADOR&next=explorar.php', primary: true },
        ]);
        return;
    }
    if (state === 'INACTIVO') {
        writeStored(PENDING_KEY, context);
        renderGate('La compra está inactiva', 'Tu identidad se conserva. Reactiva esta actividad y vuelve a la publicación.', [
            { label: 'Ir a Mi actividad', href: 'mi-actividad.php', primary: true },
        ]);
        return;
    }

    sessionStorage.removeItem(PENDING_KEY);
    verifyCurrentPublication();
}

function addCommerceButtons() {
    const cards = document.querySelectorAll('.explore-card');
    cards.forEach((card) => {
        const area = card.querySelector('.explore-card__actions');
        if (!area || area.querySelector('[data-commerce-open]')) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.commerceOpen = 'true';
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-gavel';
        icon.setAttribute('aria-hidden', 'true');
        const label = document.createElement('span');
        label.textContent = 'Comprar / pujar';
        button.append(icon, label);
        button.addEventListener('click', () => guardAndOpen(contextFromCard(card)));
        area.append(button);
    });

    const pending = readStored(PENDING_KEY);
    if (pending && cards.length > 0) {
        const card = [...cards].find((candidate) => publicationMatches({
            titulo: candidate.querySelector('h2')?.textContent,
            animal: { identificacion: candidate.querySelectorAll('.explore-card__meta span')[1]?.textContent },
        }, pending));
        if (card) guardAndOpen(contextFromCard(card));
    }
}

function initialize() {
    ensureStyles();
    buildDialog();
    const deck = document.querySelector('[data-explore-deck]');
    if (!deck) return;
    const observer = new MutationObserver(addCommerceButtons);
    observer.observe(deck, { childList: true });
    addCommerceButtons();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();
