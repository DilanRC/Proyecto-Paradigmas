# Auditoría de accesibilidad — Explorar

Fecha: 2026-09-21  
URL solicitada: https://proyecto-paradigmas-jkx3k4e1x-stardust-crusaders.vercel.app/explorar  
Rama local: `feat/Front-2.0`  
Alcance solicitado: página pública `/explorar`, estado sin publicaciones y sin sesión iniciada.

## Herramienta solicitada y limitación

Se intentó usar el flujo `agent-browser` del plugin de Vercel para obtener su snapshot de accesibilidad. La herramienta no está instalada en este entorno: `agent-browser: command not found`.

Por esa razón, este documento conserva el resultado de una inspección del DOM mediante el runtime de Chrome y la revisión del código fuente local candidato. No se presenta como un resultado emitido por Vercel Accessibility ni como una certificación WCAG.

Hay una discrepancia de trazabilidad: `getTabContext` sobre la pestaña seleccionada reportó contenido de inicio aunque la URL mostrada era `/explorar`; durante la inspección directa, el runtime llegó a observar `/explorar`, pero una lectura posterior del mismo tab resolvió a `/` con título `Ganado Cerca — Ganado y oportunidades cerca de ti`. La evidencia de página y los hallazgos de fuente deben tratarse como observaciones separadas hasta repetir la captura en un tab estable.

## Resultado observado en la captura directa de `/explorar`

Una captura directa llegó a mostrar el título `Explorar | Ganado Cerca`, idioma `es` y un `h1`:

> Oportunidades para descubrir, comparar y decidir.

En esa captura directa, el estado mostrado fue:

- filtro `Todo`;
- estado vacío `No encontramos publicaciones.`;
- acción `Restablecer`;
- mensaje de participación que pide iniciar sesión;
- navegación principal, búsqueda, cambio de tema, enlaces de cuenta y pie de página.

## Hallazgos para corregir

### A11Y-001 — El nombre del botón de búsqueda no describe el estado abierto

- Severidad: alta. Prioridad: P1.
- Criterio relacionado: WCAG 4.1.2 Name, Role, Value.
- Evidencia en la página publicada: el botón expone `aria-expanded="true"` cuando la búsqueda está abierta, pero su nombre accesible sigue siendo `Abrir búsqueda`.
- Evidencia en fuente: `Application/View/explorar/index.php:48` fija `aria-label="Abrir búsqueda"`; `Public/js/public-ui.js:24-31` actualiza `aria-expanded`, pero no actualiza el nombre.
- Impacto: una persona que usa lector de pantalla recibe un nombre contradictorio con el estado actual del control.
- Corrección propuesta: actualizar el nombre junto con `aria-expanded`, por ejemplo `Abrir búsqueda` cuando está cerrado y `Cerrar búsqueda` cuando está abierto. Aplicar el mismo contrato a las demás vistas públicas que reutilizan `data-public-search-toggle`.
- Verificación requerida: abrir y cerrar la búsqueda con teclado y comprobar que nombre y `aria-expanded` cambian juntos.

### A11Y-002 — El filtro activo no expone su estado semántico

- Severidad: alta. Prioridad: P1.
- Criterio relacionado: WCAG 4.1.2 Name, Role, Value.
- Evidencia en fuente: `Application/View/explorar/index.php:71-73` renderiza el botón `Todo` con la clase visual `is-active`, pero sin `aria-pressed="true"`; además, `Public/js/explore.js:241-259` crea los filtros dinámicamente y tampoco expone `aria-pressed`.
- Impacto: el estado seleccionado solo se comunica visualmente; un lector de pantalla no puede distinguir el filtro activo.
- Corrección propuesta: mantener `aria-pressed="true"` en el filtro activo y `false` en los demás filtros. El render dinámico debe asignarlo al crear cada botón y actualizarlo junto con `state.proposito`.
- Verificación requerida: recorrer los filtros con teclado y comprobar que solo uno anuncia `pressed=true`.

## Evidencia de controles interactivos

La captura directa de `/explorar` enumeró 27 elementos interactivos visibles en el estado sin publicaciones. El conteo provino del DOM posterior a JavaScript con elementos visibles (`a`, `button`, `input`, `select`, `textarea`, `[role]` y `[tabindex]`), no de un snapshot de Vercel Accessibility. Debido a la discrepancia de rutas descrita arriba, este inventario no se puede usar todavía como evidencia reproducible del deployment. Sus nombres observados fueron:

1. `Ganado Cerca, inicio` — enlace a `./`
2. `Inicio` — enlace a `./`
3. `Explorar` — enlace a `explorar`
4. `Nosotros` — enlace a `./#nosotros`
5. `Cómo funciona` — enlace a `./#como-funciona`
6. `Publicar` — enlace a `publicar`
7. `Fletes` — enlace a `fletes`
8. `Abrir búsqueda` — botón de búsqueda
9. campo de búsqueda `q`, tipo `search`, etiquetado por `Buscar publicaciones`
10. `Buscar` — botón submit
11. `Cambiar a modo claro` — botón de tema
12. `Crear cuenta` — enlace a `registro`
13. `Entrar` — enlace a `entrar`
14. `Todo` — botón de filtro
15. `Restablecer` — botón
16. `Inicie sesión para inscribirse` — enlace a `entrar?next=explorar`
17–27. enlaces equivalentes del pie de página: marca, Inicio, Explorar, Nosotros, Cómo funciona, Entrar, Ayuda de uso, Sobre Ganado Cerca, Privacidad, Términos e Información legal.

## Aspectos que pasaron la inspección estática de esta página

- `html[lang="es"]` está presente.
- Existe un solo `h1` visible y la jerarquía observada continúa con `h2`.
- La búsqueda tiene un `label` asociado al input mediante `for`/`id`.
- La búsqueda usa `role="search"`.
- La navegación principal tiene `aria-label="Navegación principal"`.
- Los iconos decorativos observados tienen `aria-hidden="true"`.
- Los logotipos tienen `alt=""`, consistente con su uso decorativo dentro de una marca ya textualizada.
- El estado de participación usa `role="status"` y `aria-live="polite"`.
- Los controles visibles son elementos nativos `a`, `button` e `input`; no se observaron `div` usados como botones.
- `explore.css` contiene reglas `:focus-visible` para filtros, navegación interna, estados vacíos, acciones de tarjetas y participación; los controles compartidos de navegación se definen también en hojas públicas compartidas. La visibilidad real del foco en todos los tamaños y modos de color requiere prueba manual.

## Cobertura pendiente

No se pudo afirmar lo siguiente porque la integración solicitada no está instalada y no se ejecutó una sesión manual completa:

- resultado de axe o de otro escáner automatizado WCAG;
- correspondencia entre el checkout local y el deployment publicado: no se registró commit de deployment, identificador de deployment ni hash de los archivos servidos;
- captura estable y reproducible de `/explorar`: la pestaña seleccionada y la inspección directa resolvieron a contenido/rutas distintas;
- contraste calculado para todos los pares de color renderizados;
- recorrido completo con Tab, Shift+Tab y Escape;
- comportamiento de la búsqueda abierta en viewport móvil;
- estados con publicaciones cargadas, error de API, carga, tarjetas y acciones autenticadas;
- comportamiento del cambio de tema en todos los controles;
- prueba con lector de pantalla.

La captura directa usó viewport `1459×865`. No se registró un identificador de navegador ni una marca horaria con precisión de segundos. La inspección incluyó DOM posterior a la carga de módulos, pero no una prueba de todos los estados dinámicos.

## Orden de corrección

1. Repetir la captura en un tab estable y registrar URL final, título, viewport, commit/deployment y estado de sesión.
2. A11Y-001: sincronizar nombre accesible y estado de la búsqueda.
3. A11Y-002: sincronizar `aria-pressed` con el filtro activo, incluido el render dinámico.
4. Ejecutar una auditoría automatizada disponible y una pasada manual de teclado sobre `/`, `/explorar`, `/publicar`, `/fletes`, `/registro` y `/entrar`.
5. Repetir la verificación en estados vacío, cargando, error y con publicaciones.

Este archivo es la lista de trabajo para la siguiente corrección; todavía no modifica el código de la interfaz.
