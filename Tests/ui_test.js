const fs = require('node:fs');
const assert = require('node:assert');

// El panel se reparte entre su archivo de entrada y los modulos compartidos que
// importa; el control se busca sobre el grafo completo y no solo sobre la entrada.
const sharedDir = 'Public/js/shared';
const sharedModules = fs.existsSync(sharedDir)
    ? fs.readdirSync(sharedDir).filter((file) => file.endsWith('.js'))
    : [];
const leer = (file) => fs.readFileSync(`${sharedDir}/${file}`, 'utf8');
const shared = sharedModules.map(leer).join('\n');
const js = `${fs.readFileSync('Public/js/productores.js', 'utf8')}\n${shared}`;
const view = fs.readFileSync('Application/View/productores/index.php', 'utf8');

assert(js.includes('fetch('), 'La UI debe usar fetch.');
// auth-gate.js es la puerta de navegación del shell privado: su contrato es
// redirigir a login.php cuando no hay sesión y al cerrar sesión. Esa
// navegación no es una fuga del CRUD; se excluye solo de la cláusula de
// no-navegación, que sigue aplicando a productores.js y a los demás módulos.
const sinPuerta = `${fs.readFileSync('Public/js/productores.js', 'utf8')}\n${
    sharedModules.filter((file) => file !== 'auth-gate.js').map(leer).join('\n')
}`;
// Lo que se prohibe es navegar o recargar, no leer la URL: la ficha de un
// comprador enlaza a la misma persona en otro panel con ?q=<identificacion>, y
// ese panel debe poder leer el parametro. Prohibir `window.location` entero
// daba un falso positivo sobre esa lectura.
const navega = [
    /location\.reload\s*\(/, /location\.assign\s*\(/, /location\.replace\s*\(/,
    /location\.href\s*=[^=]/, /window\.location\s*=[^=]/,
];
for (const patron of navega) {
    assert(!patron.test(sinPuerta), `El CRUD no debe recargar ni navegar la página: ${patron}`);
}
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
assert(view.includes('id="fincas-lista"') && view.includes('id="agregar-finca"'),
    'Fincas deben capturarse por nombre con el componente "Agregar finca" (Tramo C).');
assert(view.includes('id="error-fincas"'), 'El error de fincas mantiene su espacio de mensaje.');
assert(js.includes('aria-invalid') && view.includes('aria-live'), 'Debe conservar accesibilidad.');
// El doble envio se evita con una guarda que levanta su bandera de forma
// sincrona, antes de cualquier await, para que dos clics del mismo turno no
// puedan colarse los dos.
assert(js.includes('createSubmitGuard') && js.includes('busy'), 'Debe prevenir doble envío.');
assert(js.includes('setSaving'), 'El formulario debe bloquearse mientras se envía.');

console.log('OK ui_test: AJAX, identificación inmutable, fincas por nombre, carreras y ARIA.');
