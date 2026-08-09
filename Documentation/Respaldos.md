# Respaldos versionados y restauración

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
├── dbtindercows_avance01_correccion04_completo.sql
├── dbtindercows_avance01_correccion04_estructura.sql
├── dbtindercows_avance01_correccion04_datos.sql
├── MANIFEST.md
└── SHA256SUMS.txt
```

El verificador restaura el dump completo y luego estructura más datos. Compara
tablas, columnas, metadatos de claves, CHECK, reglas referenciales, índices,
intercalación, conteos y checksum de datos. Para Corrección 04 exige:

- cuatro tablas singulares exactas;
- cero restricciones totales;
- cero PRIMARY KEY, FOREIGN KEY y CHECK;
- cero índices;
- cero columnas `AUTO_INCREMENT`;
- `utf8mb4/utf8mb4_unicode_ci` en base y tablas;
- igualdad entre origen, restauración completa y restauración por partes.

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
