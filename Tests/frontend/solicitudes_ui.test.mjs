import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (ruta) => readFileSync(ruta, 'utf8').replace(/\r\n/g, '\n');
const panel = read('Public/js/mi-actividad.js');
const panelVista = read('Application/View/mi-actividad/index.php');
const dialogo = read('Public/js/shared/solicitud-compra.js');

test('Mi panel tiene las tres bandejas de solicitudes y el JS usa sus ids', () => {
    for (const clave of ['hechas', 'recibidas', 'fletes']) {
        for (const parte of ['panel', 'list', 'count', 'title']) {
            assert.match(panelVista, new RegExp(`id="sol-${clave}-${parte}"`), `falta #sol-${clave}-${parte}`);
        }
    }
    assert.match(panel, /querySelector\(`#sol-\$\{clave\}-panel`\)/);
    assert.match(panel, /const SOLICITUDES_API = 'api\/v1\/solicitudes-compra'/);
    assert.match(panel, /await loadSolicitudes\(\);/);
});

test('las acciones de las bandejas coinciden con las de la API', () => {
    for (const accion of ['CANCELAR', 'ACEPTAR', 'RECHAZAR', 'ACEPTAR_FLETE', 'RECHAZAR_FLETE']) {
        assert.match(panel, new RegExp(`'${accion}'`));
        assert.match(read('Application/Controller/SolicitudesCompraController.php'), new RegExp(`'${accion}'`));
    }
    // Una publicación "a convenir" pide el precio al aceptar; el rechazo pide un motivo opcional.
    assert.match(panel, /solicitud\.precio === null/);
    assert.match(panel, /cuerpo\.precio = precio/);
    assert.match(panel, /cuerpo\.motivo = motivo\.trim\(\)/);
});

test('todo texto de la base se escapa en las filas de solicitudes', () => {
    for (const campo of ['s.publicacion.titulo', 's.mensaje', 's.respuestaMotivo', 's.vendedor.nombre', 's.flete.transportista.nombre']) {
        assert.ok(panel.includes(`escapeHtml(${campo}`), `${campo} debe escaparse`);
    }
    assert.match(panel, /parte\.telefono \? `\$\{escapeHtml\(parte\.nombre\)\} · \$\{escapeHtml\(parte\.telefono\)\}`/);
});

test('Explorar tiene "Solicitar compra" como acción principal y la abre sin enviarla como interacción', () => {
    const explore = read('Public/js/explore.js');
    assert.match(explore, /\['Solicitar compra', 'fa-handshake'\]/);
    assert.match(explore, /accion === 'Solicitar compra' \? 'is-primary' : null/);
    const interacciones = read('Public/js/explore-interactions.js');
    assert.match(interacciones, /dataset\.exploreAction === 'Solicitar compra'/);
    assert.match(interacciones, /solicitud-compra\.js\?v=solicitud-2/);
    assert.doesNotMatch(interacciones.match(/const ACTION_TYPES = \{[^}]*\}/)[0], /Solicitar/);
    const vista = read('Application/View/explorar/index.php');
    assert.match(vista, /css\/solicitud\.css\?v=solicitud-2/);
    assert.match(vista, /js\/explore\.js\?v=explore-12/);
    assert.match(vista, /js\/explore-interactions\.js\?v=interactions-4/);
});

test('Me interesa ofrece solicitar y ver fletes cercanos solo si la publicación sigue activa', () => {
    const interesa = read('Public/js/me-interesa.js');
    assert.match(interesa, /publicacion\.estado === 'ACTIVO'/);
    assert.match(interesa, /abrir\(false\)/);
    assert.match(interesa, /abrir\(true\)/);
    assert.match(interesa, /Ver fletes cercanos/);
    const vista = read('Application/View/me-interesa/index.php');
    assert.match(vista, /css\/solicitud\.css\?v=solicitud-2/);
    assert.match(vista, /js\/me-interesa\.js\?v=interesa-5/);
});

test('el diálogo usa la API de solicitudes y los fletes por publicación, sin innerHTML', () => {
    assert.match(dialogo, /api\/v1\/solicitudes-compra/);
    assert.match(dialogo, /api\/v1\/fletes\?publicacionId=/);
    assert.doesNotMatch(dialogo, /innerHTML/);
    assert.match(dialogo, /cuerpo\.ofertaId = Number\(elegida\.value\)/);
    // El navegador nunca manda el comprador ni el precio: los decide el servidor.
    assert.doesNotMatch(dialogo, /compradorId|precio:/);
});

test('el estilo del diálogo usa solo variables --tc-* para el color', () => {
    const css = read('Public/css/solicitud.css');
    assert.doesNotMatch(css.replace(/rgba\([^)]*\)/g, '').replace(/#fff\b/g, ''), /#[0-9a-fA-F]{3,6}\b(?![^{]*\{)/);
    assert.match(css, /var\(--tc-primary\)/);
    assert.match(css, /:has\(input:focus-visible\)/);
});

test('el diálogo ofrece el método de pago opcional, sin romperse si falla la carga, y Mi panel lo muestra', () => {
    assert.match(dialogo, /api\/v1\/pago-metodos-disponibles/);
    assert.match(dialogo, /Método de pago \(opcional\)/);
    assert.match(dialogo, /if \(selectorPago\.value\) cuerpo\.pagoMetodoId = Number\(selectorPago\.value\)/);
    assert.match(dialogo, /catch \{\s*pago\.hidden = true;/);
    assert.match(dialogo, /pago\.hidden = true;\n/);
    assert.match(panel, /Método de pago: \$\{escapeHtml\(s\.pagoMetodo\.nombre\)\}/);
    const ruta = read('Public/.htaccess');
    assert.match(ruta, /RewriteRule \^api\/v1\/pago-metodos-disponibles\/\?\$ api\/pago-metodos-disponibles\.php \[END\]/);
    // El endpoint de clientes pide sesión y no toca el de administrador.
    assert.match(read('Public/api/pago-metodos-disponibles.php'), /requerirAutenticado/);
    assert.match(read('Public/.htaccess'), /api\/v1\/metodos-pago/);
});
