# Respaldos versionados y restauración

## Entrega vigente

Corrección 03 no modifica `Avance01/`, `Avance01Correccion01/` ni
`Avance01Correccion02/`. El paquete vigente se genera en
`Database/Backups/Avance01Correccion03/` y corresponde a la etiqueta
`avance-01-correccion-03`.

```bash
Tools/backup-database.sh Avance01Correccion03 Dilan
Tools/test-restore.sh Avance01Correccion03
```

PowerShell ofrece el mismo contrato:

```powershell
Tools/Backup-Database.ps1 -Avance Avance01Correccion03 -Responsable Dilan
Tools/Test-Restore.ps1 -Avance Avance01Correccion03
```

## Paquete inmutable

```text
Database/Backups/Avance01Correccion03/
├── dbtindercows_avance01_correccion03_completo.sql
├── dbtindercows_avance01_correccion03_estructura.sql
├── dbtindercows_avance01_correccion03_datos.sql
├── MANIFEST.md
└── SHA256SUMS.txt
```

- Completo contiene estructura y datos.
- Estructura contiene tablas, la única PK, CHECK e índices ordinarios; no contiene FK.
- Datos contiene las filas sin instrucciones de creación.
- El manifiesto identifica proyecto, base, entrega, fecha, MySQL, rama, commit
  candidato, responsable, archivos, intercalación, restauraciones, cantidades y
  resultado final.
- `SHA256SUMS.txt` identifica los tres SQL mediante SHA-256.

Los generadores aceptan `AvanceNN[CorreccionNN]` y rechazan sobrescribir un
archivo existente. El árbol Git debe estar limpio para que el manifiesto apunte
al commit candidato exacto. Las escrituras MySQL se congelan durante los dumps y
el estado del servidor se restaura incluso si ocurre un error.

## Verificación de restauración

El verificador crea solo estas bases temporales:

- `dbtindercows_restore_test`, para el respaldo completo;
- `dbtindercows_restore_parts_test`, para estructura seguida de datos.

Antes de crearlas comprueba que no existan, por lo que nunca elimina una base
temporal ajena. Al terminar las elimina mediante limpieza automática. Compara
origen, restauración completa y restauración por partes en:

- tablas, motor e intercalación;
- definición y orden de columnas;
- la única PRIMARY KEY y la ausencia total de FOREIGN KEY;
- cláusulas CHECK;
- índices, orden, unicidad y tipo;
- cantidad de registros y checksum de datos por tabla;
- intercalación de la base y de cada tabla.

Para Corrección 03 el resultado válido requiere exactamente cuatro tablas,
`utf8mb4/utf8mb4_unicode_ci`, una PRIMARY KEY en `tbproductores` y cero FOREIGN KEY.

## Comprobación manual de SHA-256

```bash
cd Database/Backups/Avance01Correccion03
sha256sum -c SHA256SUMS.txt
cd ../../..
```

## Secuencia de cierre

1. Reconstruir con `docker compose down -v` y `docker compose up --build -d`.
2. Ejecutar todas las pruebas y consultas de evidencia.
3. Reconstruir nuevamente para excluir residuos de prueba.
4. Crear el commit candidato de código y documentación.
5. Generar el paquete Corrección 03.
6. Restaurar completo y estructura + datos.
7. Registrar la evidencia real.
8. Crear el commit del respaldo.
9. Crear y subir la etiqueta sin mover etiquetas históricas.

Nunca se incluyen `.env`, credenciales ni datos reales. La semilla usa datos
académicos ficticios y dominios `example.test`.
