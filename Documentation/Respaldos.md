# Respaldos versionados y restauración

## Entrega vigente

Corrección 02 no modifica los paquetes históricos `Avance01/` y
`Avance01Correccion01/`. El paquete vigente se genera en
`Database/Backups/Avance01Correccion02/` y corresponde a la etiqueta
`avance-01-correccion-02`.

```bash
Tools/backup-database.sh Avance01Correccion02 Dilan
Tools/test-restore.sh Avance01Correccion02
```

PowerShell ofrece el mismo contrato:

```powershell
Tools/Backup-Database.ps1 -Avance Avance01Correccion02 -Responsable Dilan
Tools/Test-Restore.ps1 -Avance Avance01Correccion02
```

## Paquete inmutable

```text
Database/Backups/Avance01Correccion02/
├── dbtindercows_avance01_correccion02_completo.sql
├── dbtindercows_avance01_correccion02_estructura.sql
├── dbtindercows_avance01_correccion02_datos.sql
├── MANIFEST.md
└── SHA256SUMS.txt
```

- Completo contiene estructura y datos.
- Estructura contiene tablas, PK, FK, CHECK e índices.
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
- PRIMARY KEY, FOREIGN KEY y columnas referenciadas;
- cláusulas CHECK;
- reglas `ON UPDATE` y `ON DELETE`;
- índices, orden, unicidad y tipo;
- cantidad de registros y checksum de datos por tabla;
- intercalación de la base y de cada tabla.

Para Corrección 02 el resultado válido requiere exactamente cuatro tablas,
`utf8mb4/utf8mb4_unicode_ci` y las dos FK con `RESTRICT/RESTRICT`.

## Comprobación manual de SHA-256

```bash
cd Database/Backups/Avance01Correccion02
sha256sum -c SHA256SUMS.txt
cd ../../..
```

## Secuencia de cierre

1. Reconstruir con `docker compose down -v` y `docker compose up --build -d`.
2. Ejecutar todas las pruebas y consultas de evidencia.
3. Reconstruir nuevamente para excluir residuos de prueba.
4. Crear el commit candidato de código y documentación.
5. Generar el paquete Corrección 02.
6. Restaurar completo y estructura + datos.
7. Registrar la evidencia real.
8. Crear el commit del respaldo.
9. Crear y subir la etiqueta sin mover etiquetas históricas.

Nunca se incluyen `.env`, credenciales ni datos reales. La semilla usa datos
académicos ficticios y dominios `example.test`.
