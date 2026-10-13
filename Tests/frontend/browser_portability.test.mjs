import assert from 'node:assert/strict';
import test from 'node:test';
import { safeNext } from '../../Public/js/shared/next.js';
import { normalizeCapabilities } from '../../Public/js/shared/business-rules.js';
import { routeName } from '../../Public/js/shared/auth-gate.js';

test('rutas y permisos funcionan sin Array.at ni Object.hasOwn', () => {
    const at = Array.prototype.at;
    const hasOwn = Object.hasOwn;
    try {
        Array.prototype.at = undefined;
        Object.hasOwn = undefined;
        assert.equal(routeName('/Proyecto-Paradigmas/mi-actividad'), 'mi-actividad');
        assert.equal(safeNext('?next=' + encodeURIComponent('explorar?q=vacas&constructor=bad')), 'explorar?q=vacas');
        assert.deepEqual(normalizeCapabilities(['productor', 'constructor', 'desconocido']), ['PRODUCTOR']);
    } finally {
        Array.prototype.at = at;
        Object.hasOwn = hasOwn;
    }
});
