// Eval: Productor y Transportista siguen siendo capacidades operativas; Comprador
// es una clasificación derivada del Productor con panel de solo lectura.
//
// El objetivo de navegación se conserva: desde las fichas se puede consultar la
// misma identidad en Productor, Comprador y Transportista. Lo que NO se permite
// es volver a tratar Comprador como un registro administrable ni usar Productor
// como alias de Vendedor.

const fs = require('node:fs');
const assert = require('node:assert');

const destinos = ['productores', 'compradores', 'transportistas'];
const paneles = [...destinos, 'vehiculos', 'pagometodos'];
const capacidadesJs = fs.readFileSync('Public/js/shared/capacidades.js', 'utf8');
const compradoresJs = fs.readFileSync('Public/js/compradores.js', 'utf8');
const compradoresVista = fs.readFileSync('Application/View/compradores/index.php', 'utf8');

const checks = [
    ...paneles.map((panel) => ({
        name: `menu_completo_${panel}`,
        pass: destinos.every((destino) => fs
            .readFileSync(`Application/View/${panel}/index.php`, 'utf8')
            .includes(`href="${destino}.php"`)),
    })),
    { name: 'vista_compradores', pass: fs.existsSync('Application/View/compradores/index.php') },
    { name: 'ruta_compradores', pass: fs.existsSync('Public/compradores.php') },
    { name: 'javascript_compradores', pass: fs.existsSync('Public/js/compradores.js') },
    { name: 'modulo_relaciones_persona', pass: fs.existsSync('Public/js/shared/capacidades.js') },
    {
        name: 'comprador_marcado_derivado',
        // El comentario que documenta por qué Comprador es derivado puede crecer;
        // el gate no debe fallar solo porque se agreguen unas líneas explicativas.
        pass: /clave:\s*'comprador'[\s\S]{0,800}derivada:\s*true/.test(capacidadesJs),
    },
    {
        name: 'productor_no_alias_vendedor',
        pass: /clave:\s*'productor'[\s\S]{0,220}alias:\s*null/.test(capacidadesJs)
            && !/alias:\s*'vendedor'/.test(capacidadesJs),
    },
    {
        name: 'comprador_solo_lectura_sin_payload',
        pass: !compradoresJs.includes('buildCompradorPayload')
            && !['POST', 'PUT', 'DELETE', 'PATCH'].some((metodo) => compradoresJs.includes(`'${metodo}'`)),
    },
    {
        name: 'comprador_solo_lectura_sin_formulario',
        pass: !compradoresVista.includes('id="crear-comprador"')
            && !compradoresVista.includes('id="formulario-comprador"')
            && !compradoresVista.includes('id="modal-desactivar"'),
    },
    {
        name: 'ficha_consulta_relaciones',
        pass: compradoresJs.includes('consultarCapacidades'),
    },
    // La ficha enlaza con ?q=<identificacion>; cada panel destino debe leer ese
    // parámetro para que el enlace profundo lleve a la misma persona.
    ...destinos.map((panel) => ({
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
    `Relaciones de persona incompletas: ${passed}/${checks.length}; fallan ${fallidas.join(', ')}`,
);

console.log(`OK frontend_capacidades_eval: ${passed}/${checks.length} verificaciones aprobadas (score=${score.toFixed(2)}).`);
