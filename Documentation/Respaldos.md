# Respaldos versionados y restauración

## Respaldo previo al remodelado por tramos (tramo 1, EIF400)

Antes de tocar estado, dirección y ubicación históricos (tramos 2-11) se generó
un respaldo completo del estado actual, para poder revertir si algo del
remodelado rompe datos existentes:

```bash
Tools/backup-database.sh Avance02 Jeremi
Tools/test-restore.sh Avance02
```

```text
Database/Backups/Avance02/
├── dbmercadoganadero_avance02_completo.sql
├── dbmercadoganadero_avance02_estructura.sql
├── dbmercadoganadero_avance02_datos.sql
├── MANIFEST.md
└── SHA256SUMS.txt
```

Resultado: quince tablas, cero PK/FK/CHECK/índices/AUTO_INCREMENT, restauración
completa y por partes verificadas byte a byte contra el origen (`APROBADO` en
`MANIFEST.md`). Los ocho respaldos anteriores (`Avance01` hasta `LineaBase`) no
se tocaron, no se movieron y no se reorganizaron.

## Checkpoint previo a la ampliación histórica (P0/T1)

Antes de cualquier DDL posterior a P0-C se generó el checkpoint local
`Database/Backups/Avance03/` desde el commit `3b8753b`. Sus tres dumps, su
manifiesto y SHA256 se verificaron mediante restauración completa y por partes
en las bases temporales del verificador: 15 tablas históricas, cero restricciones y cero
índices, sin diferencias de conteo. Los respaldos históricos no fueron
modificados. Este checkpoint todavía pertenece al nombre legado
`dbmercadoganadero`; el verificador lo conserva como respaldo histórico y no
reescribe su `MANIFEST.md` ni sus sumas SHA256. Los dumps quedan fuera del
control de versiones por la política de `.gitignore`; no se deben sustituir ni
regenerar sin crear un checkpoint nuevo.

## Entrega vigente

El contrato vigente usa `bdmercadoganadero` y 48 tablas. Los respaldos nuevos
se generan con ese nombre; los respaldos históricos con prefijo
`dbmercadoganadero` se conservan como evidencia y se restauran sin reescribir
dumps, `MANIFEST.md` ni `SHA256SUMS.txt`.

```bash
Tools/backup-database.sh AvanceNN Dilan
Tools/test-restore.sh AvanceNN
```

PowerShell dispone de los comandos equivalentes.

## Restaurar un respaldo anterior a la regla de llaves primarias

`Tools/test-restore.sh` valida un respaldo contra el contrato vigente y por eso rechaza los
respaldos históricos (`Avance02`, `Avance03` y las correcciones de `Avance01`): se tomaron
cuando el esquema no declaraba llaves. Siguen siendo válidos como evidencia (sus sumas
SHA256 coinciden) y se pueden llevar al esquema actual sin perder filas:

1. Compruebe las sumas: `cd Database/Backups/Avance03 && sha256sum -c SHA256SUMS.txt`.
2. Restaure el archivo `_completo.sql` en una base **nueva y vacía** llamada
   `bdmercadoganadero` (`utf8mb4_unicode_ci`), nunca sobre una base con datos.
3. Aplique en orden las migraciones posteriores a la última que ya incluía el respaldo.
   `Avance03` (commit `3b8753b`) incluye hasta la `005`: aplique de la `006` a la `026`.
4. Importe `Database/SeedData/102administrador.sql` (el respaldo no trae administradores)
   y `104catalogosanimal.sql` (catálogos del animal).
5. Verifique con `Database/Tests/comprobacionestructura.sql`: 48 tablas, 48 llaves y
   ninguna otra restricción.

Comprobado el 12/10/2026 en MySQL 8.0 con `Avance03`: 15 tablas sin llave y 49 personas,
6 productores y 6 fincas pasaron a 48 tablas con 48 llaves, las mismas filas y las mismas
columnas, tipos y nulos que una instalación limpia. Repetir la 025 y la 026 no cambió nada.
`Tests/migraciones_cadena_test.php` falla si una tabla del esquema vigente deja de tener
una migración que la cree.

Límites: no se probaron por esta vía los respaldos de `Avance01` (base `dbtindercows`, otro
modelo de datos). Las migraciones 001 a 005 no se pueden repetir sobre una base que ya las
tiene.

## Contenido

```text
Database/Backups/AvanceNN/
├── bdmercadoganadero_avanceNN_completo.sql
├── bdmercadoganadero_avanceNN_estructura.sql
├── bdmercadoganadero_avanceNN_datos.sql
├── MANIFEST.md
└── SHA256SUMS.txt
```

El verificador restaura el dump completo y luego estructura más datos. Compara
tablas, columnas, metadatos de claves, CHECK, reglas referenciales, índices,
intercalación, conteos y checksum de datos. Para respaldos del contrato vigente
exige:

- 48 tablas exactas, incluida `tbpersona`, históricos de teléfono, clasificación histórica del
  Productor, animal, publicación, compra, venta, funnel, carrito y transporte;
- una PRIMARY KEY por tabla (48) y ninguna otra restricción;
- cero FOREIGN KEY y CHECK;
- cero índices fuera de la llave primaria;
- cero columnas `AUTO_INCREMENT`;
- cero valores `DEFAULT`, columnas generadas, triggers, rutinas y eventos;
- `utf8mb4/utf8mb4_unicode_ci` en base y tablas;
- igualdad entre restauración completa y restauración por partes.

La consulta funcional del verificador une `tbproductor` con `tbpersona`; así
detecta respaldos antiguos que todavía guarden identidad duplicada en el
perfil. La comparación de metadatos es bidireccional entre restauración
completa y estructura más datos. Un fallo SQL o cualquier salida por stderr
invalida la ejecución y nunca se interpreta como cero diferencias.

Para respaldos legados con prefijo `dbmercadoganadero`, el verificador conserva
el archivo original y compara los datos restaurados del dump completo contra
estructura más datos. No exige que esos datos históricos sean idénticos a la
base operativa actual `bdmercadoganadero`.

Antes de migrar una base existente se genera el respaldo y se ejecuta la
restauración completa. La migración de persona debe abortar sin retirar
columnas si detecta identificaciones repetidas o datos incompatibles. El
respaldo previo es el punto de reversión. Supabase requiere además snapshot y
autorización explícita; estos comandos no aplican cambios remotos.

Antes de cada verificación el manifiesto vuelve a estado `Pendiente`. Solo pasa
a `APROBADO` después de completar todas las comparaciones. Las pruebas negativas
inyectan una consulta inválida y una diferencia real de metadatos; ambas deben
terminar con error, conservar el manifiesto pendiente y limpiar las bases
temporales.

```bash
RESTORE_TEST_INJECT_INVALID_METADATA=1 Tools/test-restore.sh AvanceNN
RESTORE_TEST_INJECT_SCHEMA_DIFFERENCE=1 Tools/test-restore.sh AvanceNN
Tools/test-restore.sh Avance01Correccion04
```

Los tres SQL se verifican con `sha256sum -c SHA256SUMS.txt`. Nunca se incluyen
credenciales ni datos reales.
