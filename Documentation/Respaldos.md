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
en las bases temporales del verificador: 15 tablas, cero restricciones y cero
índices, sin diferencias de conteo. Los respaldos históricos no fueron
modificados. Este checkpoint todavía pertenece al nombre legado
`dbmercadoganadero`; el verificador lo conserva como respaldo histórico y no
reescribe su `MANIFEST.md` ni sus sumas SHA256. Los dumps quedan fuera del
control de versiones por la política de `.gitignore`; no se deben sustituir ni
regenerar sin crear un checkpoint nuevo.

## Entrega vigente

Corrección 04 no modifica los respaldos históricos hasta Corrección 03. El
paquete vigente se genera en `Database/Backups/Avance01Correccion04/` y usa la
etiqueta `avance-01-correccion-04`.

```bash
Tools/backup-database.sh Avance01Correccion04 Dilan
Tools/test-restore.sh Avance01Correccion04
```

PowerShell dispone de los comandos equivalentes.

## Contenido

```text
Database/Backups/Avance01Correccion04/
├── dbtindervacas_avance01_correccion04_completo.sql
├── dbtindervacas_avance01_correccion04_estructura.sql
├── dbtindervacas_avance01_correccion04_datos.sql
├── MANIFEST.md
└── SHA256SUMS.txt
```

El verificador restaura el dump completo y luego estructura más datos. Compara
tablas, columnas, metadatos de claves, CHECK, reglas referenciales, índices,
intercalación, conteos y checksum de datos. Para Corrección 04 exige:

- quince tablas exactas, incluida `tbpersona`, los tres perfiles de capacidad,
  las tablas históricas de productor y las tablas de dirección, pago y
  transporte;
- cero restricciones totales;
- cero PRIMARY KEY, FOREIGN KEY y CHECK;
- cero índices;
- cero columnas `AUTO_INCREMENT`;
- cero valores `DEFAULT`, columnas generadas, triggers, rutinas y eventos;
- `utf8mb4/utf8mb4_unicode_ci` en base y tablas;
- igualdad entre origen, restauración completa y restauración por partes.

La consulta funcional del verificador une `tbproductor` con `tbpersona`; así
detecta respaldos antiguos que todavía guarden identidad duplicada en el
perfil. La comparación de metadatos es bidireccional: origen contra
restauración completa y restauración completa contra estructura más datos. Un
fallo SQL o cualquier salida por stderr invalida la ejecución y nunca se
interpreta como cero diferencias.

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
RESTORE_TEST_INJECT_INVALID_METADATA=1 Tools/test-restore.sh Avance01Correccion04
RESTORE_TEST_INJECT_SCHEMA_DIFFERENCE=1 Tools/test-restore.sh Avance01Correccion04
Tools/test-restore.sh Avance01Correccion04
```

Los tres SQL se verifican con `sha256sum -c SHA256SUMS.txt`. Nunca se incluyen
credenciales ni datos reales.
