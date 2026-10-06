// Diálogo "Solicitar compra": el comprador pide el animal, con flete opcional, y el vendedor
// decide (api/v1/solicitudes-compra). Los fletes son los que cubren la finca del animal
// (api/v1/fletes?publicacionId=): el servidor resuelve el punto de la finca, el navegador no lo conoce.
import { request } from './api.js';

const SOLICITUDES_API = 'api/v1/solicitudes-compra';
const FLETES_API = 'api/v1/fletes';
const PAGO_METODOS_API = 'api/v1/pago-metodos-disponibles';

function el(tag, className, text) {
    const nodo = document.createElement(tag);
    if (className) nodo.className = className;
    if (text !== undefined) nodo.textContent = text;
    return nodo;
}

function colones(precio) {
    if (typeof precio !== 'number' || !Number.isFinite(precio)) return 'Precio a convenir';
    return `₡${String(Math.round(precio)).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
}

function ubicacion(zona) {
    return [zona?.pueblo, zona?.distrito, zona?.canton, zona?.provincia].filter(Boolean).join(', ') || 'Zona no registrada';
}

function opcion(nombre, valor, titulo, detalle, marcada = false) {
    const etiqueta = el('label', 'solicitud-option');
    const radio = el('input');
    radio.type = 'radio';
    radio.name = nombre;
    radio.value = valor;
    radio.checked = marcada;
    const texto = el('span');
    texto.append(titulo);
    if (detalle) texto.append(el('small', null, detalle));
    etiqueta.append(radio, texto);
    return etiqueta;
}

/**
 * @param {{publicacionId:number, titulo?:string, precio?:string, conFlete?:boolean,
 *          aviso?:(mensaje:string, tipo:'ok'|'error')=>void, alEnviar?:(solicitud:object)=>void}} opciones
 */
export function abrirSolicitudCompra({ publicacionId, titulo = 'Publicación', precio = '', conFlete = false, aviso = () => {}, alEnviar = () => {} }) {
    const dialogo = el('dialog', 'solicitud-dialog');
    dialogo.setAttribute('aria-labelledby', 'solicitud-titulo');
    const form = el('form', 'solicitud-form');
    form.noValidate = true;

    const encabezado = el('div', 'solicitud-form__header');
    const textos = el('div');
    const h2 = el('h2', null, titulo);
    h2.id = 'solicitud-titulo';
    textos.append(el('p', 'solicitud-form__kicker', 'Solicitud de compra'), h2);
    if (precio) textos.append(el('p', 'solicitud-form__price', precio));
    const cerrar = el('button', 'solicitud-close', '×');
    cerrar.type = 'button';
    cerrar.setAttribute('aria-label', 'Cerrar');
    encabezado.append(textos, cerrar);

    const modos = el('fieldset', 'solicitud-options');
    modos.append(el('legend', null, '¿Cómo quieres recibirlo?'),
        opcion('modo', 'solo', 'Solo el animal', 'Coordinas el traslado por tu cuenta.', !conFlete),
        opcion('modo', 'flete', 'El animal con flete', 'Eliges un transportista que cubra la finca.', conFlete));

    const fletes = el('div', 'solicitud-fletes');
    fletes.hidden = !conFlete;
    const mensaje = el('label', 'solicitud-field');
    const area = el('textarea');
    area.name = 'mensaje';
    area.maxLength = 500;
    area.rows = 3;
    area.placeholder = 'Ej.: Me interesa, ¿podemos coordinar la visita?';
    mensaje.append(el('span', null, 'Mensaje para el vendedor (opcional)'), area);

    // Método de pago opcional: se llena al abrir. Si la carga falla o no hay métodos, el campo queda oculto y el diálogo sigue igual.
    const pago = el('label', 'solicitud-field');
    pago.hidden = true;
    const selectorPago = el('select');
    selectorPago.name = 'pagoMetodoId';
    selectorPago.append(new Option('Sin preferencia', ''));
    pago.append(el('span', null, 'Método de pago (opcional)'), selectorPago);

    const estado = el('p', 'solicitud-status');
    estado.setAttribute('role', 'status');
    estado.setAttribute('aria-live', 'polite');
    const cancelar = el('button', null, 'Cancelar');
    cancelar.type = 'button';
    const enviar = el('button', 'is-primary', 'Enviar solicitud');
    enviar.type = 'submit';
    const acciones = el('div', 'solicitud-actions');
    acciones.append(cancelar, enviar);
    form.append(encabezado, modos, fletes, pago, mensaje, estado, acciones);
    dialogo.append(form);
    document.body.append(dialogo);

    const cerrarDialogo = () => {
        if (dialogo.open) dialogo.close();
        dialogo.remove();
    };
    const mostrar = (texto, tipo = 'info') => {
        estado.textContent = texto;
        estado.dataset.tipo = tipo;
    };
    const modoFlete = () => form.elements.namedItem('modo').value === 'flete';

    let fletesCargados = false;
    async function cargarFletes() {
        if (fletesCargados) return;
        fletesCargados = true;
        fletes.replaceChildren(el('p', 'solicitud-note', 'Buscando fletes que cubran la finca…'));
        try {
            const respuesta = await request(`${FLETES_API}?publicacionId=${encodeURIComponent(publicacionId)}&tamanoPagina=50`);
            const ofertas = respuesta.data?.ofertas ?? [];
            if (!ofertas.length) {
                fletes.replaceChildren(el('p', 'solicitud-note', 'Todavía ningún flete cubre la finca de este animal. Puedes solicitar solo el animal.'));
                return;
            }
            fletes.replaceChildren(...ofertas.map((oferta) => opcion(
                'oferta', String(oferta.ofertaId),
                `${oferta.vehiculo?.modelo || 'Flete'} · hasta ${oferta.capacidad} cabezas · ${colones(oferta.precio)}`,
                `${oferta.transportista || ''} · ${ubicacion(oferta.zona)} · a ${Number(oferta.distanciaKm).toLocaleString('es-CR', { maximumFractionDigits: 1 })} km de la finca`,
            )));
        } catch (error) {
            fletesCargados = false;
            fletes.replaceChildren(el('p', 'solicitud-note', error?.errors?.publicacionId || error?.message || 'No pudimos buscar fletes ahora.'));
        }
    }

    async function cargarMetodosPago() {
        try {
            const respuesta = await request(PAGO_METODOS_API);
            const metodos = respuesta.data?.metodos ?? [];
            selectorPago.append(...metodos.map((metodo) => new Option(metodo.nombre, String(metodo.pagoMetodoId))));
            pago.hidden = metodos.length === 0;
        } catch {
            pago.hidden = true;
        }
    }

    form.addEventListener('change', (evento) => {
        if (evento.target.name !== 'modo') return;
        fletes.hidden = !modoFlete();
        if (modoFlete()) cargarFletes();
        mostrar('');
    });
    cerrar.addEventListener('click', cerrarDialogo);
    cancelar.addEventListener('click', cerrarDialogo);
    dialogo.addEventListener('cancel', (evento) => { evento.preventDefault(); cerrarDialogo(); });

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        const cuerpo = { publicacionId };
        if (modoFlete()) {
            const elegida = form.querySelector('input[name="oferta"]:checked');
            if (!elegida) { mostrar('Elige un flete, o cambia a "Solo el animal".', 'error'); return; }
            cuerpo.ofertaId = Number(elegida.value);
        }
        if (selectorPago.value) cuerpo.pagoMetodoId = Number(selectorPago.value);
        if (area.value.trim()) cuerpo.mensaje = area.value.trim();
        enviar.disabled = true;
        mostrar('Enviando solicitud…');
        try {
            const respuesta = await request(SOLICITUDES_API, { method: 'POST', body: JSON.stringify(cuerpo) });
            cerrarDialogo();
            aviso(respuesta.message || 'Solicitud enviada.', 'ok');
            alEnviar(respuesta.data?.solicitud);
        } catch (error) {
            enviar.disabled = false;
            mostrar(error?.errors?.ofertaId || error?.errors?.pagoMetodoId || error?.message || 'No pudimos enviar la solicitud.', 'error');
        }
    });

    dialogo.showModal();
    cargarMetodosPago();
    if (conFlete) cargarFletes();
    (conFlete ? form.querySelector('input[name="modo"]:checked') : form.querySelector('input[name="modo"]'))?.focus();
}
