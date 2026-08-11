(() => {
    'use strict';
    const IDENTIFICACION_DESTACADA = '101110111';

    async function cargarProductorDestacado() {
        try {
            const respuesta = await fetch(`api/productores.php?identificacionNumero=${IDENTIFICACION_DESTACADA}`, {
                headers: { Accept: 'application/json' },
            });
            const cuerpo = await respuesta.json();
            if (!respuesta.ok || !cuerpo.success) return;

            const productor = cuerpo.data;
            const nombreElemento = document.querySelector('.rural__producer-name');
            if (nombreElemento) nombreElemento.textContent = productor.nombre;

            const finca = productor.fincas?.[0]?.nombre;
            const fincaElemento = document.querySelector('[data-finca]');
            if (finca && fincaElemento) fincaElemento.textContent = finca;
        } catch {
            // Sin conexion a la API: se conserva el contenido estatico de respaldo.
        }
    }

    document.addEventListener('DOMContentLoaded', cargarProductorDestacado);
})();
