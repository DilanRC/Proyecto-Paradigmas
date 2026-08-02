const fs = require('node:fs');
const assert = require('node:assert');

const js = fs.readFileSync('Public/js/productores.js', 'utf8');
const view = fs.readFileSync('Application/View/productores/index.php', 'utf8');

assert(js.includes('fetch('), 'La UI debe usar fetch.');
assert(!js.includes('window.location') && !js.includes('location.reload'), 'El CRUD no debe recargar la página.');
assert(js.includes('textContent'), 'Datos externos deben insertarse con textContent.');
assert(js.includes('AbortController') && js.includes('listSequence'), 'Debe prevenir carreras de listados.');
assert(js.includes('identificacionNumero') && !js.includes('participanteId'), 'La UI debe usar la PK natural.');
assert(!js.includes('fincaId'), 'No debe existir ID artificial de finca.');
assert(view.includes('id="identificacion-original"'), 'El formulario debe conservar la PK original al editar.');
assert(view.includes('id="fincas-nombres"'), 'Fincas deben capturarse por nombre.');
assert(js.includes('aria-invalid') && view.includes('aria-live'), 'Debe conservar accesibilidad.');
assert(js.includes('saving') && js.includes('changingStatus'), 'Debe prevenir doble envío.');

console.log('OK ui_test: AJAX, PK natural, fincas por nombre, carreras y ARIA.');
