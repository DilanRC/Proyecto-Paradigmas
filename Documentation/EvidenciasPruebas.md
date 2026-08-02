# Evidencias de pruebas

Esta es una plantilla de registro. `PENDIENTE` no significa aprobado. Sustituya
los marcadores solo con resultados reales del commit candidato y adjunte la
salida o ruta de la captura. No incluya credenciales ni datos personales reales.

## Identificación de la ejecución

- Fecha y hora: `PENDIENTE`
- Responsable: `PENDIENTE`
- Rama: `PENDIENTE`
- Commit completo: `PENDIENTE`
- Docker: `PENDIENTE`
- MySQL: `PENDIENTE`
- Navegador: `PENDIENTE`

## Instalación limpia

Comandos:

```bash
cp .env.example .env
docker compose down -v
docker compose up --build -d
docker compose ps
```

- Resultado: `PENDIENTE`
- Servicios saludables: `PENDIENTE`
- Evidencia `docker compose ps`: `PENDIENTE: ruta/captura`
- Evidencia Adminer con `dbtindercows`: `PENDIENTE: ruta/captura`
- Tablas observadas: `PENDIENTE`
- Catálogos y semillas cargados una sola vez: `PENDIENTE`

## Matriz de pruebas automáticas

| Script | Tipo | Comando | Resultado | Evidencia |
|---|---|---|---|---|
| `schema_test.php` | gate | `php Tests/schema_test.php` | PENDIENTE | PENDIENTE |
| `api_productores_test.php` | gate/integración | `php Tests/api_productores_test.php` | PENDIENTE | PENDIENTE |
| `transaction_test.php` | gate/integración | `php Tests/transaction_test.php` | PENDIENTE | PENDIENTE |
| `role_test.php` | gate/integración | `php Tests/role_test.php` | PENDIENTE | PENDIENTE |
| `address_policy_test.php` | gate/integración | `php Tests/address_policy_test.php` | PENDIENTE | PENDIENTE |
| `audit_test.php` | gate/integración | `php Tests/audit_test.php` | PENDIENTE | PENDIENTE |
| `naming_gate.php` | gate | `php Tests/naming_gate.php` | PENDIENTE | PENDIENTE |
| `naming_eval.php` | eval | `php Tests/naming_eval.php` | PENDIENTE | PENDIENTE |

Si un script no existe en el commit candidato, registre `NO EXISTE` y trate el
criterio correspondiente como pendiente, no como aprobado.

## Base de datos

| Caso | Resultado | Evidencia / consulta |
|---|---|---|
| Nombre exacto `dbtindercows` | PENDIENTE | PENDIENTE |
| Scripts ejecutados en orden | PENDIENTE | PENDIENTE |
| Tipo + normalizado duplicado rechazado | PENDIENTE | PENDIENTE |
| Mismo correo en dos participantes permitido | PENDIENTE | PENDIENTE |
| FK inválida rechazada | PENDIENTE | PENDIENTE |
| Rol duplicado rechazado | PENDIENTE | PENDIENTE |
| Asociación duplicada rechazada | PENDIENTE | PENDIENTE |
| Una finca con dos productores | PENDIENTE | PENDIENTE |
| Un productor con dos fincas | PENDIENTE | PENDIENTE |
| Borrado físico relacionado restringido | PENDIENTE | PENDIENTE |
| Rollback en cada punto de falla | PENDIENTE | PENDIENTE |

## API

Base: `http://localhost:8080/api/productores.php`.

| Caso | HTTP esperado | Resultado real | Evidencia |
|---|---:|---|---|
| Listar y paginar | 200 | PENDIENTE | PENDIENTE |
| Buscar por nombre/identificación | 200 | PENDIENTE | PENDIENTE |
| Filtrar activos/inactivos | 200 | PENDIENTE | PENDIENTE |
| Crear sin finca | 201 | PENDIENTE | PENDIENTE |
| Crear con varias fincas | 201 | PENDIENTE | PENDIENTE |
| Mismo correo en dos participantes | 201 | PENDIENTE | PENDIENTE |
| Identidad normalizada duplicada | 409 | PENDIENTE | PENDIENTE |
| Identidad de inactivo indica reactivación | 409 | PENDIENTE | PENDIENTE |
| Consultar por ID | 200 | PENDIENTE | PENDIENTE |
| Actualizar contacto/dirección/fincas | 200 | PENDIENTE | PENDIENTE |
| Desactivar y conservar fila | 200 | PENDIENTE | PENDIENTE |
| Reactivar mismo ID | 200 | PENDIENTE | PENDIENTE |
| Tipo inexistente/inactivo | 422 | PENDIENTE | PENDIENTE |
| Documento alfanumérico | 201 | PENDIENTE | PENDIENTE |
| Documento con ceros iniciales | 201 | PENDIENTE | PENDIENTE |
| JSON inválido | 400 | PENDIENTE | PENDIENTE |
| `Content-Type` incorrecto | 415 | PENDIENTE | PENDIENTE |
| ID inexistente | 404 | PENDIENTE | PENDIENTE |
| Finca inexistente/inactiva | 422 | PENDIENTE | PENDIENTE |
| Finca con campos extra o sin `fincaId` | 422 | PENDIENTE | PENDIENTE |
| Método no permitido | 405 | PENDIENTE | PENDIENTE |
| Falla inesperada de BD | 500 sin detalle interno | PENDIENTE | PENDIENTE |
| Toda respuesta usa JSON válido | Sí | PENDIENTE | PENDIENTE |

Pegue aquí peticiones y respuestas sanitizadas de crear, consultar, actualizar,
desactivar y reactivar:

```text
PENDIENTE
```

## Dirección, roles y estado

| Caso | Resultado | Evidencia |
|---|---|---|
| Activo sin principal rechazado | PENDIENTE | PENDIENTE |
| Una principal permitida | PENDIENTE | PENDIENTE |
| Dos principales activas rechazadas | PENDIENTE | PENDIENTE |
| Dirección adicional no principal permitida | PENDIENTE | PENDIENTE |
| Desactivar principal sin reemplazo rechazado | PENDIENTE | PENDIENTE |
| Asignar COMPRADOR al mismo participante | PENDIENTE | PENDIENTE |
| La persona no se duplica | PENDIENTE | PENDIENTE |
| Productores excluye quien no tiene ese rol | PENDIENTE | PENDIENTE |
| Desactivar conserva relaciones | PENDIENTE | PENDIENTE |
| Inactivo no admite nuevas operaciones | PENDIENTE | PENDIENTE |

## Bitácora y transacciones

| Caso | Resultado | Evidencia |
|---|---|---|
| `CREAR` | PENDIENTE | PENDIENTE |
| `ACTUALIZAR`, anteriores y nuevos | PENDIENTE | PENDIENTE |
| `DESACTIVAR` | PENDIENTE | PENDIENTE |
| `REACTIVAR` | PENDIENTE | PENDIENTE |
| `actorTipo = NO_AUTENTICADO` | PENDIENTE | PENDIENTE |
| `tbusuarioId IS NULL` | PENDIENTE | PENDIENTE |
| `origen = API_PRODUCTORES` | PENDIENTE | PENDIENTE |
| Solicitud correlacionable | PENDIENTE | PENDIENTE |
| Rollback no deja dominio parcial | PENDIENTE | PENDIENTE |
| Rollback no deja bitácora falsa | PENDIENTE | PENDIENTE |

## Interfaz y seguridad

| Caso | Resultado | Evidencia |
|---|---|---|
| CRUD sin recarga completa | PENDIENTE | PENDIENTE: captura de Network/Performance |
| Errores por campo | PENDIENTE | PENDIENTE |
| Prevención de doble envío | PENDIENTE | PENDIENTE |
| Tabla se actualiza tras operación | PENDIENTE | PENDIENTE |
| Estado y reactivación visibles | PENDIENTE | PENDIENTE |
| Teclado, foco y `aria-invalid` | PENDIENTE | PENDIENTE |
| Datos externos insertados con `textContent` | PENDIENTE | PENDIENTE |
| Consultas preparadas | PENDIENTE | PENDIENTE |
| `.env` ignorado | PENDIENTE | PENDIENTE |
| Sin usuario ficticio ni IP como identidad | PENDIENTE | PENDIENTE |
| Error interno no expuesto | PENDIENTE | PENDIENTE |

## Respaldo y restauración

- Entrega: `PENDIENTE: AvanceNN`
- Carpeta: `PENDIENTE`
- Commit candidato: `PENDIENTE`
- Tres SQL no vacíos: `PENDIENTE`
- `sha256sum -c`: `PENDIENTE: pegue salida`
- Restauración en `dbtindercows_restore_test`: `PENDIENTE`
- Comparación de tablas: `PENDIENTE`
- Comparación PK/FK/UK/índices: `PENDIENTE`
- Conteos por tabla: `PENDIENTE`
- Consulta funcional: `PENDIENTE`
- Base temporal eliminada después de registrar evidencia: `PENDIENTE`
- Manifiesto completo: `PENDIENTE`
- Revisión de secretos/datos reales: `PENDIENTE`
- Etiqueta `avance-NN` apunta al commit final: `PENDIENTE`

Salida real:

```text
PENDIENTE
```

## Veredicto

- Estado: `PENDIENTE`
- Casos aprobados: `PENDIENTE`
- Casos fallidos: `PENDIENTE`
- Casos no ejecutados: `PENDIENTE`
- Bloqueos concretos: `PENDIENTE`
- Decisión de entrega: `PENDIENTE`

