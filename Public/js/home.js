// Publicaciones destacadas de la portada.
//
// Pide las más recientes (o cercanas, si ya conocemos la ubicación) y pinta de
// 3 a 6 tarjetas. Sin publicaciones, o si la API falla, la sección invita a
// publicar: la portada nunca dice "no encontramos" a quien no buscó nada.

import { request } from './shared/api.js';
import { leerUbicacionUsuario } from './shared/ubicacion-sesion.js';
import { formatLocation, formatPrice, formatPurpose, formatText } from './explore.js';

const MAXIMO = 6;

function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

function icon(clase) {
    const node = element('i', `fa-solid ${clase}`);
    node.setAttribute('aria-hidden', 'true');
    return node;
}

/** Tipo/raza en una línea: "Brahman · Engorde". */
export function featuredType(animal) {
    const partes = [animal?.raza, animal?.proposito ? formatPurpose(animal.proposito) : null]
        .map((v) => String(v ?? '').trim())
        .filter((v) => v !== '' && v !== '—');
    return partes.length ? partes.join(' · ') : 'Ganado';
}

/** textContent en todo: los textos vienen de la base. */
export function buildFeaturedCard(publicacion) {
    const article = element('article', 'featured-card');
    const visual = element('div', 'featured-card__visual');
    visual.append(icon('fa-cow'));

    const body = element('div', 'featured-card__body');
    const ubicacion = element('p', 'featured-card__location');
    ubicacion.append(icon('fa-location-dot'), ` ${formatLocation(publicacion?.direccion)}`);

    const ver = element('a', 'public-secondary featured-card__action');
    const id = Number(publicacion?.publicacionId);
    ver.href = Number.isInteger(id) && id > 0 ? `explorar?publicacion=${id}` : 'explorar';
    ver.append(element('span', null, 'Ver'), icon('fa-arrow-right'));
    ver.setAttribute('aria-label', `Ver ${formatText(publicacion?.titulo)}`);

    body.append(
        element('span', 'featured-card__type', featuredType(publicacion?.animal)),
        element('h3', null, formatText(publicacion?.titulo)),
        ubicacion,
        element('strong', 'featured-card__price', formatPrice(publicacion?.precio)),
        ver,
    );
    article.append(visual, body);
    return article;
}

async function cargarDestacadas() {
    const seccion = document.querySelector('[data-featured]');
    if (!seccion) return;
    const grid = seccion.querySelector('[data-featured-grid]');
    const vacio = seccion.querySelector('[data-featured-empty]');
    const cargando = seccion.querySelector('[data-featured-loading]');
    const verTodas = seccion.querySelector('[data-featured-more]');

    let publicaciones = [];
    try {
        const consulta = { estado: 'ACTIVO', pagina: '1', tamanoPagina: String(MAXIMO) };
        const ubicacion = leerUbicacionUsuario();
        if (ubicacion) {
            consulta.latitud = ubicacion.latitud;
            consulta.longitud = ubicacion.longitud;
        }
        const respuesta = await request('api/v1/publicaciones', {
            method: 'POST',
            body: JSON.stringify({ consulta }),
        });
        publicaciones = Array.isArray(respuesta.data?.publicaciones) ? respuesta.data.publicaciones : [];
    } catch {
        // Un fallo de red no es "no hay ganado": se ofrece publicar igual.
    }

    cargando.hidden = true;
    const hay = publicaciones.length > 0;
    grid.replaceChildren(...publicaciones.slice(0, MAXIMO).map(buildFeaturedCard));
    grid.hidden = !hay;
    verTodas.hidden = !hay;
    vacio.hidden = hay;
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', cargarDestacadas, { once: true });
    } else {
        cargarDestacadas();
    }
}
