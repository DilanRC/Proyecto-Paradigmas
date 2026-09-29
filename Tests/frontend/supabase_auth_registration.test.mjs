import assert from 'node:assert/strict';
import test from 'node:test';

import { signUpWithPassword } from '../../Public/js/shared/supabase-auth.js';

test('el alta traduce respuestas variantes de correo ya registrado', async () => {
    const originalFetch = globalThis.fetch;
    const requested = [];
    const signupErrors = [
        { msg: 'User already registered' },
        { code: 'user_already_exists', message: 'User exists' },
    ];
    globalThis.fetch = async (url) => {
        requested.push(String(url));
        if (String(url).includes('api/v1/auth/config')) {
            return new Response(JSON.stringify({
                success: true,
                data: { url: 'https://auth.example.test', publishableKey: 'sb_publishable_test' },
            }), { status: 200, headers: { 'Content-Type': 'application/json' } });
        }
        return new Response(JSON.stringify(signupErrors.shift()), {
            status: 422,
            headers: { 'Content-Type': 'application/json' },
        });
    };
    try {
        for (let intento = 0; intento < 2; intento += 1) {
            await assert.rejects(
                signUpWithPassword('docente@example.test', 'UnaClaveFuerte9', null),
                { name: 'AuthError', message: 'No fue posible crear la cuenta. Revise los datos e intente nuevamente.' },
            );
        }
        assert.equal(requested.length, 3);
        assert.ok(requested[1].includes('/auth/v1/signup'));
        assert.ok(requested[2].includes('/auth/v1/signup'));
    } finally {
        globalThis.fetch = originalFetch;
    }
});
