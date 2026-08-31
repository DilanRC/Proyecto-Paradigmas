const fs = require('node:fs');
const assert = require('node:assert');

const retiredLink = 'compradores.php';
const activePanels = ['productores', 'transportistas', 'vehiculos', 'pagometodos'];
const checks = [
    ...activePanels.map((panel) => ({
        name: `menu_${panel}`,
        pass: !fs.readFileSync(`Application/View/${panel}/index.php`, 'utf8').includes(retiredLink),
    })),
    { name: 'vista_retirada', pass: !fs.existsSync('Application/View/compradores') },
    { name: 'ruta_retirada', pass: !fs.existsSync('Public/compradores.php') },
    { name: 'javascript_retirado', pass: !fs.existsSync('Public/js/compradores.js') },
];

const passed = checks.filter((check) => check.pass).length;
const score = passed / checks.length;
assert.strictEqual(score, 1, `Retiro frontend incompleto: ${passed}/${checks.length} verificaciones aprobadas`);

console.log(`OK frontend_retirement_eval: ${passed}/${checks.length} verificaciones aprobadas (score=${score.toFixed(2)}).`);
