import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const read = (path) => readFileSync(path, 'utf8');

test('la geolocalización requiere una acción explícita y conserva el filtro manual', () => {
    const pageModules = [
        'Public/js/explore.js',
        'Public/js/public-ui.js',
        'Public/js/registro.js',
        'Public/js/shared/auth-gate.js',
    ];
    for (const path of pageModules) {
        assert.doesNotMatch(
            read(path),
            /^\s*inicializarUbicacionAutomatica\(\);\s*$/m,
            `${path} no debe pedir ubicación al abrirse`,
        );
    }

    const explore = read('Public/js/explore.js');
    assert.match(
        explore,
        /geolocationButton\?\.addEventListener\('click',[\s\S]{0,500}inicializarUbicacionAutomatica\(\)/,
        'Explorar solo pide geolocalización al pulsar el control visible',
    );
    assert.match(read('Application/View/explorar/index.php'), /data-explore-use-location/);
    assert.match(read('Application/View/explorar/index.php'), /data-explore-ubicacion/);

    const fletes = read('Public/js/fletes.js');
    assert.match(fletes, /\$\('#cercanos-ubicacion'\)\?\.addEventListener\('click', usarMiUbicacion\)/);
    assert.match(fletes, /async function usarMiUbicacion\(\)[\s\S]{0,300}capturarUbicacionAutomatica\(\{ forzar: true \}\)/);
});
