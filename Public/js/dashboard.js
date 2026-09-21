import { request } from './shared/api.js';

const SOURCES = [
    { key: 'productores', label: 'Productores', icon: 'fa-cow', href: 'admin/productores', query: { pagina: 1, tamanoPagina: 1 } },
    { key: 'compradores', label: 'Compradores', icon: 'fa-handshake', href: 'admin/compradores', query: { pagina: 1, tamanoPagina: 1 } },
    { key: 'transportistas', label: 'Transportistas', icon: 'fa-truck-fast', href: 'admin/transportistas', query: { pagina: 1, tamanoPagina: 1 } },
    { key: 'vehiculos', label: 'Vehículos activos', icon: 'fa-truck-pickup', href: 'admin/vehiculos', query: { pagina: 1, tamanoPagina: 1, estado: 'ACTIVO' } },
];

function formatCount(value) {
    return Number.isFinite(Number(value)) ? new Intl.NumberFormat('es-CR').format(Number(value)) : '—';
}

async function readSource(source) {
    const endpoint = source.key === 'productores' ? 'api/v1/productores'
        : source.key === 'compradores' ? 'api/v1/compradores'
            : source.key === 'transportistas' ? 'api/v1/transportistas' : 'api/v1/vehiculos';
    try {
        const response = await request(endpoint, {
            method: 'POST',
            body: JSON.stringify({ consulta: source.query }),
        });
        return { ...source, total: Number(response.data?.total) || 0, ok: true };
    } catch (error) {
        return { ...source, total: null, ok: false, error };
    }
}

function renderMetrics(results) {
    const target = document.querySelector('#dashboard-metrics');
    if (!target) return;
    target.replaceChildren(...results.map((result) => {
        const card = document.createElement('article');
        card.className = `dashboard-metric ${result.ok ? '' : 'dashboard-metric--error'}`;
        const icon = document.createElement('span');
        icon.className = 'dashboard-metric__icon';
        icon.innerHTML = `<i class="fa-solid ${result.icon}" aria-hidden="true"></i>`;
        const label = document.createElement('span');
        label.className = 'dashboard-metric__label';
        label.textContent = result.label;
        const value = document.createElement('strong');
        value.className = 'dashboard-metric__value';
        value.textContent = result.ok ? formatCount(result.total) : 'No disponible';
        const link = document.createElement('a');
        link.className = 'dashboard-metric__link';
        link.href = result.href;
        link.textContent = result.ok ? 'Abrir módulo' : 'Reintentar en módulo';
        card.append(icon, label, value, link);
        return card;
    }));
    target.setAttribute('aria-busy', 'false');
}

async function loadDashboard() {
    const status = document.querySelector('#dashboard-status');
    const refresh = document.querySelector('#dashboard-refresh');
    if (refresh) refresh.disabled = true;
    if (status) status.textContent = 'Actualizando indicadores…';
    const results = await Promise.all(SOURCES.map(readSource));
    renderMetrics(results);
    const failed = results.filter((result) => !result.ok).length;
    if (status) status.textContent = failed === 0
        ? `Actualizado a las ${new Intl.DateTimeFormat('es-CR', { hour: '2-digit', minute: '2-digit' }).format(new Date())}.`
        : `${failed} indicador${failed === 1 ? '' : 'es'} no pudo${failed === 1 ? '' : 'ieron'} cargarse. Los módulos siguen disponibles.`;
    if (refresh) refresh.disabled = false;
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelector('#dashboard-refresh')?.addEventListener('click', loadDashboard);
        loadDashboard();
    }, { once: true });
}
