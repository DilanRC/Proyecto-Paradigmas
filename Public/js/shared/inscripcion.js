// Flujo no-CRUD de inscripción desde la vista (Tramo B del sprint).
//
// El sistema no obliga a entender el modelo de base de datos: una misma
// Persona se inscribe, abandona o reactiva contextos (COMPRADOR, PRODUCTOR,
// TRANSPORTISTA) desde la acción natural de la vista, reutilizando
// /api/capacidades.php con el sobre {success,message,data,errors} de
// DEC-28/29. Este módulo:
//
//   1. Resuelve la superficie con identidad.php (publica) para no pintar
//      controles de escritura a un visitante sin sesión.
//   2. Consulta el estado de cada contexto con las lecturas públicas de cada
//      panel (misma lógica que consultarCapacidades).
//   3. Ejecuta inscribir / abandonar / reactivar y traduce un 401 SIN_SESION
//      en "inicie sesión", nunca en un error genérico.
//
// No hay navegación interna desde este módulo: el único destino que se ofrece
// es el enlace explícito a login.php (contrato de la vista pública).

import { request } from './api.js';
import { consultarCapacidades } from './capacidades.js';
import { resolverSuperficie } from './sesion.js';

export const CAPACIDADES_URL = 'api/capacidades.php';

/** Contextos registrables presentados por la acción de negocio. */
export const CONTEXTOS_INSCRIPCION = [
    {
        clave: 'comprador',
        etiqueta: 'Comprador',
        accionNatural: 'comprar',
        descripcion: 'Regístrese para explorar y guardar oportunidades de ganado.',
    },
    {
        clave: 'productor',
        etiqueta: 'Productor',
        accionNatural: 'publicar y vender',
        descripcion: 'Regístrese para publicar ganado y vender desde su finca.',
    },
    {
        clave: 'transportista',
        etiqueta: 'Transportista',
        accionNatural: 'ofrecer fletes',
        descripcion: 'Regístrese para ofrecer fletes y traslados de ganado.',
    },
];

/** Etiqueta humana para la acción que el servicio ejecuta. */
export const ETIQUETA_ACCION = {
    inscribir: 'Inscribirme',
    abandonar: 'Abandonar',
    reactivar: 'Reactivar',
};

/**
 * Decide, para un contexto, la acción de negocio que la vista debe ofrecer.
 * Reutiliza la interpretación de consultarCapacidades: un 404 es ausencia
 * comprobada; un fallo de red o 5xx no permite concluir nada.
 *
 * @returns {string} 'inscribir' | 'abandonar' | 'reactivar' | 'indefinido'
 */
export function accionParaContexto(capacidad) {
    const situacion = capacidad?.situacion;
    if (situacion === 'registrado' && capacidad?.estado === 'ACTIVO') return 'abandonar';
    if (situacion === 'registrado' && capacidad?.estado === 'INACTIVO') return 'reactivar';
    if (situacion === 'no-registrado') return 'inscribir';
    return 'indefinido';
}

/**
 * Ejecuta una acción de inscripción contra /api/capacidades.php. Devuelve la
 * respuesta del sobre o un desenlace {ok:false, requiereSesion:true} cuando el
 * backend responde 401/403 SIN_SESION (la vista lo convierte en "inicie
 * sesión").
 */
export async function enviarAccionInscripcion({
    contexto,
    accion,
    identificacionNumero,
    datosPersona = null,
    motivo = null,
    requestImpl = request,
}) {
    const cuerpo = {
        accion,
        contexto,
        identificacionNumero,
        ...(motivo ? { motivo } : {}),
        ...(datosPersona && typeof datosPersona === 'object' ? { datosPersona } : {}),
    };
    try {
        const respuesta = await requestImpl(CAPACIDADES_URL, {
            method: 'POST',
            body: JSON.stringify(cuerpo),
        });
        return { ok: true, respuesta };
    } catch (error) {
        if (error?.status === 401 || error?.status === 403) {
            return { ok: false, requiereSesion: true, error };
        }
        return { ok: false, requiereSesion: false, error };
    }
}

// ---------------------------------------------------------------------------
// Render (navegador). Todo el texto externo entra por textContent; no hay
// innerHTML y ninguna navegación fuera del enlace explícito a login.php.
// ---------------------------------------------------------------------------

function elemento(tag, texto, clase) {
    const nodo = document.createElement(tag);
    if (clase) nodo.className = clase;
    if (texto !== undefined) nodo.textContent = texto;
    return nodo;
}

function crearMensaje(modo, texto) {
    const p = elemento('p', texto, `inscripcion__aviso inscripcion__aviso--${modo}`);
    p.setAttribute('role', modo === 'error' ? 'alert' : 'status');
    return p;
}

/** Enlace explícito a login.php (la única navegación permitida de la vista). */
function crearEnlaceLogin() {
    const enlace = document.createElement('a');
    enlace.href = 'login.php?next=explorar.php';
    enlace.className = 'inscripcion__login';
    const icono = document.createElement('i');
    icono.className = 'fa-solid fa-right-to-bracket';
    icono.setAttribute('aria-hidden', 'true');
    enlace.append(icono, ' Inicie sesión para inscribirse');
    return enlace;
}

function crearBotonAccion(capacidad, identificacionNumero, onResultado, requestImpl) {
    const accion = accionParaContexto(capacidad);
    if (accion === 'indefinido') {
        const aux = elemento('span', 'No se pudo comprobar el estado', 'inscripcion__aviso inscripcion__aviso--muted');
        return aux;
    }
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = `inscripcion__accion inscripcion__accion--${accion}`;
    boton.textContent = `${ETIQUETA_ACCION[accion]} ${capacidad.etiqueta}`;
    boton.addEventListener('click', async () => {
        boton.disabled = true;
        const desenlace = await enviarAccionInscripcion({
            contexto: capacidad.clave,
            accion,
            identificacionNumero,
            requestImpl,
        });
        onResultado(desenlace, accion, capacidad.etiqueta);
    });
    return boton;
}

/**
 * Arma el bloque "Tu participación" dentro de `contenedor`.
 * - Sin sesión: aviso + enlace a login (solo lectura).
 * - Con sesión: estado de cada contexto + la acción de negocio correspondiente.
 */
export async function montarInscripcion({
    contenedor,
    requestImpl = request,
    storage = null,
}) {
    if (!contenedor) return null;
    contenedor.replaceChildren();

    const resuelto = await resolverSuperficie({ requestImpl, storage });
    if (!resuelto.autenticado) {
        contenedor.append(
            crearMensaje('muted', 'Para inscribirse, abandonar o reactivar un contexto de negocio se necesita una sesión verificada.'),
            crearEnlaceLogin(),
        );
        return null;
    }

    const identidad = resuelto.data ?? {};
    const identificacionNumero = resuelto.actor?.identificacionNumero
        ?? identidad.identificacionNumero;
    if (!identificacionNumero) {
        contenedor.append(crearMensaje('error', 'La sesión no corresponde a una persona registrada.'));
        return null;
    }

    const cabecera = elemento('p', `Sesión verificada · identificación ${identificacionNumero}`, 'inscripcion__identidad');
    contenedor.append(cabecera);

    let capacidades;
    try {
        capacidades = await consultarCapacidades(identificacionNumero, { requestImpl });
    } catch {
        contenedor.append(crearMensaje('error', 'No fue posible consultar sus contextos. Reintente.'));
        return null;
    }

    const onResultado = (desenlace, accion, etiqueta) => {
        const aviso = desenlace.ok
            ? crearMensaje('ok', `Listo: ${desenlace.respuesta?.message ?? `acción ${accion} completada`}`)
            : desenlace.requiereSesion
                ? crearMensaje('error', 'Su sesión venció. Inicie sesión para continuar.')
                : crearMensaje('error', desenlace.error?.message ?? 'No fue posible completar la acción.');
        contenedor.append(aviso);
        // Luego de una acción el estado cambió: se vuelve a montar.
        montarInscripcion({ contenedor, requestImpl, storage });
    };

    for (const contexto of CONTEXTOS_INSCRIPCION) {
        const capacidad = capacidades.find((c) => c.clave === contexto.clave);
        if (!capacidad) continue;

        const fila = elemento('article', null, 'inscripcion__contexto');
        const titulo = elemento('h3', contexto.etiqueta);
        const resumen = elemento('p', contexto.descripcion, 'inscripcion__descripcion');

        const estado = capacidad.situacion === 'registrado'
            ? (capacidad.estado === 'ACTIVO' ? 'Activo' : 'Inactivo')
            : capacidad.situacion === 'no-registrado' ? 'No registrado' : 'Sin comprobar';
        const badge = elemento('span', estado, `inscripcion__estado inscripcion__estado--${capacidad.situacion}`);

        fila.append(titulo, badge, resumen, crearBotonAccion(capacidad, identificacionNumero, onResultado, requestImpl));
        contenedor.append(fila);
    }
}
