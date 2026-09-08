// Relaciones visibles de una persona en la red: Productor, Comprador y Transportista.
// La identidad vive en tbpersona; cada entrada consulta un contexto de negocio.
// Ninguno de estos contextos debe interpretarse como un rol administrativo.

/** Lecturas de la persona, en el orden en que se muestran en las fichas. */
export const CAPACIDADES = [
    {
        clave: 'productor',
        etiqueta: 'Productor',
        alias: null,
        api: 'api/productores.php',
        panel: 'productores.php',
        derivada: false,
    },
    {
        clave: 'comprador',
        etiqueta: 'Comprador',
        alias: null,
        api: 'api/compradores.php',
        panel: 'compradores.php',
        derivada: false,
    },
    {
        clave: 'transportista',
        etiqueta: 'Transportista',
        alias: null,
        api: 'api/transportistas.php',
        panel: 'transportistas.php',
        derivada: false,
    },
];

/**
 * Traduce el desenlace HTTP a una situación comprobable.
 * 200 confirma que el contexto existe; 404 confirma ausencia. Un error de red
 * o 5xx no permite inferir nada sobre los datos.
 */
export function interpretarCapacidad(desenlace) {
    if (desenlace.ok) {
        return {
            situacion: 'registrado',
            estado: desenlace.data?.estado === 'ACTIVO' ? 'ACTIVO' : 'INACTIVO',
        };
    }
    if (desenlace.error?.status === 404) return { situacion: 'no-registrado', estado: null };
    return { situacion: 'desconocido', estado: null };
}

export function describirCapacidad({ situacion, estado }) {
    if (situacion === 'registrado') {
        return estado === 'ACTIVO' ? 'Registrado y activo' : 'Registrado, inactivo';
    }
    if (situacion === 'no-registrado') return 'No registrado';
    return 'No se pudo comprobar';
}

/** Consulta los contextos de negocio de una misma Persona en paralelo. */
export async function consultarCapacidades(identificacionNumero, { requestImpl }) {
    const consulta = encodeURIComponent(identificacionNumero);
    return Promise.all(CAPACIDADES.map(async (capacidad) => {
        let desenlace;
        try {
            const respuesta = await requestImpl(`${capacidad.api}?identificacionNumero=${consulta}`);
            desenlace = { ok: true, data: respuesta.data };
        } catch (error) {
            desenlace = { ok: false, error };
        }
        return { ...capacidad, identificacionNumero, ...interpretarCapacidad(desenlace) };
    }));
}
