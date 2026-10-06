import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    candidatosNumero, elegirNumero, esCelular, mensajeLectura, sePuedeLeer,
} from '../../Public/js/shared/escaner-documento.js';

const read = (ruta) => readFileSync(ruta, 'utf8');

test('encuentra el número de cédula entre el resto del texto leído', () => {
    // Así lee el OCR el frente de una cédula: el número con espacios, fechas y otros números.
    const texto = '1 1234 5678\n25 03 1998\n12 04 2031\n7 0311 0553 21';
    const candidatos = candidatosNumero(texto, 'CEDULA_FISICA');
    assert.ok(candidatos.includes('112345678'), 'número con espacios');
    assert.ok(candidatos.includes('703110553'), 'número pegado a otros dígitos');
    assert.ok(candidatos.every((n) => /^[1-9]\d{8}$/.test(n)), 'solo números con formato válido');
    assert.deepEqual(candidatosNumero('1-1111-1111', 'CEDULA_FISICA'), ['111111111'], 'con guiones');
    assert.deepEqual(candidatosNumero('0 1234 5678', 'CEDULA_FISICA'), [], 'una cédula física no empieza en cero');
    assert.deepEqual(candidatosNumero('3-101-111111', 'CEDULA_JURIDICA'), ['3101111111']);
    assert.deepEqual(candidatosNumero('ABC', 'CEDULA_FISICA'), []);
    assert.deepEqual(candidatosNumero('111111111', 'PASAPORTE'), [], 'el pasaporte no se lee como dígitos');
});

test('prefiere el número registrado si apareció; si no, el primero', () => {
    assert.equal(elegirNumero(['112345678', '703110553'], '7-0311-0553'), '703110553');
    assert.equal(elegirNumero(['112345678'], '703110553'), '112345678', 'el servidor dirá que no coincide');
    assert.equal(elegirNumero([], '703110553'), null, 'se intentó y no se encontró');
});

test('solo se leen fotos de identificaciones numéricas', () => {
    assert.equal(sePuedeLeer({ type: 'image/jpeg' }, 'CEDULA_FISICA'), true);
    assert.equal(sePuedeLeer({ type: 'application/pdf' }, 'CEDULA_FISICA'), false, 'un PDF se sube sin lectura');
    assert.equal(sePuedeLeer({ type: 'image/png' }, 'PASAPORTE'), false);
    assert.equal(sePuedeLeer(null, 'CEDULA_FISICA'), false);
});

test('"Tomar foto" solo en celulares (pantalla táctil sin puntero fino)', () => {
    const entorno = (coarse, toques) => ({ matchMedia: () => ({ matches: coarse }), navigator: { maxTouchPoints: toques } });
    assert.equal(esCelular(entorno(true, 5)), true);
    assert.equal(esCelular(entorno(false, 0)), false, 'computadora');
    assert.equal(esCelular(entorno(false, 10)), false, 'laptop táctil con mouse');
    assert.equal(esCelular({}), false);
});

test('el mensaje para la persona sale del resultado que calcula el servidor', () => {
    assert.match(mensajeLectura('COINCIDE'), /coincide con tu registro/);
    assert.match(mensajeLectura('SIN_LECTURA'), /más luz/);
    assert.equal(mensajeLectura(null), '');
});

test('Ajustes y el registro ofrecen archivo y cámara; la cámara abre la del teléfono', () => {
    const ajustes = read('Application/View/ajustes/index.php');
    assert.match(ajustes, /id="profile-document-photo" type="file" accept="image\/\*" capture="environment" hidden/);
    assert.match(ajustes, /id="profile-document-camera"[^>]*hidden/, 'oculto hasta comprobar que es un celular');
    const registro = read('Application/View/registro/index.php');
    assert.match(registro, /id="registro-documento-foto" type="file" accept="image\/\*" capture="environment" hidden/);
    assert.match(registro, /Documento de identidad <em>\(opcional\)<\/em>/);
    const registroJs = read('Public/js/registro.js');
    // Se envía después de crear la cuenta y nunca impide crearla.
    assert.ok(registroJs.indexOf("await request('api/v1/registro'") < registroJs.indexOf('prepararEnvioDocumento(documentoPendiente'));
    assert.match(registroJs, /if \(!extending && !documentoEnviado\) marcarAvisoDocumento\(\);/);
});

test('el navegador manda solo el número leído, nunca el resultado', () => {
    const escaner = read('Public/js/shared/escaner-documento.js');
    assert.match(escaner, /cuerpo\.documentoLectura = \{ numero \};/);
    assert.doesNotMatch(escaner, /COINCIDE['"]?\s*[,}]\s*$/m);
    assert.match(escaner, /cdn\.jsdelivr\.net\/npm\/tesseract\.js@5/);
});

test('el admin ve la lectura como ayuda junto al estado', async () => {
    const { textoLectura } = await import('../../Public/js/documentos.js');
    assert.equal(textoLectura({ lectura: 'COINCIDE', numeroLeido: '703110553' }), '✅ El número coincide (leído: 703110553)');
    assert.match(textoLectura({ lectura: 'OTRA_CUENTA', numeroLeido: '1' }), /otra cuenta/);
    assert.equal(textoLectura({}), '— Sin lectura automática');
});
