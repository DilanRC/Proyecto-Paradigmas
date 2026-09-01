// Relaciones visibles de una persona en la red: Productor, clasificación Comprador y Transportista.
//
// DEC-PER-001 fija que `tbpersona` concentra la identidad y que la existencia de
// una fila en `tbproductor` o `tbtransportista` representa una capacidad
// operativa. Comprador es distinto: desde DEC-DBREADY-008 es una clasificación
// histórica del Productor, leída desde un periodo COMPRADOR abierto.
//
// Productor tampoco es sinónimo de Vendedor. VENDEDOR es otra clasificación del
// mismo Productor y puede coexistir con COMPRADOR; por eso este módulo no usa el
// alias histórico "vendedor" para Productor ni crea `tbvendedor`.
//
// El nombre exportado CAPACIDADES se conserva por compatibilidad de los paneles,
// pero cada entrada declara `derivada` cuando la lectura corresponde a una
// clasificación y no a un registro administrable.

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
        // No se registra a mano. El endpoint responde 200 si existe un periodo
        // COMPRADOR abierto y 404 si no existe; la etiqueta visual debe hablar
        // de clasificación y no de alta/registro.
        derivada: true,
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
 * Traduce el desenlace HTTP a una situación comprobable. Puro.
 *
 * `registrado` aquí significa solamente "el endpoint devolvió 200". Para una
 * entrada `derivada` (Comprador), ese 200 significa "clasificación vigente" y
 * `describirCapacidad()` lo expresa así. Un 404 sí permite afirmar ausencia;
 * un fallo de red o 500 no permite concluir nada sobre los datos.
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

/** Etiqueta que se muestra al usuario para una situación ya interpretada. */
export function describirCapacidad({ situacion, estado, derivada = false }) {
    if (situacion === 'registrado') {
        if (derivada) {
            return estado === 'ACTIVO' ? 'Clasificado actualmente' : 'Clasificado, persona inactiva';
        }
        return estado === 'ACTIVO' ? 'Registrado y activo' : 'Registrado, inactivo';
    }
    if (situacion === 'no-registrado') return derivada ? 'Sin clasificación vigente' : 'No registrado';
    return 'No se pudo comprobar';
}

/**
 * Consulta Productor, clasificación Comprador y Transportista para una identificación.
 *
 * Las tres lecturas van en paralelo porque son independientes. `requestImpl` se
 * inyecta para poder probar el módulo sin red. Comprador conserva `derivada:
 * true` en el resultado para que ningún panel lo vuelva a rotular como registro.
 *
 * @returns {Promise<Array<{clave, etiqueta, alias, panel, derivada, identificacionNumero,
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
