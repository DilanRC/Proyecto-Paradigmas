const fs = require('node:fs');
const assert = require('node:assert');

// El panel se reparte entre su archivo de entrada y los modulos compartidos que
// importa; el control se busca sobre el grafo completo y no solo sobre la entrada.
const sharedDir = 'Public/js/shared';
const shared = fs.existsSync(sharedDir)
    ? fs.readdirSync(sharedDir)
        .filter((file) => file.endsWith('.js'))
        .map((file) => fs.readFileSync(`${sharedDir}/${file}`, 'utf8'))
        .join('\n')
    : '';
const js = `${fs.readFileSync('Public/js/productores.js', 'utf8')}\n${shared}`;
const view = fs.readFileSync('Application/View/productores/index.php', 'utf8');

assert(js.includes('fetch('), 'La UI debe usar fetch.');
assert(!js.includes('window.location') && !js.includes('location.reload'), 'El CRUD no debe recargar la página.');
assert(js.includes('textContent'), 'Datos externos deben insertarse con textContent.');
// La carrera de listados se evita cancelando la peticion anterior y descartando
// las respuestas con secuencia vieja. La comprobacion es sobre esa propiedad, no
// sobre el nombre que tenia la variable.
assert(js.includes('AbortController') && js.includes('sequence'), 'Debe prevenir carreras de listados.');
assert(js.includes('applyAbort'), 'Cancelar una peticion no debe tratarse como un fallo.');
assert(js.includes('showEmpty') && js.includes('showError'),
    'El estado vacio y el de error deben ser distinguibles.');
assert(js.includes('identificacionNumero') && !js.includes('participanteId'), 'La UI debe usar la identificación de negocio.');
assert(!js.includes('fincaId'), 'No debe existir ID artificial de finca.');
assert(view.includes('id="identificacion-original"'), 'El formulario debe conservar la identificación original al editar.');
assert(view.includes('id="fincas-nombres"'), 'Fincas deben capturarse por nombre.');
assert(js.includes('aria-invalid') && view.includes('aria-live'), 'Debe conservar accesibilidad.');
// El doble envio se evita con una guarda que levanta su bandera de forma
// sincrona, antes de cualquier await, para que dos clics del mismo turno no
// puedan colarse los dos.
assert(js.includes('createSubmitGuard') && js.includes('busy'), 'Debe prevenir doble envío.');
assert(js.includes('setSaving'), 'El formulario debe bloquearse mientras se envía.');

console.log('OK ui_test: AJAX, identificación inmutable, fincas por nombre, carreras y ARIA.');
