import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { ESTADO_CUENTA, estadoPorError, esRegistroIncompleto } from '../../Public/js/shared/estado-cuenta.js';

function modulo(path, bindings) {
    const source = readFileSync(new URL(`../../Public/js/${path}`, import.meta.url), 'utf8')
        .replace(/^import .*;\r?\n/gm, '').replace(/^export /gm, '');
    const context = vm.createContext({ URLSearchParams, setInterval() {}, ...bindings });
    vm.runInContext(source, context, { filename: path });
    return context;
}

class Nodo {
    constructor() { this.hidden = true; this.dataset = {}; this.events = {}; this.elements = { monto: { value: '100' } }; this.classList = { add() {}, remove() {} }; }
    addEventListener(name, callback) { this.events[name] = callback; }
    setAttribute() {}
    querySelector() { return new Nodo(); }
    querySelectorAll() { return []; }
    replaceChildren() {}
}

for (const status of [403, 409, 401]) {
    for (const admin of [true, false]) {
        test(`login con ${status}, admin=${admin}: verifica autorización antes de cerrar una sesión válida`, async () => {
            const form = new Nodo();
            const button = new Nodo();
            form.querySelector = () => button;
            const message = new Nodo();
            let submit;
            form.addEventListener = (name, callback) => { if (name === 'submit') submit = callback; };
            const redirects = [];
            let signouts = 0;
            let checks = 0;
            const context = modulo('login.js', {
                document: { querySelector: (s) => s === '#formulario-login' ? form : message, addEventListener() {} },
                HTMLFormElement: Nodo, HTMLButtonElement: Nodo,
                FormData: class { get(key) { return key === 'email' ? 'admin@example.test' : 'password'; } },
                window: { location: { pathname: '/admin/entrar', search: '', assign: (url) => redirects.push(url) } },
                ESTADO_CUENTA, estadoPorError,
                clearAdminBrowserSession() {}, writeAdminBrowserSession() {}, readPublicProfile() {},
                signInWithPassword: async () => {}, signOut: async () => { signouts++; },
                request: async () => { throw Object.assign(new Error('Persona no disponible'), { status }); },
                getAccessToken: async () => 'fixture',
                fetch: async () => { checks++; return { ok: admin }; },
            });
            vm.runInContext('initialize()', context);
            await submit({ preventDefault() {} });
            const allowed = admin && status !== 401;
            assert.equal(redirects[0], allowed ? 'admin/dashboard' : undefined);
            assert.equal(signouts, allowed ? 0 : 1);
            assert.equal(checks, status === 401 ? 0 : 1);
        });
    }
}

function subastas(request) {
    const nodes = new Map();
    const node = (selector) => {
        if (selector === '[data-cuenta-pendiente]') return null;
        if (!nodes.has(selector)) nodes.set(selector, new Nodo());
        return nodes.get(selector);
    };
    const context = modulo('subastas.js', {
        document: { readyState: 'loading', addEventListener() {}, querySelector: node },
        window: { confirm: () => true }, request,
        ESTADO_CUENTA, esRegistroIncompleto, estadoPublicado: () => '', erroresNativos: () => ({}),
        iniciarProgreso: () => ({ terminar() {} }),
        escuchar() {}, agrupar: (fn) => fn, formatPrice: String,
    });
    vm.runInContext('sincronizarHora = () => {}; relojes = () => {}; vistaDetalle = (state) => { globalThis.detailView = state; document.querySelector("#subasta-loading").hidden = state !== "loading"; }; pintarDetalle = (s) => { detalle.subasta = s; };', context);
    return { context, node };
}

test('una recarga silenciosa no desplaza una carga visible y deja siempre un error recuperable', async () => {
    const pending = [];
    const { context, node } = subastas(() => new Promise((resolve, reject) => pending.push({ resolve, reject })));
    const visible = vm.runInContext('cargarLista()', context);
    const silent = vm.runInContext('cargarLista({ silencioso: true })', context);
    pending[0].reject(new Error('Sin conexión'));
    await visible;
    if (pending[1]) pending[1].reject(new Error('Sin conexión'));
    await silent;
    assert.equal(node('#subastas-loading').hidden, true);
    assert.equal(node('#subastas-error').hidden, false);
});

for (const action of ['PUJAR', 'CANCELAR']) {
    test(`una lectura anterior a ${action} no pisa la respuesta de la escritura`, async () => {
        let oldRead;
        const updated = { precioActual: 200, estado: action === 'CANCELAR' ? 'CANCELADA' : 'ACTIVA' };
        const { context, node } = subastas((url, options) => options?.method === 'PATCH'
            ? Promise.resolve({ data: { subasta: updated } })
            : new Promise((resolve) => { oldRead = resolve; }));
        vm.runInContext('iniciarDetalle(4)', context);
        const trigger = action === 'PUJAR' ? node('#subasta-pujar').events.submit : node('#subasta-cancelar').events.click;
        await trigger({ preventDefault() {}, currentTarget: new Nodo() });
        oldRead({ data: { subasta: { precioActual: 100, estado: 'ACTIVA' } } });
        await new Promise((resolve) => setImmediate(resolve));
        assert.equal(vm.runInContext('detalle.subasta.precioActual', context), 200);
        assert.equal(vm.runInContext('detalle.subasta.estado', context), updated.estado);
    });
}

test('si falla una recarga silenciosa que desplazó al detalle visible, sale del esqueleto', async () => {
    const pending = [];
    const { context } = subastas(() => new Promise((resolve, reject) => pending.push({ resolve, reject })));
    const visible = vm.runInContext('cargarDetalle()', context);
    const silent = vm.runInContext('cargarDetalle({ silencioso: true })', context);
    pending[0].reject(new Error('Sin conexión'));
    await visible;
    pending[1].reject(new Error('Sin conexión'));
    await silent;
    assert.equal(context.detailView, 'error');
});
