function setSearchOpen(root, open) {
    if (!root) return;
    const safeOpen = open === true;
    root.dataset.open = String(safeOpen);
    const toggle = root.querySelector('[data-public-search-toggle]');
    const input = root.querySelector('input[type="search"]');
    toggle?.setAttribute('aria-expanded', String(safeOpen));
    if (safeOpen) {
        requestAnimationFrame(() => input?.focus());
    }
}

function initializePublicSearch() {
    for (const root of document.querySelectorAll('[data-public-search]')) {
        if (root.dataset.ready === 'true') continue;
        root.dataset.ready = 'true';
        const toggle = root.querySelector('[data-public-search-toggle]');
        const input = root.querySelector('input[type="search"]');
        if (!toggle || !input) continue;

        toggle.addEventListener('click', () => {
            const open = root.dataset.open === 'true';
            setSearchOpen(root, !open);
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                input.value = '';
                setSearchOpen(root, false);
                toggle.focus();
            }
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target) && input.value.trim() === '') {
                setSearchOpen(root, false);
            }
        });
    }
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializePublicSearch, { once: true });
    } else {
        initializePublicSearch();
    }
}
