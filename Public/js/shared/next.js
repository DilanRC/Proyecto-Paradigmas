// Destino seguro después de entrar o registrarse (?next=...).
//
// El destino se reconstruye, nunca se copia tal cual: solo páginas propias de
// la lista y solo parámetros conocidos. Así next no puede llevar a otro sitio
// y aun así devuelve a la persona a la publicación o búsqueda de donde venía.

export const PUBLIC_DESTINATIONS = new Set(['explorar', 'mi-actividad', 'fletes', 'publicar', 'ajustes']);

const NEXT_PARAMS = Object.freeze({
    publicacion: (valor) => /^\d{1,9}$/.test(valor),
    q: (valor) => valor.length <= 150,
    ubicacion: (valor) => valor.length <= 150,
    tipo: (valor) => valor.length <= 150,
    capacidad: (valor) => /^(comprador|productor|transportista)$/i.test(valor),
});

/** Devuelve el destino limpio de ?next=, o null si no es válido. */
export function safeNext(search = '') {
    const requested = new URLSearchParams(search).get('next') ?? '';
    const [ruta, consulta = ''] = requested.split('?');
    if (!PUBLIC_DESTINATIONS.has(ruta)) return null;
    const limpia = new URLSearchParams();
    for (const [clave, valor] of new URLSearchParams(consulta)) {
        if (Object.hasOwn(NEXT_PARAMS, clave) && NEXT_PARAMS[clave](valor)) limpia.set(clave, valor);
    }
    const texto = limpia.toString();
    return texto ? `${ruta}?${texto}` : ruta;
}
