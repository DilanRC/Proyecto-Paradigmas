# Respaldos versionados y restauración

## Objetivo

Una entrega es reproducible cuando el commit identifica el código y el paquete
SQL identifica el estado persistente que ese código produjo. Un `.sql` sin
manifiesto, suma y restauración no es un respaldo comprobado.

## Paquete inmutable

```text
Database/Backups/AvanceNN/
├── dbtindercows_avanceNN_completo.sql
├── dbtindercows_avanceNN_estructura.sql
├── dbtindercows_avanceNN_datos.sql
├── MANIFEST.md
└── SHA256SUMS.txt
```

- Completo: estructura y filas; es el insumo de restauración.
- Estructura: tablas, claves, restricciones, índices, rutinas, disparadores y
  eventos existentes.
- Datos: filas sin instrucciones de creación ni disparadores.
- Manifiesto: procedencia y estado de verificación.
- SHA-256: identidad de los tres archivos SQL.

No se sobrescribe un paquete previo. Una corrección formal usa una carpeta como
`Avance01Correccion01` y otra etiqueta.

## Precondiciones

1. Docker y MySQL están saludables.
2. Las pruebas del commit candidato están registradas.
3. No hay cambios funcionales después de congelar el candidato.
4. `git rev-parse HEAD` devuelve el SHA que se anotará.
5. La base contiene solo datos académicos ficticios.
6. `.env`, secretos y usuarios internos de MySQL quedan fuera del respaldo.

## Generación automatizada

Linux/Git Bash:

```bash
Tools/backup-database.sh Avance01 "Nombre responsable"
```

PowerShell:

```powershell
Tools/Backup-Database.ps1 -Avance Avance01 -Responsable "Nombre responsable"
```

El argumento debe cumplir `AvanceNN`. Los scripts:

1. rechazan sobrescribir cualquier archivo destino;
2. validan Compose y disponibilidad de `db`;
3. leen rama, commit candidato y versión real de MySQL;
4. ejecutan `mysqldump` dentro del contenedor, usando
   `MYSQL_ROOT_PASSWORD` sin escribirla en el repositorio;
5. activan temporalmente `read_only` y `super_read_only` para que los tres dumps
   describan el mismo estado y restauran siempre el estado anterior al terminar;
6. comprueban que cada SQL sea no vacío y tenga encabezado de MySQL dump;
7. crean `MANIFEST.md` con restauración pendiente;
8. generan y verifican `SHA256SUMS.txt`.

Verificación manual en Linux/Git Bash:

```bash
cd Database/Backups/Avance01
sha256sum -c SHA256SUMS.txt
cd ../../..
```

En PowerShell:

```powershell
$folder = "Database/Backups/Avance01"
Get-Content "$folder/SHA256SUMS.txt" | ForEach-Object {
    $parts = $_ -split '  ', 2
    $actual = (Get-FileHash -Algorithm SHA256 "$folder/$($parts[1])").Hash.ToLower()
    if ($actual -ne $parts[0].ToLower()) { throw "SHA-256 incorrecto: $($parts[1])" }
}
```

## Restauración obligatoria

Linux/Git Bash:

```bash
Tools/test-restore.sh Avance01
```

PowerShell:

```powershell
Tools/Test-Restore.ps1 -Avance Avance01
```

Los scripts restauran el dump completo en `dbtindercows_restore_test` y el par
estructura+datos en `dbtindercows_restore_parts_test`, nunca sobre
`dbtindercows`. Comparan:

- conjunto de tablas;
- nombre y tipo de restricciones PK, FK y UK;
- índices, secuencia de columnas, unicidad y tipo;
- cantidad de filas en las nueve tablas;
- equivalencia entre el respaldo completo y la reconstrucción estructura+datos;
- una consulta funcional de participantes con rol `PRODUCTOR`.

La base temporal se elimina mediante limpieza final aun si una comprobación
falla. Antes de ejecutar manualmente un `DROP DATABASE`, confirme que el nombre
es exactamente `dbtindercows_restore_test`.

## Manifiesto mínimo

```markdown
# Respaldo — AvanceNN

- Proyecto: TinderCows
- Base de datos: dbtindercows
- Entrega: AvanceNN
- Fecha y hora: PENDIENTE
- Motor: MySQL PENDIENTE
- Rama: PENDIENTE
- Commit candidato de código: PENDIENTE
- Etiqueta oficial: avance-NN
- Responsable de exportación: PENDIENTE
- Archivo completo: dbtindercows_avanceNN_completo.sql
- Archivo de estructura: dbtindercows_avanceNN_estructura.sql
- Archivo de datos: dbtindercows_avanceNN_datos.sql
- Restauración probada: Pendiente
- Base temporal utilizada: dbtindercows_restore_test
- Resultado de integridad: Pendiente
- Observaciones: PENDIENTE
```

Después de una restauración real, cambie “Pendiente” por el resultado exacto y
registre la salida en `Documentation/EvidenciasPruebas.md`. No anticipe el
resultado.

## Cierre Git

```bash
git status
git add .
git commit -m "Preparar código candidato del Avance 01"
git rev-parse HEAD
```

Genere y restaure el respaldo, complete evidencias y luego:

```bash
git add Database/Backups/Avance01 Documentation
git commit -m "Agregar respaldo verificado del Avance 01"
git tag -a avance-01 -m "Entrega oficial del Avance 01"
git push origin HEAD
git push origin avance-01
```

La etiqueta apunta al paquete final; el manifiesto apunta al commit candidato
que produjo el estado. Confirme la etiqueta sin inventar evidencia:

```bash
git show-ref --tags avance-01
git tag -n --list avance-01
```

## Lista de cierre

- [ ] Los tres SQL existen y no están vacíos.
- [ ] Los nombres usan `dbtindercows_avanceNN_<tipo>.sql`.
- [ ] El manifiesto contiene commit completo, responsable, hora y MySQL real.
- [ ] `sha256sum -c` o su equivalente pasó.
- [ ] Se restauró el archivo completo en la base temporal.
- [ ] Tablas, restricciones, índices y conteos coinciden.
- [ ] Se ejecutó la consulta funcional.
- [ ] La salida real está en `EvidenciasPruebas.md`.
- [ ] La base temporal se eliminó después de registrar el resultado.
- [ ] No hay secretos ni datos personales reales.
- [ ] El commit final solo agrega respaldo/evidencia al candidato probado.
- [ ] La etiqueta anotada existe y apunta al paquete final.
- [ ] La rama y la etiqueta se subieron.
