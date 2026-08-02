# Evidencias de pruebas — Avance 01 (histórico)

Este archivo demuestra la entrega etiquetada `avance-01` y no describe el
modelo vigente después de la corrección docente. La evidencia nueva se registra
en `EvidenciasPruebasAvance01Correccion01.md`. Los resultados y hashes
históricos se conservan sin reescribirlos.

## Identificación

- Fecha: `2026-08-01 19:10 -06:00`
- Responsable: Dilan
- Rama: `refactor/modelo-productor-calidad`
- Commit candidato: `05c7a262870640c6ee8b05b35d18ff58065bfe8b`
- Base: `dbtindercows`
- Motor: MySQL 8.0.46
- Entorno: Docker Compose, PHP 8.2/Apache, Adminer 4
- Registro íntegro de la verificación: `/tmp/tindercows-avance01-evidencias/verificacion-final.txt`
- Captura de interfaz: `/tmp/tindercows-avance01-evidencias/productores-avance01.png`
- SHA-256 de la captura: `535b0a2d9eb7011ed4b443c205c2f0d55919ac16c0ec0c893c32f11c9e644b68`

Las rutas bajo `/tmp` son evidencias locales y no forman parte de Git. La
captura muestra la lista de productores, búsqueda, filtro, paginación, estados y
acciones servida desde `http://localhost:8080`.

## Instalación limpia y servicios

La pila se creó en el proyecto Compose aislado `tindercows-refactor`, sin tocar
el volumen de la línea base. Resultado de `docker compose -p tindercows-refactor ps`:

| Servicio | Estado | Puerto |
|---|---|---|
| `app` | activo | `8080:80` |
| `adminer` | activo | `8081:8080` |
| `db` | activo y saludable | `3307:3306` |

Los cinco scripts numerados se ejecutaron en orden al inicializar el volumen.
Se comprobaron 9 tablas, 3 roles, 5 tipos de identificación, 2 participantes,
2 direcciones, 2 identificaciones, 2 fincas y 3 asociaciones de semilla.

## Pruebas automáticas

| Script | Resultado |
|---|---|
| `Tests/naming_gate.php` | OK: nomenclatura, contrato JSON y controles estáticos |
| `Tests/schema_test.php` | OK: tablas, restricciones, correo compartido, unicidad y FK |
| `Tests/api_productores_test.php` | OK: CRUD JSON, búsqueda, validaciones, fincas y reactivación |
| `Tests/transaction_test.php` | OK: rollback por identidad, finca y bitácora |
| `Tests/role_test.php` | OK: roles múltiples, identidad única y filtro PRODUCTOR |
| `Tests/address_policy_test.php` | OK: dirección obligatoria, adicional y principal única |
| `Tests/audit_test.php` | OK: acciones, JSON, actor nulo, origen y solicitud |
| `Tests/concurrency_test.php` | OK: concurrencia de identidad y bloqueo de catálogos |
| `Tests/ui_test.js` | OK: AJAX, paginación, prevención de carreras y ARIA |
| `Tests/naming_eval.php` | OK: 100/100, umbral 100 |

Todos los scripts PHP se ejecutaron dentro del contenedor `app`. La prueba de
interfaz se ejecutó con Node en el host. Los scripts PowerShell recibieron
revisión estática, pero no se ejecutaron porque `pwsh` no está instalado en este
equipo. Las versiones Bash equivalentes sí se ejecutaron de principio a fin.

## Cobertura funcional comprobada

- Creación sin finca y con varias fincas.
- Consulta individual, listado paginado, búsqueda por nombre o identificación y filtro por estado.
- Actualización de contacto, identificación, dirección y asociaciones.
- Desactivación lógica, conservación de relaciones y reactivación del mismo ID.
- Documentos alfanuméricos y con ceros iniciales.
- Conflicto `409` para identidad duplicada activa o inactiva.
- `400` para JSON inválido, `415` para tipo de contenido incorrecto, `422` para validación, `404` para recursos ausentes, `405` para método no permitido y `500` sanitizado para falla interna.
- Dos participantes con el mismo correo.
- Una persona con PRODUCTOR y COMPRADOR sin duplicar `tbparticipante`.
- Una finca asociada a dos productores y un productor asociado a dos fincas.
- Rechazo de rol, asociación, FK, identificación y dirección principal duplicados.
- Una sola dirección principal activa y una dirección adicional no principal.
- Rollback sin participante parcial ni bitácora falsa cuando falla la bitácora.
- Bitácora `CREAR`, `ACTUALIZAR`, `DESACTIVAR` y `REACTIVAR`, con `NO_AUTENTICADO`, `tbusuarioId = NULL`, origen y solicitud correlacionable.
- Consultas preparadas, `.env` ignorado, salida mediante `textContent`, bloqueo de doble envío y actualización por `fetch()` sin recarga completa.

Muestra real de respuesta:

```json
{"success":true,"message":"Productor consultado correctamente.","data":{"participanteId":1,"rol":"PRODUCTOR","identificacion":{"tipoId":1,"tipoCodigo":"CEDULA_FISICA","numero":"1-0111-0111"},"nombre":"Maria Fernandez Solano","telefono":"88881111","correoElectronico":"contacto.compartido@example.test","estado":"ACTIVO","direccionPrincipal":{"provincia":"Alajuela","canton":"San Carlos","distrito":"Quesada","pueblo":"Centro","senas":"Datos ficticios para demostracion."},"fincas":[{"fincaId":1,"nombre":"Finca El Roble"},{"fincaId":2,"nombre":"Finca Valle Verde"}]}}
```

## Respaldo y restauración

- Carpeta: `Database/Backups/Avance01/`
- Los respaldos completo, estructura y datos existen y no están vacíos.
- `sha256sum -c SHA256SUMS.txt`: los tres archivos `OK`.
- Restauración completa probada en `dbtindercows_restore_test`.
- Restauración adicional de estructura + datos probada en una segunda base temporal.
- Origen y ambas restauraciones coincidieron en 9 tablas, 53 restricciones y 39 filas de índices.
- Conteos coincidentes: participantes 2, roles 3, tipos 5, identificaciones 2, direcciones 2, fincas 2, asociaciones 3 y bitácoras 0.
- La consulta funcional sobre los datos restaurados fue correcta.
- Las bases temporales se eliminaron después de comparar.
- `read_only` y `super_read_only` regresaron a `0` después de exportar.
- La búsqueda de secretos no encontró credenciales. Los datos versionados son ficticios y usan el dominio reservado `example.test`.

Sumas verificadas:

```text
db2b39de983bc9804a12b587d90971fc21f8fcf876ddef96ff90c82b412b5a65  dbtindercows_avance01_completo.sql
8a61a5a1fea133d791b27d61c9909b08c34430fd0660414d19f61fb8058c6fdf  dbtindercows_avance01_estructura.sql
f62ba7a248874082220eccccebcaf3ed8765a3df8f54d4b849fd80fbf16d8274  dbtindercows_avance01_datos.sql
```

## Veredicto

- Estado técnico: aprobado.
- Gate tests: 9 aprobados, 0 fallidos.
- Eval: 100/100.
- Restauraciones: 2 aprobadas, 0 diferencias.
- Limitación de esta ejecución: scripts PowerShell no ejecutados por ausencia de `pwsh`; scripts Bash equivalentes aprobados.
- La etiqueta anotada `avance-01` se crea sobre el commit final que incorpora este respaldo y esta evidencia.
