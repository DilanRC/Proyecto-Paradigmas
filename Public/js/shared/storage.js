// Subida de imágenes a Supabase Storage desde el navegador.
//
// La foto va directo del navegador al bucket público "publicaciones" con la
// sesión de la persona (su JWT); PHP solo recibe y guarda la URL resultante.
// Cada persona escribe dentro de su propia carpeta (<id de usuario>/...), que
// es lo que exige la política del bucket.

import { request } from './api.js';
import { getAccessToken } from './supabase-auth.js';

export const BUCKET_PUBLICACIONES = 'publicaciones';
export const TIPOS_IMAGEN = Object.freeze(['image/jpeg', 'image/png', 'image/webp']);
export const MAXIMO_BYTES = 5 * 1024 * 1024;

/** Problema de la imagen elegida, o null si se puede subir. */
export function validarImagen(archivo) {
    if (!archivo) return 'Elige una imagen.';
    if (!TIPOS_IMAGEN.includes(archivo.type)) return 'Usa una imagen JPG, PNG o WebP.';
    if (archivo.size > MAXIMO_BYTES) return 'La imagen no puede superar 5 MB.';
    return null;
}

/** "sub" del JWT: el id del usuario en Supabase Auth. */
export function usuarioDelToken(token) {
    try {
        const carga = String(token).split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
        return JSON.parse(atob(carga.padEnd(carga.length + ((4 - (carga.length % 4)) % 4), '='))).sub ?? null;
    } catch {
        return null;
    }
}

export function extensionDe(tipo) {
    return { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' }[tipo] ?? 'jpg';
}

/** Sube la imagen y devuelve su URL pública https. */
export async function subirImagenPublicacion(archivo, { fetchImpl = globalThis.fetch } = {}) {
    const problema = validarImagen(archivo);
    if (problema) throw new Error(problema);
    const token = await getAccessToken();
    const usuario = token ? usuarioDelToken(token) : null;
    if (!usuario) throw new Error('La sesión expiró. Entra de nuevo para subir la imagen.');

    const { url, publishableKey } = (await request('api/v1/auth/config')).data ?? {};
    const ruta = `${usuario}/${crypto.randomUUID()}.${extensionDe(archivo.type)}`;
    const respuesta = await fetchImpl(`${url}/storage/v1/object/${BUCKET_PUBLICACIONES}/${ruta}`, {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${token}`,
            apikey: publishableKey,
            'Content-Type': archivo.type,
            'x-upsert': 'false',
        },
        body: archivo,
    });
    if (!respuesta.ok) {
        // Sin bucket o sin política, Supabase responde 400/403/404.
        throw new Error('No pudimos subir la imagen. Puedes usar una URL o publicar sin foto.');
    }
    return `${url}/storage/v1/object/public/${BUCKET_PUBLICACIONES}/${ruta}`;
}
