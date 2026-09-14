import { inicializarUbicacionProductorUI } from './shared/productor-ubicacion-ui.js';

const modal = document.querySelector('#modal-productor');
const hiddenField = document.querySelector('#fincas-nombres');
const list = document.querySelector('#fincas-cards');
const addButton = document.querySelector('#agregar-finca-admin');
const emptyState = document.querySelector('#fincas-empty');

function readNames() {
    return String(hiddenField?.value ?? '')
        .split(/\r?\n/)
        .map((name) => name.trim())
        .filter(Boolean);
}

function syncHidden() {
    if (!hiddenField || !list) return;
    const names = [...list.querySelectorAll('[data-farm-name]')]
        .map((input) => input.value.trim())
        .filter(Boolean);
    hiddenField.value = names.join('\n');
    hiddenField.dispatchEvent(new Event('input', { bubbles: true }));
    renderEmptyState();
}

function renderEmptyState() {
    if (!emptyState || !list) return;
    emptyState.hidden = list.querySelector('[data-farm-card]') !== null;
}

function buildCard(name = '', persisted = false) {
    const card = document.createElement('article');
    card.className = 'farm-card';
    card.dataset.farmCard = 'true';

    const top = document.createElement('div');
    top.className = 'farm-card__top';

    const label = document.createElement('label');
    label.className = 'field';
    const title = document.createElement('span');
    title.textContent = 'Nombre de finca';
    const input = document.createElement('input');
    input.type = 'text';
    input.maxLength = 150;
    input.autocomplete = 'off';
    input.value = name;
    input.placeholder = 'Ej. Finca El Roble';
    input.dataset.farmName = 'true';
    input.setAttribute('aria-label', 'Nombre de finca');
    input.addEventListener('input', syncHidden);
    label.append(title, input);

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'button button--secondary farm-card__remove';
    remove.textContent = 'Quitar';
    remove.addEventListener('click', () => {
        card.remove();
        syncHidden();
    });

    top.append(label, remove);

    const address = document.createElement('div');
    address.className = 'farm-card__address';
    const icon = document.createElement('i');
    icon.className = 'fa-solid fa-location-dot';
    icon.setAttribute('aria-hidden', 'true');
    const copy = document.createElement('div');
    const strong = document.createElement('strong');
    strong.textContent = 'Direccion de esta finca';
    const text = document.createElement('span');
    text.textContent = persisted
        ? 'Ya esta asociada a esta finca. Para verla o editarla use Ver → Direccion en la ficha del productor.'
        : 'Se habilita despues de guardar el productor, para evitar escrituras parciales entre endpoints separados.';
    copy.append(strong, text);
    address.append(icon, copy);

    card.append(top, address);
    return card;
}

function renderFromHidden() {
    if (!list) return;
    const names = readNames();
    list.replaceChildren(...names.map((name) => buildCard(name, true)));
    renderEmptyState();
}

function initialize() {
    if (modal && hiddenField && list && addButton) {
        addButton.addEventListener('click', () => {
            const card = buildCard('', false);
            list.append(card);
            renderEmptyState();
            card.querySelector('[data-farm-name]')?.focus();
        });

        const observer = new MutationObserver(() => {
            if (modal.hasAttribute('open')) queueMicrotask(renderFromHidden);
        });
        observer.observe(modal, { attributes: true, attributeFilter: ['open'] });
        renderEmptyState();
    }
    inicializarUbicacionProductorUI();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
else initialize();
