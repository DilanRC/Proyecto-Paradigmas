// Publicaciones destacadas de la portada.
//
// Usa la tarjeta de Explorar en su variante compacta (foto, nombre, precio y
// "Ver más información"), sin botones de acción. Con más de 4 publicaciones se
// monta el carrusel automático; con 4 o menos quedan fijas.
//
// Cuatro estados que no se confunden: cargando (esqueleto de tarjetas), con
// publicaciones, catálogo realmente vacío (bienvenida) y fallo de la consulta
// (aviso con Reintentar). Un fallo de red no es "no hay ganado".

import { request } from './shared/api.js?v=auth-gate-16';
import { readAuthSession } from './shared/supabase-auth.js?v=session-4';
import { leerUbicacionUsuario } from './shared/ubicacion-sesion.js';
import { montarCarrusel } from './shared/carousel.js';
import { buildCard } from './explore.js?v=explore-16';

const MAXIMO = 12;
const FIJAS_HASTA = 4;

/** Qué parte de la sección se ve en cada fase: 'cargando' | 'listo' | 'vacio' | 'error'. */
export function vistaDestacadas(fase) {
    return {
        cargando: fase === 'cargando',
        carrusel: fase === 'listo',
        verTodas: fase === 'listo',
        vacio: fase === 'vacio',
        error: fase === 'error',
    };
}

let carruselMontado = false;

async function cargarDestacadas() {
    const seccion = document.querySelector('[data-featured]');
    if (!seccion) return;
    const partes = {
        cargando: seccion.querySelector('[data-featured-loading]'),
        carrusel: seccion.querySelector('[data-featured-carousel]'),
        verTodas: seccion.querySelector('[data-featured-more]'),
        vacio: seccion.querySelector('[data-featured-empty]'),
        error: seccion.querySelector('[data-featured-error]'),
    };
    const track = seccion.querySelector('[data-carousel-track]');
    const mostrar = (fase) => {
        const vista = vistaDestacadas(fase);
        for (const [parte, nodo] of Object.entries(partes)) if (nodo) nodo.hidden = !vista[parte];
        seccion.setAttribute('aria-busy', String(vista.cargando));
    };

    mostrar('cargando');
    let publicaciones;
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
    } catch (error) {
        const mensaje = seccion.querySelector('[data-featured-error-message]');
        if (mensaje) mensaje.textContent = error?.message || 'Revisa tu conexión e inténtalo de nuevo.';
        mostrar('error');
        return;
    }

    if (publicaciones.length === 0) {
        // Sin sesión la invitación es crear cuenta; con sesión, publicar.
        const accion = partes.vacio?.querySelector('[data-featured-empty-action]');
        if (readAuthSession() && accion) {
            accion.href = 'publicar';
            accion.querySelector('span').textContent = 'Publicar ganado';
        }
        mostrar('vacio');
        return;
    }

    track.replaceChildren(...publicaciones.map((p) => buildCard(p, { compacta: true })));
    const fijas = publicaciones.length <= FIJAS_HASTA;
    partes.carrusel.dataset.static = String(fijas);
    mostrar('listo');
    if (!fijas && !carruselMontado) {
        carruselMontado = true;
        montarCarrusel(partes.carrusel, { intervalo: 4000 });
    }
}

function iniciar() {
    document.querySelector('[data-featured-retry]')?.addEventListener('click', () => {
        void cargarDestacadas();
    });
    void cargarDestacadas();
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar, { once: true });
    } else {
        iniciar();
    }
}
