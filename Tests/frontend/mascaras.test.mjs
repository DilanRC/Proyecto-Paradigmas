import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { REGLAS_SERVIDOR, errorIdentificacion } from '../../Public/js/shared/identificacion.js';
import { validatePersonaDraft } from '../../Public/js/shared/business-rules.js';

const php = readFileSync(new URL('../../Application/Service/ValidacionService.php', import.meta.url), 'utf8');
const registroJs = readFileSync(new URL('../../Public/js/registro.js', import.meta.url), 'utf8');

test('las reglas de identificación del navegador son las mismas de ValidacionService.php', () => {
    const delServidor = {};
    for (const [, tipo, patron, mensaje] of php.matchAll(/'([A-Z_]+)' => \['patron' => '\/(.+?)\/', 'mensaje' => '(.+?)'\]/g)) {
        delServidor[tipo] = { patron, mensaje };
    }
    assert.deepEqual(Object.keys(REGLAS_SERVIDOR).sort(), Object.keys(delServidor).sort());
    for (const [tipo, regla] of Object.entries(REGLAS_SERVIDOR)) {
        assert.equal(regla.patron.source, delServidor[tipo].patron, `patrón de ${tipo}`);
        assert.equal(regla.mensaje, delServidor[tipo].mensaje, `mensaje de ${tipo}`);
    }
});

test('errorIdentificacion normaliza y juzga igual que el servidor', () => {
    const casos = [
        ['CEDULA_FISICA', '1-1111-1111', ''],
        ['CEDULA_FISICA', '1 1111 1111', ''],
        ['CEDULA_FISICA', '0-1234-5678', 'La cédula física debe tener 9 dígitos y no iniciar con cero.'],
        ['CEDULA_FISICA', '1234567890', 'La cédula física debe tener 9 dígitos y no iniciar con cero.'],
        ['CEDULA_JURIDICA', '3-101-111111', ''],
        ['CEDULA_JURIDICA', '3-101-11111', 'La cédula jurídica debe tener 10 dígitos.'],
        ['DIMEX', '11111111111', ''],
        ['DIMEX', '011111111111', 'El DIMEX debe tener 11 o 12 dígitos y no iniciar con cero.'],
        ['NITE', '0123456789', ''],
        ['PASAPORTE', 'ab1234567', ''],
        ['PASAPORTE', 'AB12345678', 'El pasaporte debe tener hasta 9 caracteres alfanuméricos.'],
        // Sin tipo o sin número no se juzga: eso lo reporta "obligatorio".
        ['', '123', ''],
        ['CEDULA_FISICA', '   ', ''],
    ];
    for (const [tipo, numero, esperado] of casos) {
        assert.equal(errorIdentificacion(tipo, numero), esperado, `${tipo} ${numero}`);
    }
});

test('el registro avisa antes de enviar lo que el servidor rechazaría', () => {
    const base = {
        identificacionTipo: 'CEDULA_FISICA', identificacionNumero: '1-1111-1111', nombres: 'Ana', apellidos: 'Mora',
        telefono: '+506 8888 8888', correoElectronico: 'ana@example.test',
    };
    assert.deepEqual(validatePersonaDraft(base, { requirePassword: false }), {});
    assert.equal(validatePersonaDraft({ ...base, identificacionNumero: '0-1234-5678' }, { requirePassword: false }).identificacionNumero,
        'La cédula física debe tener 9 dígitos y no iniciar con cero.');
    // Antes solo se contaban dígitos: con letras pasaba y el servidor lo rechazaba tras crear la cuenta.
    assert.ok(validatePersonaDraft({ ...base, telefono: '8888-8888 ext' }, { requirePassword: false }).telefono);
    assert.equal(validatePersonaDraft({ ...base, telefono: ' (88) 88-77 77 ' }, { requirePassword: false }).telefono, undefined);
});

test('el formulario de registro usa las mismas restricciones de teléfono que los paneles admin', () => {
    assert.match(registroJs, /telefono\.pattern = PATRON_TELEFONO/);
    assert.match(registroJs, /aplicarRestriccionTelefono\(telefono\)/);
    // Un 422 del servidor muestra su mensaje, no "No se pudo verificar".
    assert.match(registroJs, /error\?\.status === 422 \? error\.errors\?\.identificacionNumero/);
});
