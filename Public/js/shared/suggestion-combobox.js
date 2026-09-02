/*
 * Combobox de sugerencias para campos de texto libre.
 *
 * Portado del patron de busqueda/autocompletado de Ciclo_Finca_4_Vercel, pero
 * con una politica distinta: una sugerencia ayuda a escribir, no convierte el
 * campo en un catalogo cerrado. Por eso nunca se borra ni se restaura el valor
 * escrito por la persona si no coincide con una opcion.
 */

const STYLE_MARKER = 'data-suggestion-combobox-style';
const DEFAULT_MAX_HEIGHT = 280;
const VIEWPORT_MARGIN = 8;
const MENU_GAP = 6;

function ensureStyles() {
    if (typeof document === 'undefined' || !document.head) return;
    if (document.querySelector(`link[${STYLE_MARKER}]`)) return;

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = new URL('../../css/suggestion-combobox.css', import.meta.url).href;
    link.setAttribute(STYLE_MARKER, '');
    document.head.appendChild(link);
}

/** Devuelve el siguiente indice activo con navegacion circular. */
export function nextActiveIndex(current, step, total) {
    if (!Number.isInteger(total) || total <= 0) return -1;
    const safeCurrent = Number.isInteger(current) ? current : -1;
    if (step < 0) return safeCurrent > 0 ? safeCurrent - 1 : total - 1;
    return safeCurrent < total - 1 ? safeCurrent + 1 : 0;
}

function createSearchIcon(className) {
    const span = document.createElement('span');
    span.className = className;
    span.setAttribute('aria-hidden', 'true');
    span.innerHTML = '<svg viewBox="0 0 24 24" focusable="false"><circle cx="11" cy="11" r="6.5"></circle><path d="m16 16 4 4"></path></svg>';
    return span;
}

function uniqueByLabel(items, getLabel) {
    const seen = new Set();
    return items.filter((item) => {
        const label = String(getLabel(item) ?? '');
        if (seen.has(label)) return false;
        seen.add(label);
        return true;
    });
}

/**
 * Mejora un input de texto con un listbox accesible y visualmente controlable.
 *
 * getSuggestions puede ser asincrono. Las respuestas viejas se descartan por
 * secuencia y por valor actual del input, de modo que un cambio rapido de
 * consulta o contexto no puede pintar resultados obsoletos.
 */
export function createSuggestionCombobox({
    input,
    getSuggestions,
    getLabel = (item) => String(item ?? ''),
    getMeta = () => '',
    fallbackList = null,
    label = 'Sugerencias',
    emptyText = 'Sin coincidencias',
    loadingText = 'Cargando sugerencias…',
    errorText = 'No fue posible cargar las sugerencias',
    onSelected = null,
} = {}) {
    if (!input || typeof getSuggestions !== 'function') return null;

    ensureStyles();

    const originalParent = input.parentNode;
    if (!originalParent) return null;

    const wrapper = document.createElement('div');
    wrapper.className = 'suggestion-combobox';
    originalParent.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    const leadingIcon = createSearchIcon('suggestion-combobox__leading-icon');
    wrapper.insertBefore(leadingIcon, input);

    const menu = document.createElement('div');
    const baseId = fallbackList?.id || input.id || `suggestion-${Math.random().toString(36).slice(2)}`;
    menu.id = `${baseId}-menu`;
    menu.className = 'suggestion-combobox__menu';
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('aria-label', label);
    menu.hidden = true;
    wrapper.appendChild(menu);

    if (fallbackList) fallbackList.hidden = true;
    input.removeAttribute('list');
    input.classList.add('suggestion-combobox__input');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', menu.id);
    input.setAttribute('aria-expanded', 'false');

    let isOpen = false;
    let items = [];
    let activeIndex = -1;
    let sequence = 0;
    let loadingTimer = 0;
    let positionFrame = 0;
    let suppressInput = false;

    function cancelPending() {
        sequence += 1;
        if (loadingTimer) {
            clearTimeout(loadingTimer);
            loadingTimer = 0;
        }
    }

    function setOpen(open) {
        isOpen = Boolean(open) && !input.disabled;
        menu.hidden = !isOpen;
        wrapper.classList.toggle('is-open', isOpen);
        input.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (!isOpen) {
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');
            return;
        }
        schedulePosition();
    }

    function close({ cancel = true } = {}) {
        if (cancel) cancelPending();
        setOpen(false);
    }

    function schedulePosition() {
        if (!isOpen || typeof window === 'undefined') return;
        if (positionFrame) return;
        const raf = window.requestAnimationFrame || ((fn) => setTimeout(fn, 0));
        positionFrame = raf(() => {
            positionFrame = 0;
            positionMenu();
        });
    }

    function positionMenu() {
        if (!isOpen || typeof window === 'undefined') return;
        const rect = input.getBoundingClientRect();
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const width = Math.max(180, Math.min(rect.width, viewportWidth - VIEWPORT_MARGIN * 2));
        const left = Math.max(
            VIEWPORT_MARGIN,
            Math.min(rect.left, viewportWidth - width - VIEWPORT_MARGIN),
        );
        const spaceBelow = viewportHeight - rect.bottom - MENU_GAP - VIEWPORT_MARGIN;
        const spaceAbove = rect.top - MENU_GAP - VIEWPORT_MARGIN;
        const placeAbove = spaceBelow < 150 && spaceAbove > spaceBelow;
        const available = Math.max(96, placeAbove ? spaceAbove : spaceBelow);
        const maxHeight = Math.min(DEFAULT_MAX_HEIGHT, available);

        menu.style.left = `${Math.round(left)}px`;
        menu.style.width = `${Math.round(width)}px`;
        menu.style.maxHeight = `${Math.round(maxHeight)}px`;

        if (placeAbove) {
            menu.style.top = 'auto';
            menu.style.bottom = `${Math.round(viewportHeight - rect.top + MENU_GAP)}px`;
            wrapper.classList.add('opens-above');
        } else {
            menu.style.bottom = 'auto';
            menu.style.top = `${Math.round(rect.bottom + MENU_GAP)}px`;
            wrapper.classList.remove('opens-above');
        }
    }

    function renderState(text, kind = 'empty') {
        items = [];
        activeIndex = -1;
        input.removeAttribute('aria-activedescendant');
        menu.replaceChildren();
        const state = document.createElement('div');
        state.className = `suggestion-combobox__state suggestion-combobox__state--${kind}`;
        state.setAttribute('role', 'status');
        state.textContent = text;
        menu.appendChild(state);
    }

    function highlightActive() {
        const options = menu.querySelectorAll('[role="option"]');
        options.forEach((option, index) => {
            const active = index === activeIndex;
            option.classList.toggle('is-active', active);
            option.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        const active = options[activeIndex];
        if (active) {
            input.setAttribute('aria-activedescendant', active.id);
            active.scrollIntoView({ block: 'nearest' });
        } else {
            input.removeAttribute('aria-activedescendant');
        }
    }

    function selectItem(item) {
        suppressInput = true;
        input.value = String(getLabel(item) ?? '');
        input.dispatchEvent(new Event('input', { bubbles: true }));
        suppressInput = false;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close();
        input.focus({ preventScroll: true });
        if (typeof onSelected === 'function') onSelected(item);
    }

    function renderOptions(found) {
        items = uniqueByLabel(Array.isArray(found) ? found : [], getLabel);
        activeIndex = -1;
        input.removeAttribute('aria-activedescendant');
        menu.replaceChildren();

        if (!items.length) {
            renderState(emptyText);
            return;
        }

        const fragment = document.createDocumentFragment();
        items.forEach((item, index) => {
            const option = document.createElement('div');
            option.id = `${menu.id}-option-${index}`;
            option.className = 'suggestion-combobox__option';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.dataset.index = String(index);

            option.appendChild(createSearchIcon('suggestion-combobox__option-icon'));

            const name = document.createElement('span');
            name.className = 'suggestion-combobox__option-name';
            name.textContent = String(getLabel(item) ?? '');
            option.appendChild(name);

            const metaText = String(getMeta(item) ?? '').trim();
            if (metaText) {
                const meta = document.createElement('span');
                meta.className = 'suggestion-combobox__option-meta';
                meta.textContent = metaText;
                option.appendChild(meta);
            }

            option.addEventListener('pointerenter', () => {
                activeIndex = index;
                highlightActive();
            });
            option.addEventListener('pointerdown', (event) => {
                event.preventDefault();
                selectItem(item);
            });
            fragment.appendChild(option);
        });
        menu.appendChild(fragment);
    }

    async function refresh({ open = true } = {}) {
        if (input.disabled) {
            close();
            return [];
        }

        const query = input.value;
        const ticket = ++sequence;
        if (loadingTimer) clearTimeout(loadingTimer);
        loadingTimer = setTimeout(() => {
            if (ticket !== sequence || query !== input.value || input.disabled) return;
            renderState(loadingText, 'loading');
            if (open) setOpen(true);
        }, 120);

        try {
            const found = await getSuggestions(query);
            if (ticket !== sequence || query !== input.value || input.disabled) return [];
            clearTimeout(loadingTimer);
            loadingTimer = 0;
            renderOptions(found);
            if (open) setOpen(true);
            return items.slice();
        } catch (_error) {
            if (ticket !== sequence || query !== input.value || input.disabled) return [];
            clearTimeout(loadingTimer);
            loadingTimer = 0;
            renderState(errorText, 'error');
            if (open) setOpen(true);
            return [];
        }
    }

    function setDisabled(disabled) {
        input.disabled = Boolean(disabled);
        wrapper.classList.toggle('is-disabled', input.disabled);
        if (input.disabled) close();
    }

    function onInput() {
        if (suppressInput) return;
        refresh();
    }

    function onFocus() {
        refresh();
    }

    function onBlur() {
        setTimeout(() => {
            if (document.activeElement !== input) close();
        }, 0);
    }

    function onKeyDown(event) {
        if (event.key === 'Escape') {
            if (!isOpen) return;
            event.preventDefault();
            close();
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowUp' ? -1 : 1;
            if (!isOpen) {
                refresh().then(() => {
                    if (!isOpen || !items.length) return;
                    activeIndex = nextActiveIndex(-1, step, items.length);
                    highlightActive();
                });
                return;
            }
            activeIndex = nextActiveIndex(activeIndex, step, items.length);
            highlightActive();
            return;
        }

        if (event.key === 'Enter' && isOpen && activeIndex >= 0 && items[activeIndex]) {
            event.preventDefault();
            selectItem(items[activeIndex]);
        }
    }

    function onDocumentPointerDown(event) {
        if (!wrapper.contains(event.target)) close();
    }

    function onViewportChange() {
        schedulePosition();
    }

    input.addEventListener('input', onInput);
    input.addEventListener('focus', onFocus);
    input.addEventListener('blur', onBlur);
    input.addEventListener('keydown', onKeyDown);
    document.addEventListener('pointerdown', onDocumentPointerDown, true);
    document.addEventListener('scroll', onViewportChange, true);
    window.addEventListener('resize', onViewportChange);

    setDisabled(input.disabled);

    return {
        input,
        menu,
        wrapper,
        close,
        refresh,
        setDisabled,
        destroy() {
            close();
            input.removeEventListener('input', onInput);
            input.removeEventListener('focus', onFocus);
            input.removeEventListener('blur', onBlur);
            input.removeEventListener('keydown', onKeyDown);
            document.removeEventListener('pointerdown', onDocumentPointerDown, true);
            document.removeEventListener('scroll', onViewportChange, true);
            window.removeEventListener('resize', onViewportChange);
        },
    };
}
