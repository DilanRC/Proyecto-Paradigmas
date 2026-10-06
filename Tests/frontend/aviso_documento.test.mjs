import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { AVISO_DOCUMENTO_KEY, marcarAvisoDocumento, tomarAvisoDocumento } from '../../Public/js/shared/aviso-documento.js';

const read = (ruta) => readFileSync(new URL(`../../${ruta}`, import.meta.url), 'utf8');

function memoria() {
    const datos = new Map();
    return {
        getItem: (k) => (datos.has(k) ? datos.get(k) : null),
        setItem: (k, v) => datos.set(k, String(v)),
        removeItem: (k) => datos.delete(k),
    };
}

test('el aviso del documento se muestra una sola vez por cuenta nueva', () => {
    const storage = memoria();
    assert.equal(tomarAvisoDocumento(storage), false, 'sin marca no hay aviso');
    marcarAvisoDocumento(storage);
    assert.equal(storage.getItem(AVISO_DOCUMENTO_KEY), '1');
    assert.equal(tomarAvisoDocumento(storage), true);
    assert.equal(tomarAvisoDocumento(storage), false, 'la marca se consume');
    // Sin almacenamiento (modo privado estricto) no rompe nada.
    const roto = { getItem() { throw new Error('x'); }, setItem() { throw new Error('x'); }, removeItem() {} };
    assert.doesNotThrow(() => marcarAvisoDocumento(roto));
    assert.equal(tomarAvisoDocumento(roto), false);
});

test('los dos caminos del alta marcan el aviso y Explorar lo muestra con enlace a Ajustes', () => {
    // Con sesión al registrarse (registro.js) y al confirmar el correo (login.js).
    // Sin documento enviado en el registro, Explorar invita a subirlo (con sesión al registrarse).
    assert.match(read('Public/js/registro.js'), /if \(!extending && !documentoEnviado\) marcarAvisoDocumento\(\);/);
    const login = read('Public/js/login.js');
    assert.ok(login.indexOf('marcarAvisoDocumento();') > login.indexOf('await completarRegistroPendiente(email)'));
    const vista = read('Application/View/explorar/index.php');
    assert.match(vista, /data-aviso-documento role="status"[^>]*hidden/);
    assert.match(vista, /<a href="ajustes#perfil">Subir documento<\/a>/);
    assert.match(vista, /data-aviso-cerrar aria-label="Cerrar aviso"/);
    assert.match(vista, /js\/shared\/aviso-documento\.js\?v=aviso-1/);
});
