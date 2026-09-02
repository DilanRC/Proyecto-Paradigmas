import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const read = (path) => readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');

const home = read('Application/View/home/index.php');
const explore = read('Application/View/explorar/index.php');
const login = read('Application/View/login/index.php');
const productCss = read('Public/css/public-product.css');
const adminJs = read('Public/js/shared/admin-ui.js');
const collapseCss = read('Public/css/admin-sidebar-collapse.css');

const checks = [
    ['landing_producto', home.includes('El ganado que buscas, más cerca de ti.') && home.includes('explorar.php')],
    ['sin_lenguaje_academico', !/EIF400|acad[eé]mic/i.test(home + explore + login)],
    ['explorar_independiente', explore.includes('data-explore-deck') && explore.includes('data-explore-action="Pujar"')],
    ['tarjeta_explorar_con_datos', ['explore-card__price', 'explore-card__specs', 'explore-card__seller'].every((clase) => explore.includes(clase))],
    ['login_navegable', home.includes('href="login.php"') && login.includes('id="formulario-login"')],
    ['logos_oficiales', home.includes('assets/logo_dark.png') && explore.includes('assets/logo_light.png')],
    ['busqueda_expandible', home.includes('data-public-search-toggle') && productCss.includes("data-open='true'" )],
    ['sidebar_colapsable', collapseCss.includes('--admin-sidebar-collapsed-width:82px')],
    ['cuenta_dropdown', adminJs.includes("admin-account-menu__trigger") && adminJs.includes("admin-account-menu__panel")],
    ['paginacion_al_pie', adminJs.includes("tableContainer.insertAdjacentElement('afterend', footer)")],
];

const failed = checks.filter(([, ok]) => !ok).map(([name]) => name);
assert.deepEqual(failed, [], `Eval shell TinderCows falló: ${failed.join(', ')}`);

console.log(`OK official_app_shell.eval: ${checks.length}/${checks.length} criterios de producto, exploración y administración.`);
