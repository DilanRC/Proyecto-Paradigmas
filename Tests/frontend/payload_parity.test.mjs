// Paridad del contrato con el backend tras el refactor.
//
// El riesgo real de repartir el codigo en modulos no es visual: es que el
// formulario siga viendose bien y empiece a enviar un cuerpo distinto. Estas
// pruebas fijan el cuerpo exacto que cada panel mandaba antes del refactor,
// comparado contra un payload de referencia escrito a mano.

import assert from 'node:assert/strict';
import test from 'node:test';

import { buildVehiculoPayload } from '../../Public/js/vehiculos.js';

test('vehiculo nuevo: exactamente placa, vin y modelo', () => {
    const payload = buildVehiculoPayload({ placa: 'ABC-123', vin: 'VIN0001', modelo: 'Hilux 2024' });

    assert.deepEqual(payload, { placa: 'ABC-123', vin: 'VIN0001', modelo: 'Hilux 2024' });
    assert.equal('vehiculoId' in payload, false, 'el alta no envia vehiculoId');
});

test('vehiculo editado: agrega vehiculoId numerico', () => {
    const payload = buildVehiculoPayload({
        placa: 'ABC-123', vin: 'VIN0001', modelo: 'Hilux 2025', vehiculoId: '7',
    });

    assert.deepEqual(payload, {
        placa: 'ABC-123', vin: 'VIN0001', modelo: 'Hilux 2025', vehiculoId: 7,
    });
    assert.equal(typeof payload.vehiculoId, 'number', 'el backend valida entero, no texto');
});

test('los espacios sobrantes se recortan igual que antes', () => {
    const payload = buildVehiculoPayload({ placa: '  ABC-123 ', vin: ' VIN1 ', modelo: ' Hilux  ' });

    assert.deepEqual(payload, { placa: 'ABC-123', vin: 'VIN1', modelo: 'Hilux' });
});

test('el campo vacio no se convierte en vehiculoId 0', () => {
    // Number('') es 0, que el backend aceptaria como entero invalido en vez de
    // tratarlo como alta. El alta debe omitir la clave por completo.
    const payload = buildVehiculoPayload({ placa: 'A', vin: 'B', modelo: 'C', vehiculoId: '' });

    assert.equal('vehiculoId' in payload, false);
});

test('el panel conserva su endpoint', async () => {
    const { readFile } = await import('node:fs/promises');
    const source = await readFile(new URL('../../Public/js/vehiculos.js', import.meta.url), 'utf8');

    assert.match(source, /const API_URL = 'api\/vehiculos\.php';/);
});

// --- metodos de pago ---------------------------------------------------------
import { buildPagoMetodoPayload } from '../../Public/js/pagometodos.js';

test('metodo de pago nuevo: nombre, descripcion y activo', () => {
    const payload = buildPagoMetodoPayload({ nombre: 'Efectivo', descripcion: 'Pago en efectivo' });

    assert.deepEqual(payload, { nombre: 'Efectivo', descripcion: 'Pago en efectivo', activo: true });
    assert.equal('id' in payload, false);
});

test('metodo de pago editado: agrega id numerico y conserva activo', () => {
    const payload = buildPagoMetodoPayload({ nombre: 'Tarjeta', descripcion: 'Con tarjeta', id: '3' });

    assert.deepEqual(payload, { nombre: 'Tarjeta', descripcion: 'Con tarjeta', activo: true, id: 3 });
});

// --- transportistas ----------------------------------------------------------
import { buildTransportistaPayload } from '../../Public/js/transportistas.js';

const transportistaBase = {
    tipoCodigo: 'PASAPORTE', numero: ' AB1234 ', nombre: ' Juan Perez ',
    telefono: ' +506 8888-7777 ', correoElectronico: '  Juan@Example.TEST ',
};

test('transportista nuevo: identificacion anidada y correo en minuscula', () => {
    const payload = buildTransportistaPayload(transportistaBase);

    assert.deepEqual(payload, {
        identificacion: { tipoCodigo: 'PASAPORTE', numero: 'AB1234' },
        nombre: 'Juan Perez',
        telefono: '+506 8888-7777',
        correoElectronico: 'juan@example.test',
    });
    assert.equal('identificacionNumeroOriginal' in payload, false, 'el alta no lo envia');
});

test('transportista editado: agrega identificacionNumeroOriginal', () => {
    const payload = buildTransportistaPayload({
        ...transportistaBase, identificacionNumeroOriginal: 'AB1234',
    });

    assert.equal(payload.identificacionNumeroOriginal, 'AB1234');
});

// --- productores -------------------------------------------------------------
import { buildFincaDireccionPayload, buildProductorPayload } from '../../Public/js/productores.js';

const productorBase = {
    tipoCodigo: 'CEDULA_FISICA', numero: ' 1-1111-1111 ', nombre: ' Maria Solano ',
    telefono: ' 88881111 ', correoElectronico: ' Maria@Example.TEST ',
    provincia: ' Heredia ', canton: ' Heredia ', distrito: ' Mercedes ',
    pueblo: '', senas: '   ', fincas: '',
};

test('productor nuevo: direccion anidada y opcionales en null', () => {
    const payload = buildProductorPayload(productorBase);

    assert.deepEqual(payload, {
        identificacion: { tipoCodigo: 'CEDULA_FISICA', numero: '1-1111-1111' },
        nombre: 'Maria Solano',
        telefono: '88881111',
        correoElectronico: 'maria@example.test',
        direccionPrincipal: {
            provincia: 'Heredia', canton: 'Heredia', distrito: 'Mercedes',
            pueblo: null, senas: null,
        },
        fincas: [],
    });
});

test('REGRESION: los campos opcionales vacios viajan como null, no como cadena', () => {
    // El backend acepta null; una cadena vacia se guardaria como texto vacio.
    const payload = buildProductorPayload({ ...productorBase, pueblo: '  ', senas: '' });

    assert.equal(payload.direccionPrincipal.pueblo, null);
    assert.equal(payload.direccionPrincipal.senas, null);
});

test('las fincas se parten por linea, se recortan y se descartan las vacias', () => {
    const payload = buildProductorPayload({
        ...productorBase, fincas: ' Finca El Roble \n\n  Finca Valle Verde\n   \n',
    });

    assert.deepEqual(payload.fincas, [
        { nombre: 'Finca El Roble' },
        { nombre: 'Finca Valle Verde' },
    ]);
});

test('la direccion de finca conserva su envoltura direccionFinca', () => {
    const payload = buildFincaDireccionPayload({
        identificacionNumero: '111111111', nombreFinca: 'Finca El Roble',
        provincia: 'Alajuela', canton: 'San Carlos', distrito: 'Quesada',
        pueblo: 'Centro', senas: '',
    });

    assert.deepEqual(payload, {
        identificacionNumero: '111111111',
        nombreFinca: 'Finca El Roble',
        direccionFinca: {
            provincia: 'Alajuela', canton: 'San Carlos', distrito: 'Quesada',
            pueblo: 'Centro', senas: null,
        },
    });
});

test('cada panel conserva su endpoint', async () => {
    const { readFile } = await import('node:fs/promises');
    const leer = (f) => readFile(new URL(`../../Public/js/${f}`, import.meta.url), 'utf8');

    assert.match(await leer('pagometodos.js'), /const API_URL = 'api\/pagometodos\.php';/);
    assert.match(await leer('transportistas.js'), /const API_URL = 'api\/transportistas\.php';/);
    assert.match(await leer('transportistas.js'), /const ASIGNACION_URL = 'api\/transportistas-vehiculos\.php';/);
    assert.match(await leer('productores.js'), /const API_URL = 'api\/productores\.php';/);
    assert.match(await leer('productores.js'), /const FINCAS_DIRECCION_URL = 'api\/fincas-direccion\.php';/);
});

// --- compradores -------------------------------------------------------------
// El panel de compradores ya no envia ningun cuerpo: el CRUD legacy se retiro
// en el paso (d) (DEC-DBREADY-008) y la vista quedo de solo lectura, porque
// Comprador es una clasificacion derivada del comportamiento del productor.
// La paridad que se prueba ahora es la contraria: que no haya vuelto a aparecer
// un constructor de payload ni una escritura desde ese panel.
import { formatearClasificadoDesde, describirOrigen } from '../../Public/js/compradores.js';

test('el panel de compradores no construye cuerpos de escritura', async () => {
    const { readFile } = await import('node:fs/promises');
    const fuente = await readFile(
        new URL('../../Public/js/compradores.js', import.meta.url), 'utf8');
    assert.equal(/buildCompradorPayload/.test(fuente), false, 'reaparecio el constructor de payload');
    for (const metodo of ['POST', 'PUT', 'DELETE', 'PATCH']) {
        assert.equal(fuente.includes(`'${metodo}'`), false, `el panel volvio a emitir ${metodo}`);
    }
});

test('la clasificacion se muestra con su fecha y el origen real del periodo', () => {
    assert.equal(formatearClasificadoDesde(''), 'Sin fecha registrada');
    assert.equal(formatearClasificadoDesde('no es fecha'), 'no es fecha');
    assert.match(formatearClasificadoDesde('2026-09-01 10:15:00'), /2026/);
    assert.equal(describirOrigen('MIGRACION_TBCOMPRADOR_LEGACY'), 'Migración del registro anterior');
    assert.equal(describirOrigen('ALTA_CRUD_COMPRADOR'), 'Alta registrada antes del retiro del CRUD');
    assert.equal(describirOrigen('REACTIVACION_CRUD_COMPRADOR'), 'Reactivación registrada antes del retiro del CRUD');
    assert.equal(describirOrigen(''), 'Sin origen declarado');
    assert.equal(describirOrigen('T10_REGLA_FUTURA'), 'T10_REGLA_FUTURA',
        'un motivo futuro no se inventa ni se oculta');
});
