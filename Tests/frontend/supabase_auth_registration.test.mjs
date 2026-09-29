import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import { signUpWithPassword, SIGNUP_NEXT_STEPS_MESSAGE } from '../../Public/js/shared/supabase-auth.js';

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
                {
                    name: 'AuthError',
                    code: 'account_already_exists',
                    message: SIGNUP_NEXT_STEPS_MESSAGE,
                },
            );
        }
        assert.equal(requested.length, 3);
        assert.ok(requested[1].includes('/auth/v1/signup'));
        assert.ok(requested[2].includes('/auth/v1/signup'));
    } finally {
        globalThis.fetch = originalFetch;
    }
});

test('el registro no expone un verificador público de correos existentes', () => {
    const registration = readFileSync(new URL('../../Public/js/registro.js', import.meta.url), 'utf8');
    const view = readFileSync(new URL('../../Application/View/registro/index.php', import.meta.url), 'utf8');
    const rewriteRules = readFileSync(new URL('../../Public/.htaccess', import.meta.url), 'utf8');

    assert.doesNotMatch(view, /data-correo-status|data-correo-retry/);
    assert.doesNotMatch(registration, /api\/v1\/registro\/correo|checkEmail|scheduleEmailCheck/);
    assert.doesNotMatch(rewriteRules, /api\/v1\/registro\/correo/);
    assert.doesNotMatch(registration, /emailState/);
    assert.match(registration, /if \(error\?\.code === 'account_already_exists'\)[\s\S]*SIGNUP_NEXT_STEPS_MESSAGE/);
    assert.match(registration, /if \(!auth\.session\)[\s\S]*SIGNUP_NEXT_STEPS_MESSAGE/);
});
