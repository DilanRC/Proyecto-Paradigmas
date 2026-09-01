# DEC-FRONT-001 — Borradores temporales de formularios

**Estado:** IMPLEMENTADA  
**Alcance:** formularios CRUD principales de Productor, Transportista, Vehículo y Método de pago.  
**Origen:** petición de Calidad de no obligar a reescribir un formulario cuando el guardado falla.

## Conclusión

Los datos introducidos por la persona usuaria se conservan temporalmente en `sessionStorage` mientras trabaja en un formulario. Un error de validación, conflicto, servidor o red no elimina lo ya escrito. El borrador se borra únicamente cuando el guardado termina correctamente y el diálogo se cierra.

No se usa `localStorage`: el requisito es recuperación temporal durante la sesión, no persistencia indefinida en el navegador.

## Actores y responsabilidades

- **Persona usuaria:** introduce o modifica datos en el formulario.
- **Frontend:** captura los valores, los guarda temporalmente, restaura el borrador y lo elimina después de un guardado correcto.
- **Backend:** sigue siendo responsable de validar y persistir los datos definitivos. El borrador no sustituye a la base de datos.
- **Base de datos:** no cambia por esta decisión.

## Mecanismo

`Public/js/shared/form-draft.js` centraliza el comportamiento. `Public/js/shared/form.js` lo activa para los formularios que ya usan `bindFormErrors()` y tienen una identidad estable.

Flujo:

```text
input/change
    ↓ debounce 250 ms
sessionStorage
    ↓ al volver a abrir el diálogo
restauración de valores
    ↓
POST/PUT
├─ 422 / 409 / 500 / red → conservar borrador
└─ 2xx + diálogo cerrado → borrar borrador
```

Las claves separan creación de edición y separan cada registro:

```text
tindercows:draft:productor:create
tindercows:draft:productor:edit:<identificacion>
tindercows:draft:transportista:create
tindercows:draft:transportista:edit:<identificacion>
tindercows:draft:vehiculo:create
tindercows:draft:vehiculo:edit:<id>
tindercows:draft:pagometodo:create
tindercows:draft:pagometodo:edit:<id>
```

## Datos almacenados

Se guardan únicamente valores de controles con `name` que representan datos del formulario. Se excluyen explícitamente:

- `password`;
- `file`;
- botones `submit`, `button`, `reset` e `image`;
- errores de validación;
- `aria-invalid`;
- estados de carga;
- botones deshabilitados;
- mensajes/toasts.

El registro guardado contiene versión, fecha de guardado y los valores serializados. La fecha es técnica y no tiene semántica de negocio.

## Relaciones y restauración territorial

Los formularios de Productor contienen la cascada Provincia → Cantón → Distrito → Pueblo. La restauración recorre los controles en orden DOM y emite `change` después de cada `select`, permitiendo que `direccion.js` reconstruya primero las opciones dependientes antes de aplicar el valor siguiente.

## Política de errores y recuperación

`sessionStorage` es una mejora de experiencia, no una dependencia crítica. Si el navegador bloquea el almacenamiento, se alcanza la cuota, el JSON está corrupto o el API no está disponible, el formulario continúa funcionando sin borrador.

Cerrar manualmente el diálogo no elimina el borrador. Esto permite volver a abrirlo durante la misma sesión y recuperar lo escrito.

## Límite conocido

`formulario-direccion-finca` no activa todavía el borrador automático porque su DOM no contiene una identidad estable que combine Productor + Finca. Guardarlo bajo una clave global podría restaurar la dirección de una finca sobre otra, lo cual sería peor que no guardar.

Para cubrir ese formulario en un bloque posterior se debe exponer explícitamente su contexto (`identificacionNumero` + `nombreFinca`) al módulo de borradores. No se debe inferir la identidad leyendo texto visible del modal.

## Comprobación

`Tests/frontend/form_draft.test.mjs` cubre:

- separación crear/editar;
- separación entre registros;
- exclusión de datos no persistibles;
- guardar/restaurar/limpiar;
- debounce en `input/change`;
- eventos `change` para selects dependientes;
- almacenamiento corrupto o no disponible.

`Tests/frontend_contract_test.js` fija la integración con `form.js`, el uso de `sessionStorage` y la prohibición de `localStorage`.
