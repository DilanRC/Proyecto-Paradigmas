// Maquina de estados de los listados. Funciones puras: sin DOM y sin fetch.
//
// Corrige el defecto central de los cuatro paneles: ante un fallo de red o un
// 500 el catch llamaba a render([], 0, size), lo que encendia el estado vacio y
// mostraba "No se encontraron X". Un fallo del servidor se presentaba como si
// la busqueda no tuviera resultados.
//
// La invariante que lo impide es una sola:
//     showEmpty  <=>  phase === 'ready' && items.length === 0
// Una lista vacia solo puede afirmarse cuando el servidor respondio bien.

/** phase: 'idle' | 'loading' | 'ready' | 'error' */
export function createListState({ pageSize = 25 } = {}) {
    return { phase: 'idle', items: [], total: 0, page: 1, pageSize, sequence: 0, error: null };
}

/**
 * Abre una peticion nueva. Devuelve el estado y su numero de secuencia: las
 * respuestas que lleguen con una secuencia vieja se descartan.
 */
export function nextRequest(state, { page = state.page } = {}) {
    const sequence = state.sequence + 1;
    return { state: { ...state, phase: 'loading', page, sequence, error: null }, sequence };
}

export function applyResult(state, { sequence, items, total, page, pageSize }) {
    if (sequence !== state.sequence) return state;      // respuesta obsoleta
    return {
        ...state,
        phase: 'ready',
        items,
        total,
        page: page ?? state.page,
        pageSize: pageSize ?? state.pageSize,
        error: null,
    };
}

export function applyFailure(state, { sequence, error }) {
    if (sequence !== state.sequence) return state;
    return { ...state, phase: 'error', items: [], error };
}

/**
 * Una cancelacion no es un fallo. Nunca cambia de fase: si se tratara como
 * error, escribir rapido en el buscador pintaria un error en cada pulsacion.
 */
export function applyAbort(state) {
    return state;
}

/** Traduce el estado a lo que la vista debe mostrar. Sin tocar el DOM. */
export function deriveListView(state, { singular = 'registro', plural = 'registros' } = {}) {
    const totalPages = Math.max(1, Math.ceil(state.total / state.pageSize));
    const loading = state.phase === 'loading';
    return {
        showSkeleton: loading,
        showList: state.phase === 'ready' && state.items.length > 0,
        showEmpty: state.phase === 'ready' && state.items.length === 0,
        showError: state.phase === 'error',
        canRetry: state.phase === 'error' && (state.error?.retryable ?? true),
        errorMessage: state.error?.message ?? '',
        totalLabel: state.phase === 'ready'
            ? `${state.total} ${state.total === 1 ? singular : plural} ${state.total === 1 ? 'encontrado' : 'encontrados'}`
            : '',
        pageLabel: `Pagina ${state.page} de ${totalPages}`,
        previousDisabled: loading || state.page <= 1,
        nextDisabled: loading || state.page >= totalPages,
        refreshDisabled: loading,
    };
}
