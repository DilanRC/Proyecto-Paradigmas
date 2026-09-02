export const THEME_KEY = 'tindercows:theme';

function safeReadTheme() {
    try {
        return globalThis.localStorage?.getItem(THEME_KEY) ?? null;
    } catch {
        return null;
    }
}

export function preferredTheme() {
    const saved = safeReadTheme();
    if (saved === 'light' || saved === 'dark') return saved;
    return globalThis.matchMedia?.('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
}

export function applyTheme(theme) {
    if (typeof document === 'undefined') return;
    const safeTheme = theme === 'light' ? 'light' : 'dark';
    const root = document.documentElement;
    root.dataset.theme = safeTheme;
    root.style.colorScheme = safeTheme;

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', safeTheme === 'light' ? '#fef7ec' : '#151a18');

    for (const button of document.querySelectorAll('[data-theme-toggle]')) {
        const nextIsLight = safeTheme === 'dark';
        button.setAttribute('aria-pressed', String(safeTheme === 'dark'));
        button.setAttribute('aria-label', nextIsLight ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
        const label = button.querySelector('.theme-toggle__label');
        const icon = button.querySelector('.theme-toggle__icon');
        if (label) label.textContent = nextIsLight ? 'Claro' : 'Oscuro';
        if (icon) {
            icon.textContent = '';
            icon.className = `theme-toggle__icon fa-solid ${nextIsLight ? 'fa-sun' : 'fa-moon'}`;
        }
    }
}

export function storeTheme(theme) {
    try {
        globalThis.localStorage?.setItem(THEME_KEY, theme);
    } catch {
        // La preferencia visual no debe impedir usar la aplicación si storage falla.
    }
}

if (typeof document !== 'undefined') {
    applyTheme(preferredTheme());

    document.addEventListener('DOMContentLoaded', () => {
        applyTheme(preferredTheme());
        document.addEventListener('click', (event) => {
            const button = event.target.closest?.('[data-theme-toggle]');
            if (!button) return;
            const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            storeTheme(next);
            applyTheme(next);
        });
    });
}
