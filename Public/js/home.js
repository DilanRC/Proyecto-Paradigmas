// Publicaciones destacadas de la portada.
//
// Usa la tarjeta de Explorar en su variante compacta (foto, nombre, precio y
// "Ver más información"), sin botones de acción. Con más de 4 publicaciones se
// monta el carrusel automático; con 4 o menos quedan fijas. Sin publicaciones,
// o si la API falla, la sección da la bienvenida en vez de decir "no hay".

import { request } from './shared/api.js';
import { readAuthSession } from './shared/supabase-auth.js';
import { leerUbicacionUsuario } from './shared/ubicacion-sesion.js';
import { montarCarrusel } from './shared/carousel.js';
import { buildCard } from './explore.js?v=foto-4';

const MAXIMO = 12;
const FIJAS_HASTA = 4;

async function cargarDestacadas() {
    const seccion = document.querySelector('[data-featured]');
    if (!seccion) return;
    const carrusel = seccion.querySelector('[data-featured-carousel]');
    const track = seccion.querySelector('[data-carousel-track]');
    const vacio = seccion.querySelector('[data-featured-empty]');
    const cargando = seccion.querySelector('[data-featured-loading]');
    const verTodas = seccion.querySelector('[data-featured-more]');
    const conSesion = Boolean(readAuthSession());

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
        // Un fallo de red no es "no hay ganado": se muestra la bienvenida.
    }

    cargando.hidden = true;
    const hay = publicaciones.length > 0;
    carrusel.hidden = !hay;
    verTodas.hidden = !hay;
    vacio.hidden = hay;
    if (!hay) {
        // Sin sesión la invitación es crear cuenta; con sesión, publicar.
        const accion = vacio.querySelector('[data-featured-empty-action]');
        if (conSesion && accion) {
            accion.href = 'publicar';
            accion.querySelector('span').textContent = 'Publicar ganado';
        }
        return;
    }

    track.replaceChildren(...publicaciones.map((p) => buildCard(p, { compacta: true })));
    const fijas = publicaciones.length <= FIJAS_HASTA;
    carrusel.dataset.static = String(fijas);
    if (!fijas) montarCarrusel(carrusel, { intervalo: 4000 });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', cargarDestacadas, { once: true });
    } else {
        cargarDestacadas();
    }
}
