[CmdletBinding()]
param(
    [Parameter(Mandatory = $true, Position = 0)]
    [ValidatePattern('^Avance[0-9]{2}(Correccion[0-9]{2})?$')]
    [string]$Avance
)

$ErrorActionPreference = 'Stop'
$SourceDatabase = 'dbtindercows'
$RestoreDatabase = 'dbtindercows_restore_test'
$PartsDatabase = 'dbtindercows_restore_parts_test'
$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$AdvanceMatch = [regex]::Match($Avance, '^Avance(?<avance>[0-9]{2})(Correccion(?<correccion>[0-9]{2}))?$')
$AdvanceNumber = $AdvanceMatch.Groups['avance'].Value
$CorrectionNumber = $AdvanceMatch.Groups['correccion'].Value
$AdvanceSlug = "avance$AdvanceNumber"
if ($CorrectionNumber) { $AdvanceSlug += "_correccion$CorrectionNumber" }
$BackupDirectory = Join-Path $ProjectRoot "Database/Backups/$Avance"
$CompleteFile = Join-Path $BackupDirectory "${SourceDatabase}_${AdvanceSlug}_completo.sql"
$SchemaFile = Join-Path $BackupDirectory "${SourceDatabase}_${AdvanceSlug}_estructura.sql"
$DataFile = Join-Path $BackupDirectory "${SourceDatabase}_${AdvanceSlug}_datos.sql"
$ChecksumsFile = Join-Path $BackupDirectory 'SHA256SUMS.txt'
$ManifestFile = Join-Path $BackupDirectory 'MANIFEST.md'

function Invoke-MySqlQuery([string]$Sql) {
    $result = & docker compose exec -T -e "CHECK_SQL=$Sql" db sh -c 'exec mysql -N -B -uroot -p"$MYSQL_ROOT_PASSWORD" -e "$CHECK_SQL"'
    if ($LASTEXITCODE -ne 0) { throw 'Falló una consulta de comprobación MySQL.' }
    return ($result -join "`n").Trim()
}

$RestoreCreated = $false
$PartsCreated = $false
Push-Location $ProjectRoot
try {
    foreach ($requiredDump in @($CompleteFile, $SchemaFile, $DataFile)) {
        if (-not (Test-Path $requiredDump) -or (Get-Item $requiredDump).Length -eq 0) { throw "Falta un respaldo: $requiredDump" }
    }
    if (-not (Test-Path $ChecksumsFile) -or (Get-Item $ChecksumsFile).Length -eq 0) { throw "Faltan las sumas: $ChecksumsFile" }
    if (-not (Test-Path $ManifestFile) -or (Get-Item $ManifestFile).Length -eq 0) { throw "Falta el manifiesto: $ManifestFile" }

    Get-Content $ChecksumsFile | ForEach-Object {
        $parts = $_ -split '  ', 2
        if ($parts.Count -ne 2) { throw "Línea SHA-256 inválida: $_" }
        $actual = (Get-FileHash -Algorithm SHA256 (Join-Path $BackupDirectory $parts[1])).Hash.ToLowerInvariant()
        if ($actual -ne $parts[0].ToLowerInvariant()) { throw "Falló la suma SHA-256 de $($parts[1])." }
        Write-Host "$($parts[1]): CORRECTO"
    }

    & docker compose config --quiet
    if ($LASTEXITCODE -ne 0) { throw 'compose.yaml no es válido.' }
    & docker compose exec -T db sh -c 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'El servicio db no está disponible.' }

    $restoreExists = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$RestoreDatabase';"
    if ($restoreExists -ne '0') { throw "$RestoreDatabase ya existe; no se eliminará una base temporal ajena." }
    $partsExists = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$PartsDatabase';"
    if ($partsExists -ne '0') { throw "$PartsDatabase ya existe; no se eliminará una base temporal ajena." }
    Invoke-MySqlQuery "CREATE DATABASE $RestoreDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
    $RestoreCreated = $true
    Get-Content -Raw $CompleteFile | & docker compose exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" dbtindercows_restore_test'
    if ($LASTEXITCODE -ne 0) { throw 'Falló la restauración del respaldo completo.' }
    Invoke-MySqlQuery "CREATE DATABASE $PartsDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
    $PartsCreated = $true
    Get-Content -Raw $SchemaFile | & docker compose exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" dbtindercows_restore_parts_test'
    if ($LASTEXITCODE -ne 0) { throw 'Falló la restauración del respaldo de estructura.' }
    Get-Content -Raw $DataFile | & docker compose exec -T db sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" dbtindercows_restore_parts_test'
    if ($LASTEXITCODE -ne 0) { throw 'Falló la restauración del respaldo de datos.' }

    $TableDiff = Invoke-MySqlQuery "SELECT COUNT(*) FROM (SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SourceDatabase' AND TABLE_TYPE='BASE TABLE' UNION ALL SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$RestoreDatabase' AND TABLE_TYPE='BASE TABLE') x GROUP BY TABLE_NAME HAVING COUNT(*)<>2;"
    $ConstraintDiff = Invoke-MySqlQuery "SELECT COUNT(*) FROM (SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$SourceDatabase' UNION ALL SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$RestoreDatabase') x GROUP BY TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE HAVING COUNT(*)<>2;"
    $IndexDiff = Invoke-MySqlQuery "SELECT COUNT(*) FROM (SELECT TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$SourceDatabase' UNION ALL SELECT TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$RestoreDatabase') x GROUP BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE,INDEX_TYPE HAVING COUNT(*)<>2;"
    if ($TableDiff -or $ConstraintDiff -or $IndexDiff) { throw 'La estructura restaurada difiere del origen.' }
    $PartsTableDiff = Invoke-MySqlQuery "SELECT COUNT(*) FROM (SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$RestoreDatabase' AND TABLE_TYPE='BASE TABLE' UNION ALL SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$PartsDatabase' AND TABLE_TYPE='BASE TABLE') x GROUP BY TABLE_NAME HAVING COUNT(*)<>2;"
    $PartsConstraintDiff = Invoke-MySqlQuery "SELECT COUNT(*) FROM (SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$RestoreDatabase' UNION ALL SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$PartsDatabase') x GROUP BY TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE HAVING COUNT(*)<>2;"
    $PartsIndexDiff = Invoke-MySqlQuery "SELECT COUNT(*) FROM (SELECT TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$RestoreDatabase' UNION ALL SELECT TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$PartsDatabase') x GROUP BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE,INDEX_TYPE HAVING COUNT(*)<>2;"
    if ($PartsTableDiff -or $PartsConstraintDiff -or $PartsIndexDiff) { throw 'Estructura+datos difiere del respaldo completo.' }

    $Tables = (Invoke-MySqlQuery "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SourceDatabase' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;") -split "`n"
    foreach ($table in $Tables) {
        $sourceCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM $SourceDatabase.$table;"
        $restoreCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM $RestoreDatabase.$table;"
        $partsCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM $PartsDatabase.$table;"
        Write-Host ("{0,-38} origen={1} completo={2} partes={3}" -f $table, $sourceCount, $restoreCount, $partsCount)
        if ($sourceCount -ne $restoreCount -or $restoreCount -ne $partsCount) { throw "El conteo difiere para $table." }
    }

    if ($Tables -contains 'tbproductores') {
        Invoke-MySqlQuery "SELECT tbproductoresIdentificacionNumero FROM $RestoreDatabase.tbproductores ORDER BY tbproductoresIdentificacionNumero LIMIT 1;" | Out-Null
    }
    else {
        Invoke-MySqlQuery "SELECT p.tbparticipanteId FROM $RestoreDatabase.tbparticipante p INNER JOIN $RestoreDatabase.tbparticipanterol pr ON pr.tbparticipanteId=p.tbparticipanteId INNER JOIN $RestoreDatabase.tbrol r ON r.tbrolId=pr.tbrolId WHERE r.tbrolCodigo='PRODUCTOR' LIMIT 1;" | Out-Null
    }
    $tableCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SourceDatabase' AND TABLE_TYPE='BASE TABLE';"
    $constraintCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$SourceDatabase';"
    $indexCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$SourceDatabase';"
    $manifest = [IO.File]::ReadAllText($ManifestFile)
    $manifest = $manifest -replace '(?m)^- Restauración probada: .*$', '- Restauración probada: Sí'
    $manifest = $manifest -replace '(?m)^- Resultado de integridad: .*$', '- Resultado de integridad: Correcto'
    $manifest = $manifest -replace '(?m)^- Observaciones: .*$', "- Observaciones: Restauración completa y estructura+datos verificadas; tablas=$tableCount, restricciones=$constraintCount, filas de índice=$indexCount."
    [IO.File]::WriteAllText($ManifestFile, $manifest, [Text.UTF8Encoding]::new($false))
    Write-Host "Restauración correcta: tablas=$tableCount, restricciones=$constraintCount."
}
finally {
    if ($RestoreCreated) {
        try { Invoke-MySqlQuery "DROP DATABASE IF EXISTS $RestoreDatabase;" | Out-Null } catch { Write-Warning 'No se pudo eliminar la base temporal.' }
    }
    if ($PartsCreated) {
        try { Invoke-MySqlQuery "DROP DATABASE IF EXISTS $PartsDatabase;" | Out-Null } catch { Write-Warning 'No se pudo eliminar la base temporal de partes.' }
    }
    Pop-Location
}
