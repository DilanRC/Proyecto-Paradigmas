// Eval: las tres capacidades de una persona tienen panel y se alcanzan entre si.
//
// Sustituye a frontend_retirement_eval.js, que puntuaba lo contrario: el tramo 7
// habia retirado Comprador del frontend por secuenciacion (DEC-TRAMO-7), no por
// un defecto. Al recuperarlo, la propiedad que hay que vigilar se invierte.
//
// DEC-PER-001: una persona es productor (vendedor), comprador y/o transportista,
// y puede no estar registrada en ninguna. Si un panel desaparece del menu, esa
// lectura del modelo deja de ser navegable aunque la API siga respondiendo.

const fs = require('node:fs');
const assert = require('node:assert');

// Paneles que representan una capacidad de la persona.
const capacidades = ['productores', 'compradores', 'transportistas'];
// Paneles de apoyo: no son capacidades, pero comparten el menu.
const paneles = [...capacidades, 'vehiculos', 'pagometodos'];

const checks = [
    ...paneles.map((panel) => ({
        name: `menu_completo_${panel}`,
        // Desde cualquier panel se llega a las tres capacidades.
        pass: capacidades.every((destino) => fs
            .readFileSync(`Application/View/${panel}/index.php`, 'utf8')
            .includes(`href="${destino}.php"`)),
    })),
    { name: 'vista_compradores', pass: fs.existsSync('Application/View/compradores/index.php') },
    { name: 'ruta_compradores', pass: fs.existsSync('Public/compradores.php') },
    { name: 'javascript_compradores', pass: fs.existsSync('Public/js/compradores.js') },
    {
        name: 'modulo_capacidades',
        pass: fs.existsSync('Public/js/shared/capacidades.js'),
    },
    {
        // La ficha del comprador debe consultar las capacidades; sin esto el
        // panel vuelve a tratar al comprador como una identidad aislada.
        name: 'ficha_consulta_capacidades',
        pass: fs.readFileSync('Public/js/compradores.js', 'utf8').includes('consultarCapacidades'),
    },
    // La ficha ofrece "Abrir panel" con ?q=<identificacion>. Un panel que no lea
    // ese parametro recibe la visita y muestra la lista sin filtrar, de modo que
    // el enlace parece funcionar y no lleva a la persona. Falla en silencio.
    ...capacidades.map((panel) => ({
        name: `enlace_profundo_${panel}`,
        pass: fs.readFileSync(`Public/js/${panel}.js`, 'utf8').includes("get('q')"),
    })),
];

const passed = checks.filter((check) => check.pass).length;
const score = passed / checks.length;
const fallidas = checks.filter((check) => !check.pass).map((check) => check.name);
assert.strictEqual(
    score,
    1,
    `Capacidades incompletas: ${passed}/${checks.length} verificaciones aprobadas; fallan ${fallidas.join(', ')}`,
);

console.log(`OK frontend_capacidades_eval: ${passed}/${checks.length} verificaciones aprobadas (score=${score.toFixed(2)}).`);
