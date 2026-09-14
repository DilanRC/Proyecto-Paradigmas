// Eval vigente para Avance 2: Productor, Comprador y Transportista son
// contextos de negocio independientes relacionados con una misma Persona.
// Ninguno es un rol administrativo ni una clasificación derivada de otro.

const fs = require('node:fs');
const assert = require('node:assert');

const destinos = ['productores', 'compradores', 'transportistas'];
const paneles = [...destinos, 'vehiculos', 'pagometodos'];
const capacidadesJs = fs.readFileSync('Public/js/shared/capacidades.js', 'utf8');
const compradoresJs = fs.readFileSync('Public/js/compradores.js', 'utf8');
const compradoresVista = fs.readFileSync('Application/View/compradores/index.php', 'utf8');
const compradorController = fs.readFileSync('Application/Controller/CompradorConsultaController.php', 'utf8');

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
    { name: 'modelo_comprador_contexto', pass: fs.existsSync('Application/Model/Comprador.php') },
    { name: 'modulo_relaciones_persona', pass: fs.existsSync('Public/js/shared/capacidades.js') },
    {
        name: 'comprador_contexto_independiente',
        pass: /clave:\s*'comprador'[\s\S]{0,300}derivada:\s*false/.test(capacidadesJs),
    },
    {
        name: 'ninguna_capacidad_es_rol_derivado',
        pass: !/derivada:\s*true/.test(capacidadesJs),
    },
    {
        name: 'productor_no_alias_vendedor',
        pass: /clave:\s*'productor'[\s\S]{0,220}alias:\s*null/.test(capacidadesJs)
            && !/alias:\s*'vendedor'/.test(capacidadesJs),
    },
    {
        name: 'comprador_consulta_tbcomprador',
        pass: compradorController.includes('new Comprador($conexion)')
            && compradorController.includes("$resultado['fuente'] = 'tbcomprador + tbpersona';"),
    },
    {
        name: 'comprador_solo_lectura_administrativa',
        pass: !compradoresJs.includes('buildCompradorPayload')
            && !['POST', 'PUT', 'DELETE', 'PATCH'].some((metodo) => compradoresJs.includes(`'${metodo}'`)),
    },
    {
        name: 'comprador_sin_formulario_crud',
        pass: !compradoresVista.includes('id="crear-comprador"')
            && !compradoresVista.includes('id="formulario-comprador"')
            && !compradoresVista.includes('id="modal-desactivar"'),
    },
    {
        name: 'ficha_consulta_relaciones',
        pass: compradoresJs.includes('consultarCapacidades'),
    },
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
    `Relaciones de Persona incompletas: ${passed}/${checks.length}; fallan ${fallidas.join(', ')}`,
);

console.log(`OK frontend_capacidades_eval: ${passed}/${checks.length} verificaciones aprobadas (score=${score.toFixed(2)}).`);
