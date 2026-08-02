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

function Compare-MySqlMetadata([string]$LeftDatabase, [string]$RightDatabase, [string]$Label) {
    $definitions = @(
        @{ Schema = 'TABLE_SCHEMA'; Table = 'TABLES'; Columns = 'TABLE_NAME,ENGINE,TABLE_COLLATION'; Filter = "AND TABLE_TYPE='BASE TABLE'" },
        @{ Schema = 'TABLE_SCHEMA'; Table = 'COLUMNS'; Columns = "TABLE_NAME,ORDINAL_POSITION,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COALESCE(COLUMN_DEFAULT,'<NULL>'),EXTRA,COALESCE(GENERATION_EXPRESSION,''),COALESCE(CHARACTER_SET_NAME,''),COALESCE(COLLATION_NAME,'')"; Filter = '' },
        @{ Schema = 'CONSTRAINT_SCHEMA'; Table = 'TABLE_CONSTRAINTS'; Columns = 'TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE'; Filter = '' },
        @{ Schema = 'CONSTRAINT_SCHEMA'; Table = 'KEY_COLUMN_USAGE'; Columns = "TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION,COLUMN_NAME,COALESCE(REFERENCED_TABLE_NAME,''),COALESCE(REFERENCED_COLUMN_NAME,'')"; Filter = '' },
        @{ Schema = 'CONSTRAINT_SCHEMA'; Table = 'CHECK_CONSTRAINTS'; Columns = 'CONSTRAINT_NAME,CHECK_CLAUSE'; Filter = '' },
        @{ Schema = 'CONSTRAINT_SCHEMA'; Table = 'REFERENTIAL_CONSTRAINTS'; Columns = 'TABLE_NAME,CONSTRAINT_NAME,UNIQUE_CONSTRAINT_NAME,MATCH_OPTION,UPDATE_RULE,DELETE_RULE'; Filter = '' },
        @{ Schema = 'TABLE_SCHEMA'; Table = 'STATISTICS'; Columns = 'TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,NON_UNIQUE,INDEX_TYPE'; Filter = '' }
    )
    foreach ($definition in $definitions) {
        $sql = "SELECT COUNT(*) FROM (SELECT CONCAT_WS(CHAR(31),$($definition.Columns)) AS signature FROM information_schema.$($definition.Table) WHERE $($definition.Schema)='$LeftDatabase' $($definition.Filter) UNION ALL SELECT CONCAT_WS(CHAR(31),$($definition.Columns)) AS signature FROM information_schema.$($definition.Table) WHERE $($definition.Schema)='$RightDatabase' $($definition.Filter)) compared GROUP BY signature HAVING COUNT(*)<>2;"
        if (Invoke-MySqlQuery $sql) { throw "Diferencia $Label en $($definition.Table)." }
    }
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

    Compare-MySqlMetadata $SourceDatabase $RestoreDatabase 'origen/completo'
    Compare-MySqlMetadata $RestoreDatabase $PartsDatabase 'completo/estructura+datos'
    foreach ($database in @($SourceDatabase, $RestoreDatabase, $PartsDatabase)) {
        $collation = Invoke-MySqlQuery "SELECT CONCAT(DEFAULT_CHARACTER_SET_NAME,'/',DEFAULT_COLLATION_NAME) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$database';"
        if ($collation -ne 'utf8mb4/utf8mb4_unicode_ci') { throw "$database usa $collation." }
        $invalidTables = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$database' AND TABLE_TYPE='BASE TABLE' AND TABLE_COLLATION<>'utf8mb4_unicode_ci';"
        if ($invalidTables -ne '0') { throw "$database contiene tablas con intercalación incorrecta." }
        $primaryKey = Invoke-MySqlQuery "SELECT CONCAT(TABLE_NAME,'.',CONSTRAINT_NAME) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$database' AND CONSTRAINT_TYPE='PRIMARY KEY';"
        if ($primaryKey -ne 'tbproductores.PRIMARY') { throw "$database debe tener una única PRIMARY KEY en tbproductores." }
        $foreignKeyCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$database' AND CONSTRAINT_TYPE='FOREIGN KEY';"
        if ($foreignKeyCount -ne '0') { throw "$database contiene $foreignKeyCount FOREIGN KEY." }
    }

    $Tables = (Invoke-MySqlQuery "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SourceDatabase' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;") -split "`n"
    foreach ($table in $Tables) {
        $sourceCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM $SourceDatabase.$table;"
        $restoreCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM $RestoreDatabase.$table;"
        $partsCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM $PartsDatabase.$table;"
        Write-Host ("{0,-38} origen={1} completo={2} partes={3}" -f $table, $sourceCount, $restoreCount, $partsCount)
        if ($sourceCount -ne $restoreCount -or $restoreCount -ne $partsCount) { throw "El conteo difiere para $table." }
        $sourceChecksum = (Invoke-MySqlQuery "CHECKSUM TABLE $SourceDatabase.$table;") -split "\s+" | Select-Object -Last 1
        $restoreChecksum = (Invoke-MySqlQuery "CHECKSUM TABLE $RestoreDatabase.$table;") -split "\s+" | Select-Object -Last 1
        $partsChecksum = (Invoke-MySqlQuery "CHECKSUM TABLE $PartsDatabase.$table;") -split "\s+" | Select-Object -Last 1
        if ($sourceChecksum -ne $restoreChecksum -or $restoreChecksum -ne $partsChecksum) { throw "Los datos difieren para $table." }
    }

    if ($Tables -contains 'tbproductores') {
        Invoke-MySqlQuery "SELECT tbproductoresIdentificacionNumero FROM $RestoreDatabase.tbproductores ORDER BY tbproductoresIdentificacionNumero LIMIT 1;" | Out-Null
    }
    else {
        Invoke-MySqlQuery "SELECT p.tbparticipanteId FROM $RestoreDatabase.tbparticipante p INNER JOIN $RestoreDatabase.tbparticipanterol pr ON pr.tbparticipanteId=p.tbparticipanteId INNER JOIN $RestoreDatabase.tbrol r ON r.tbrolId=pr.tbrolId WHERE r.tbrolCodigo='PRODUCTOR' LIMIT 1;" | Out-Null
    }
    $tableCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SourceDatabase' AND TABLE_TYPE='BASE TABLE';"
    $constraintCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$SourceDatabase';"
    $indexCount = Invoke-MySqlQuery "SELECT COUNT(DISTINCT TABLE_NAME,INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$SourceDatabase';"
    $primaryKeyCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$SourceDatabase' AND CONSTRAINT_TYPE='PRIMARY KEY';"
    $foreignKeyCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$SourceDatabase' AND CONSTRAINT_TYPE='FOREIGN KEY';"
    $manifest = [IO.File]::ReadAllText($ManifestFile)
    $manifest = $manifest -replace '(?m)^- Intercalación comprobada: .*$', '- Intercalación comprobada: utf8mb4/utf8mb4_unicode_ci en base y cuatro tablas'
    $manifest = $manifest -replace '(?m)^- Restauración completa comprobada: .*$', '- Restauración completa comprobada: Sí'
    $manifest = $manifest -replace '(?m)^- Restauración estructura \+ datos comprobada: .*$', '- Restauración estructura + datos comprobada: Sí'
    $manifest = $manifest -replace '(?m)^- Cantidad de tablas: .*$', "- Cantidad de tablas: $tableCount"
    $manifest = $manifest -replace '(?m)^- Cantidad de restricciones: .*$', "- Cantidad de restricciones: $constraintCount"
    $manifest = $manifest -replace '(?m)^- Cantidad de índices: .*$', "- Cantidad de índices: $indexCount"
    $manifest = $manifest -replace '(?m)^- Cantidad de PRIMARY KEY: .*$', "- Cantidad de PRIMARY KEY: $primaryKeyCount"
    $manifest = $manifest -replace '(?m)^- Cantidad de FOREIGN KEY: .*$', "- Cantidad de FOREIGN KEY: $foreignKeyCount"
    $manifest = $manifest -replace '(?m)^- Resultado final: .*$', '- Resultado final: APROBADO'
    $manifest = $manifest -replace '(?m)^- Observaciones: .*$', '- Observaciones: Estructura, datos, única PK de productores, cero FK, CHECK, índices, intercalación y conteos sin diferencias.'
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
