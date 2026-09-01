// Capacidades de una persona: productor (vendedor), comprador y transportista.
//
// DEC-PER-001 fija que `tbpersona` concentra a la persona y que la existencia de
// una fila en `tbproductor`, `tbcomprador` o `tbtransportista` representa una
// capacidad. No hay tabla de roles: la pregunta "esta persona es comprador?" se
// responde consultando el endpoint de esa capacidad por identificacion.
//
// El contrato lo permite sin backend nuevo: GET ?identificacionNumero=X
// devuelve 200 con los datos si la capacidad existe y 404 si no existe.

/** Las tres capacidades del modelo, en el orden en que se muestran. */
export const CAPACIDADES = [
    {
        clave: 'productor',
        etiqueta: 'Productor',
        // El productor es quien vende: posee las fincas y el ganado. No existe
        // `tbvendedor`; "vendedor" es esta misma capacidad.
        alias: 'vendedor',
        api: 'api/productores.php',
        panel: 'productores.php',
    },
    { clave: 'comprador', etiqueta: 'Comprador', alias: null, api: 'api/compradores.php', panel: 'compradores.php' },
    {
        clave: 'transportista',
        etiqueta: 'Transportista',
        alias: null,
        api: 'api/transportistas.php',
        panel: 'transportistas.php',
    },
];

/**
 * Traduce el desenlace de una consulta a la situacion de la capacidad. Puro.
 *
 * Distingue tres situaciones y no dos, por la misma razon por la que la lista
 * separa vacio de error: un 404 es una respuesta del servidor y significa que la
 * persona NO tiene esa capacidad, mientras que un fallo de red no significa
 * nada. Pintar "no registrado" cuando lo que hubo fue un corte de red seria
 * afirmar algo que no se comprobo.
 *
 * @param {{ok: true, data: object} | {ok: false, error: {status: ?number}}} desenlace
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

/** Etiqueta que se muestra al usuario para una situacion ya interpretada. */
export function describirCapacidad({ situacion, estado }) {
    if (situacion === 'registrado') {
        return estado === 'ACTIVO' ? 'Registrado y activo' : 'Registrado, inactivo';
    }
    if (situacion === 'no-registrado') return 'No registrado';
    return 'No se pudo comprobar';
}

/**
 * Consulta las tres capacidades de una identificacion.
 *
 * Las tres van en paralelo porque son independientes: en serie el detalle
 * tardaria el triple sin ganar nada. `requestImpl` se inyecta para poder probar
 * esta funcion sin red.
 *
 * @returns {Promise<Array<{clave, etiqueta, alias, panel, identificacionNumero,
 *                            situacion, estado}>>}
 */
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
