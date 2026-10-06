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

/**
 * UUID v4 para el nombre del archivo. crypto.randomUUID() solo existe en páginas
 * seguras (HTTPS o localhost): al entrar desde el celular por http://IP-local, o con
 * un navegador viejo, no está. getRandomValues sí funciona en cualquier página.
 */
export function nuevoUuid(cripto = globalThis.crypto) {
    if (typeof cripto?.randomUUID === 'function') return cripto.randomUUID();
    const b = cripto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40; // versión 4
    b[8] = (b[8] & 0x3f) | 0x80; // variante RFC 4122
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

export function extensionDe(tipo) {
    return { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp', 'application/pdf': 'pdf' }[tipo] ?? 'jpg';
}

/** Sube la imagen y devuelve su URL pública https. */
export async function subirImagenPublicacion(archivo, { fetchImpl = globalThis.fetch } = {}) {
    const problema = validarImagen(archivo);
    if (problema) throw new Error(problema);
    const { url, ruta } = await subirArchivo(BUCKET_PUBLICACIONES, archivo, fetchImpl,
        'No pudimos subir la imagen. Puedes usar una URL o publicar sin foto.');
    return `${url}/storage/v1/object/public/${BUCKET_PUBLICACIONES}/${ruta}`;
}

// Documento de identidad (P2-5): bucket PRIVADO. Solo su dueño sube y solo el
// administrador lo abre, con un enlace firmado temporal (P2-6).
export const BUCKET_DOCUMENTOS = 'documentos';
export const TIPOS_DOCUMENTO = Object.freeze([...TIPOS_IMAGEN, 'application/pdf']);

/** Problema del documento elegido, o null si se puede subir. */
export function validarDocumento(archivo) {
    if (!archivo) return 'Elige la foto o el PDF de tu documento.';
    if (!TIPOS_DOCUMENTO.includes(archivo.type)) return 'Usa una imagen JPG, PNG o WebP, o un PDF.';
    if (archivo.size > MAXIMO_BYTES) return 'El archivo no puede superar 5 MB.';
    return null;
}

/** Sube el documento y devuelve su ruta dentro del bucket privado (no hay URL pública). */
export async function subirDocumentoIdentidad(archivo, { fetchImpl = globalThis.fetch } = {}) {
    const problema = validarDocumento(archivo);
    if (problema) throw new Error(problema);
    const { ruta } = await subirArchivo(BUCKET_DOCUMENTOS, archivo, fetchImpl,
        'No pudimos subir el documento. Intenta de nuevo más tarde.');
    return ruta;
}

/** Sube el archivo a la carpeta del usuario dentro del bucket. Devuelve la URL del proyecto y la ruta. */
async function subirArchivo(bucket, archivo, fetchImpl, mensajeError) {
    const token = await getAccessToken();
    const usuario = token ? usuarioDelToken(token) : null;
    if (!usuario) throw new Error('La sesión expiró. Entra de nuevo para subir el archivo.');

    const { url, publishableKey } = (await request('api/v1/auth/config')).data ?? {};
    const ruta = `${usuario}/${nuevoUuid()}.${extensionDe(archivo.type)}`;
    const respuesta = await fetchImpl(`${url}/storage/v1/object/${bucket}/${ruta}`, {
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
        throw new Error(mensajeError);
    }
    return { url, ruta };
}
