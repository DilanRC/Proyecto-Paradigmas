const THEME_KEY = 'tindercows:theme';

function preferredTheme() {
    const saved = localStorage.getItem(THEME_KEY);
    if (saved === 'light' || saved === 'dark') return saved;
    return window.matchMedia?.('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
}

function applyTheme(theme) {
    const root = document.documentElement;
    root.dataset.theme = theme;
    root.style.colorScheme = theme;

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', theme === 'light' ? '#fef7ec' : '#151a18');

    for (const button of document.querySelectorAll('[data-theme-toggle]')) {
        const nextIsLight = theme === 'dark';
        button.setAttribute('aria-pressed', String(theme === 'dark'));
        button.setAttribute('aria-label', nextIsLight ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
        const label = button.querySelector('.theme-toggle__label');
        const icon = button.querySelector('.theme-toggle__icon');
        if (label) label.textContent = nextIsLight ? 'Claro' : 'Oscuro';
        if (icon) icon.textContent = nextIsLight ? '☀' : '☾';
    }
}

applyTheme(preferredTheme());

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(preferredTheme());
    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-theme-toggle]');
        if (!button) return;
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem(THEME_KEY, next);
        applyTheme(next);
    });
});
