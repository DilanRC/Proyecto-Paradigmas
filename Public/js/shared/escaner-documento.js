// Lectura automática del número del documento de identidad (OCR en el
// navegador, P2-5/P2-6). Es una AYUDA para el administrador: el navegador solo
// envía el número leído y el servidor calcula si coincide (MiPerfilController).
// La decisión final sigue siendo del administrador.
//
// Tesseract.js se descarga del CDN solo cuando alguien elige una foto, y nunca
// envía la imagen a ningún servidor: lee en el dispositivo. Si no carga o no
// encuentra el número, el documento se sube igual, sin lectura.

import { REGLAS_SERVIDOR, TIPOS_NUMERICOS } from './identificacion.js?v=mascaras-1';
import { subirDocumentoIdentidad } from './storage.js?v=documento-2';

const TESSERACT_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
const TIPOS_IMAGEN = ['image/jpeg', 'image/png', 'image/webp'];
// Solo identificaciones numéricas: el lector busca dígitos (un pasaporte tiene letras).
const TIPOS_LEGIBLES = [...TIPOS_NUMERICOS, 'NITE'];

/** ¿Tiene sentido leer este archivo para este tipo de identificación? Puro. */
export function sePuedeLeer(archivo, tipo) {
    return Boolean(archivo) && TIPOS_IMAGEN.includes(archivo.type) && TIPOS_LEGIBLES.includes(tipo);
}

/**
 * Números con el formato del tipo dentro del texto leído. Une los dígitos
 * separados por espacio o guion ("1 1111 1111") y, si quedaron pegados a otros,
 * prueba las ventanas del largo esperado. Puro.
 */
export function candidatosNumero(texto, tipo) {
    const regla = REGLAS_SERVIDOR[tipo];
    if (!regla || !TIPOS_LEGIBLES.includes(tipo)) return [];
    const largos = { CEDULA_FISICA: [9], CEDULA_JURIDICA: [10], DIMEX: [11, 12], NITE: [10] }[tipo] ?? [];
    const encontrados = new Set();
    for (const linea of String(texto ?? '').split('\n')) {
        for (const m of linea.matchAll(/\d[\d \-]*\d/g)) {
            const digitos = m[0].replace(/[ \-]/g, '');
            if (regla.patron.test(digitos)) encontrados.add(digitos);
            if (digitos.length > Math.max(0, ...largos)) {
                for (const largo of largos) {
                    for (let i = 0; i + largo <= digitos.length; i++) {
                        const trozo = digitos.slice(i, i + largo);
                        if (regla.patron.test(trozo)) encontrados.add(trozo);
                    }
                }
            }
        }
    }
    return [...encontrados];
}

/**
 * El número a enviar: el registrado si aparece entre los leídos; si no, el
 * primero que se leyó (el servidor dirá que no coincide); si no hay, null. Puro.
 */
export function elegirNumero(candidatos, registrado) {
    const propio = String(registrado ?? '').replace(/\D/g, '');
    if (propio && candidatos.includes(propio)) return propio;
    return candidatos[0] ?? null;
}

/** Texto para la persona según el resultado que calculó el servidor. Puro. */
export function mensajeLectura(lectura) {
    return {
        COINCIDE: 'Leímos tu número y coincide con tu registro.',
        NO_COINCIDE: 'El número que leímos no coincide con tu registro. Un administrador lo revisará igual.',
        OTRA_CUENTA: 'El número que leímos pertenece a otra cuenta. Un administrador lo revisará.',
        SIN_LECTURA: 'No pudimos leer el número; un administrador lo revisará igual. Una foto con más luz ayuda.',
    }[lectura] ?? '';
}

/**
 * "Tomar foto" solo en celulares y tabletas: su cámara es mucho mejor que la de
 * una computadora. Pantalla táctil sin puntero fino. Puro (entorno inyectable).
 */
export function esCelular(entorno = globalThis) {
    try {
        return Boolean(entorno.matchMedia?.('(pointer: coarse)').matches) && Number(entorno.navigator?.maxTouchPoints) > 0;
    } catch {
        return false;
    }
}

let tesseract = null;
function cargarTesseract() {
    if (globalThis.Tesseract) return Promise.resolve(globalThis.Tesseract);
    tesseract ??= new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = TESSERACT_URL;
        script.async = true;
        script.onload = () => (globalThis.Tesseract ? resolve(globalThis.Tesseract) : reject(new Error('Lector no disponible')));
        script.onerror = () => { tesseract = null; reject(new Error('No se pudo cargar el lector')); };
        document.head.appendChild(script);
    });
    return tesseract;
}

/** Escala de grises y contraste (o blanco y negro) y tamaño mínimo: el lector acierta más. */
function preparar(imagen, { recorte = null, umbral = false } = {}) {
    const [sx, sy, sw, sh] = recorte ?? [0, 0, imagen.width, imagen.height];
    const escala = Math.max(1, 1600 / sw);
    const lienzo = document.createElement('canvas');
    lienzo.width = Math.round(sw * escala);
    lienzo.height = Math.round(sh * escala);
    const ctx = lienzo.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(imagen, sx, sy, sw, sh, 0, 0, lienzo.width, lienzo.height);
    const datos = ctx.getImageData(0, 0, lienzo.width, lienzo.height);
    const d = datos.data;
    let suma = 0;
    for (let i = 0; i < d.length; i += 4) {
        const g = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
        d[i] = d[i + 1] = d[i + 2] = g;
        suma += g;
    }
    const media = suma / (d.length / 4);
    for (let i = 0; i < d.length; i += 4) {
        const g = umbral ? (d[i] > media * 0.9 ? 255 : 0) : Math.max(0, Math.min(255, (d[i] - media) * 1.6 + 128));
        d[i] = d[i + 1] = d[i + 2] = g;
    }
    ctx.putImageData(datos, 0, 0);
    return lienzo;
}

/**
 * Lee el número del documento. Devuelve el número (solo dígitos), null si se
 * intentó y no se encontró, o undefined si no se puede leer este archivo o el
 * lector falló (entonces se sube sin lectura). Nunca lanza.
 */
export async function leerNumeroDocumento(archivo, tipo, registrado) {
    if (!sePuedeLeer(archivo, tipo)) return undefined;
    let worker = null;
    try {
        const Tesseract = await cargarTesseract();
        const imagen = await createImageBitmap(archivo);
        worker = await Tesseract.createWorker('eng');
        // Solo dígitos, espacios y guiones; PSM 11 = texto disperso, como en una cédula.
        await worker.setParameters({ tessedit_char_whitelist: '0123456789 -', tessedit_pageseg_mode: '11' });
        // Cuatro variantes (completa o el centro con forma de tarjeta, con contraste o en
        // blanco y negro): en la prueba con fotos reales, juntas leyeron mucho mejor que una sola.
        const ancho = imagen.width * 0.8;
        const alto = Math.min(imagen.height, ancho * 54 / 85.6);
        const centro = [Math.round(imagen.width * 0.1), Math.round((imagen.height - alto) / 2), Math.round(ancho), Math.round(alto)];
        const variantes = [{}, { umbral: true }, { recorte: centro }, { recorte: centro, umbral: true }];
        const encontrados = new Set();
        for (const opciones of variantes) {
            const { data } = await worker.recognize(preparar(imagen, opciones));
            candidatosNumero(data.text, tipo).forEach((n) => encontrados.add(n));
            // Si ya apareció el número registrado, no hace falta seguir leyendo.
            if (elegirNumero([...encontrados], registrado) === String(registrado ?? '').replace(/\D/g, '')) break;
        }
        return elegirNumero([...encontrados], registrado);
    } catch {
        return undefined;
    } finally {
        await worker?.terminate().catch(() => {});
    }
}

/**
 * Lee el número (si se puede) y sube el archivo al bucket privado. Devuelve el
 * cuerpo para `PATCH api/v1/mi-perfil`: la ruta y, si hubo lectura, solo el
 * número. `avisar` recibe el paso actual para mostrarlo en pantalla.
 */
export async function prepararEnvioDocumento(archivo, { tipo, registrado, avisar = () => {} } = {}) {
    if (sePuedeLeer(archivo, tipo)) avisar('Leyendo el número de tu documento…');
    const numero = await leerNumeroDocumento(archivo, tipo, registrado);
    avisar('Subiendo documento…');
    const cuerpo = { documentoRuta: await subirDocumentoIdentidad(archivo) };
    if (numero !== undefined) cuerpo.documentoLectura = { numero };
    return cuerpo;
}
