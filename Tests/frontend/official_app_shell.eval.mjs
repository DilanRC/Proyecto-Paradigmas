import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const home = read('Application/View/home/index.php');
const login = read('Application/View/login/index.php');
const panelCss = read('Public/css/panel.css');
const landingCss = read('Public/css/red-ganadera.css');

const checks = [
    ['landing_oficial', home.includes('Gestión centralizada de la red') && home.includes('Cómo usar TinderCows')],
    ['login_navegable', home.includes('href="login.php"') && login.includes('id="formulario-login"')],
    ['logos_oficiales', home.includes('assets/logo_dark.png') && login.includes('assets/logo_light.png')],
    ['sin_icono_viejo_en_home', !home.includes('<svg viewBox="0 0 48 48">')],
    ['tablas_sin_scroll_horizontal', panelCss.includes('overflow-x:visible') && panelCss.includes('table-layout:fixed')],
    ['acciones_no_ensanchan_tabla', panelCss.includes('flex-wrap:wrap') && panelCss.includes('overflow-wrap:anywhere')],
    ['layout_responsive', landingCss.includes('@media (max-width:980px)') && panelCss.includes('@media (max-width:900px)')],
];

const failed = checks.filter(([, ok]) => !ok).map(([name]) => name);
assert.deepEqual(failed, [], `Eval oficial app shell falló: ${failed.join(', ')}`);

console.log(`OK official_app_shell.eval: ${checks.length}/${checks.length} criterios de app oficial, login, logo y tablas.`);
