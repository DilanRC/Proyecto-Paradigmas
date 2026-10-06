// Campo de foto de los diálogos de Mi panel (publicación y vehículo): elegir un
// archivo, ver la vista previa y quitarla. La subida usa el mismo bucket que Publicar.
import { subirImagenPublicacion, validarImagen } from './storage.js';

/**
 * `raiz` trae `input[type=file]`, `[data-foto-preview]` (con un `img`),
 * `[data-foto-quitar]` y `[data-foto-error]`.
 * `resolver()` devuelve `undefined` si no hubo cambio, `null` si se quitó la
 * foto, o la URL https de la foto nueva ya subida.
 */
export function montarCampoFoto(raiz) {
    const input = raiz.querySelector('input[type="file"]');
    const vista = raiz.querySelector('[data-foto-preview]');
    const img = vista.querySelector('img');
    const error = raiz.querySelector('[data-foto-error]');
    let archivo = null;
    let quitar = false;
    let blob = null;

    const mostrar = (src) => {
        if (blob) URL.revokeObjectURL(blob);
        blob = src?.startsWith('blob:') ? src : null;
        vista.hidden = !src;
        if (src) img.src = src;
        else img.removeAttribute('src');
    };
    const mostrarError = (mensaje = '') => { error.textContent = mensaje; };

    input.addEventListener('change', () => {
        const nuevo = input.files?.[0];
        if (!nuevo) return;
        const problema = validarImagen(nuevo);
        input.value = '';
        if (problema) { mostrarError(problema); return; }
        mostrarError();
        archivo = nuevo;
        quitar = false;
        mostrar(URL.createObjectURL(nuevo));
    });
    raiz.querySelector('[data-foto-quitar]').addEventListener('click', () => {
        archivo = null;
        quitar = true;
        mostrar(null);
        mostrarError();
    });

    return {
        /** Deja el campo como al abrir el diálogo: con la foto guardada (https) o vacío. */
        reiniciar(urlActual = null) {
            archivo = null;
            quitar = false;
            input.value = '';
            mostrarError();
            mostrar(urlActual);
        },
        mostrarError,
        async resolver() {
            if (archivo) return subirImagenPublicacion(archivo);
            return quitar ? null : undefined;
        },
    };
}
