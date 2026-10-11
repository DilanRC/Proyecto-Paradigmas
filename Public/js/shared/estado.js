// Línea de estado de un formulario (.auth-status). Sin tipo se ve verde, así que un error mostrado con
// textContent salía en un cuadro verde. Con esto cada mensaje lleva su color: 'error' rojo, 'info' neutro
// (guardando, buscando) y 'success' verde.

/** @param {'error'|'info'|'success'} tipo */
export function mostrarEstado(nodo, texto, tipo = 'info') {
    if (!nodo) return;
    nodo.textContent = texto ?? '';
    nodo.dataset.status = texto ? tipo : '';
}
