const state = {
    filter: 'all',
    query: '',
    visible: [],
    index: 0,
};

function normalize(value) {
    return String(value ?? '').trim().toLocaleLowerCase('es');
}

function cards() {
    return Array.from(document.querySelectorAll('.explore-card'));
}

function updatePosition() {
    const label = document.querySelector('[data-explore-position]');
    if (!label) return;
    const total = state.visible.length;
    label.textContent = total === 0 ? '0 de 0' : `${state.index + 1} de ${total}`;
}

function scrollToCurrent() {
    const current = state.visible[state.index];
    current?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    updatePosition();
}

function applyFilters() {
    const query = normalize(state.query);
    state.visible = cards().filter((card) => {
        const types = normalize(card.dataset.type).split(/\s+/);
        const typeMatch = state.filter === 'all' || types.includes(state.filter);
        const queryMatch = query === '' || normalize(card.dataset.searchable).includes(query);
        const visible = typeMatch && queryMatch;
        card.hidden = !visible;
        return visible;
    });

    state.index = Math.min(state.index, Math.max(0, state.visible.length - 1));
    const empty = document.querySelector('[data-explore-empty]');
    if (empty) empty.hidden = state.visible.length > 0;
    document.querySelector('.explore-deck__navigation')?.toggleAttribute('hidden', state.visible.length === 0);
    updatePosition();
    if (state.visible.length > 0) requestAnimationFrame(scrollToCurrent);
}

function showToast(message) {
    const toast = document.querySelector('[data-explore-toast]');
    if (!toast) return;
    toast.textContent = message;
    toast.hidden = false;
    clearTimeout(showToast.timer);
    showToast.timer = setTimeout(() => { toast.hidden = true; }, 2200);
}

function initializeFilters() {
    for (const button of document.querySelectorAll('[data-explore-filter]')) {
        button.addEventListener('click', () => {
            state.filter = button.dataset.exploreFilter || 'all';
            state.index = 0;
            for (const peer of document.querySelectorAll('[data-explore-filter]')) {
                peer.classList.toggle('is-active', peer === button);
            }
            applyFilters();
        });
    }
}

function initializeSearch() {
    const input = document.querySelector('[data-explore-search]');
    if (!input) return;
    state.query = input.value;
    input.addEventListener('input', () => {
        state.query = input.value;
        state.index = 0;
        applyFilters();
    });
}

function initializeDeck() {
    document.querySelector('[data-explore-prev]')?.addEventListener('click', () => {
        if (state.visible.length === 0) return;
        state.index = (state.index - 1 + state.visible.length) % state.visible.length;
        scrollToCurrent();
    });

    document.querySelector('[data-explore-next]')?.addEventListener('click', () => {
        if (state.visible.length === 0) return;
        state.index = (state.index + 1) % state.visible.length;
        scrollToCurrent();
    });

    for (const button of document.querySelectorAll('[data-explore-action]')) {
        button.addEventListener('click', () => {
            const action = button.dataset.exploreAction;
            showToast(`${action}: acción visual de muestra. Se conectará al servicio correspondiente.`);
            if (action === 'Pasar' && state.visible.length > 1) {
                state.index = (state.index + 1) % state.visible.length;
                scrollToCurrent();
            }
        });
    }

    document.querySelector('[data-explore-reset]')?.addEventListener('click', () => {
        state.filter = 'all';
        state.query = '';
        state.index = 0;
        for (const button of document.querySelectorAll('[data-explore-filter]')) {
            button.classList.toggle('is-active', button.dataset.exploreFilter === 'all');
        }
        const input = document.querySelector('[data-explore-search]');
        if (input) input.value = '';
        applyFilters();
    });
}

function initialize() {
    initializeFilters();
    initializeSearch();
    initializeDeck();
    applyFilters();
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
}
