[CmdletBinding()]
param(
    [Parameter(Mandatory = $true, Position = 0)]
    [ValidatePattern('^Avance[0-9]{2}(Correccion[0-9]{2})?$')]
    [string]$Avance,
    [switch]$InjectInvalidMetadata,
    [switch]$InjectSchemaDifference
)

$ErrorActionPreference = 'Stop'
$SourceDatabase = 'bdmercadoganadero'
$RestoreDatabase = 'bdmercadoganadero_restore_test'
$PartsDatabase = 'bdmercadoganadero_restore_parts_test'
$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$AdvanceMatch = [regex]::Match($Avance, '^Avance(?<avance>[0-9]{2})(Correccion(?<correccion>[0-9]{2}))?$')
$AdvanceNumber = $AdvanceMatch.Groups['avance'].Value
$CorrectionNumber = $AdvanceMatch.Groups['correccion'].Value
$AdvanceSlug = "avance$AdvanceNumber"
if ($CorrectionNumber) { $AdvanceSlug += "_correccion$CorrectionNumber" }
$BackupDirectory = Join-Path $ProjectRoot "Database/Backups/$Avance"
$ChecksumsFile = Join-Path $BackupDirectory 'SHA256SUMS.txt'
$ManifestFile = Join-Path $BackupDirectory 'MANIFEST.md'
$BackupDatabase = $null
foreach ($candidateDatabase in @($SourceDatabase, 'dbmercadoganadero')) {
    $candidateCompleteFile = Join-Path $BackupDirectory "${candidateDatabase}_${AdvanceSlug}_completo.sql"
    $candidateSchemaFile = Join-Path $BackupDirectory "${candidateDatabase}_${AdvanceSlug}_estructura.sql"
    $candidateDataFile = Join-Path $BackupDirectory "${candidateDatabase}_${AdvanceSlug}_datos.sql"
    if ((Test-Path $candidateCompleteFile) -and (Test-Path $candidateSchemaFile) -and (Test-Path $candidateDataFile)) {
        $BackupDatabase = $candidateDatabase
        $CompleteFile = $candidateCompleteFile
        $SchemaFile = $candidateSchemaFile
        $DataFile = $candidateDataFile
        break
    }
}

function Invoke-MySqlQuery([string]$Sql) {
    $result = & docker compose exec -T -e "CHECK_SQL=$Sql" db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -N -B -uroot -e "$CHECK_SQL"' 2>&1
    $nativeErrors = @($result | Where-Object { $_ -is [System.Management.Automation.ErrorRecord] })
    if ($LASTEXITCODE -ne 0 -or $nativeErrors.Count -gt 0) {
        throw "Falló una consulta de comprobación MySQL: $($result -join "`n")"
    }
    return ($result -join "`n").Trim()
}

function Compare-MySqlMetadata([string]$LeftDatabase, [string]$RightDatabase, [string]$Label) {
    $tablesMetadata = if ($InjectInvalidMetadata) { 'TABLES_INVALIDA' } else { 'TABLES' }
    $definitions = @(
        @{ Schema = 'TABLE_SCHEMA'; Table = $tablesMetadata; Columns = 'TABLE_NAME,ENGINE,TABLE_COLLATION'; Filter = "AND TABLE_TYPE='BASE TABLE'" },
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
    if (-not $BackupDatabase) { throw "Faltan respaldos para $SourceDatabase o dbmercadoganadero en $BackupDirectory" }
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
    & docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin ping -h 127.0.0.1 -uroot --silent' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'El servicio db no está disponible.' }
    $ExpectedTablesCsv = (& php Tools/schema-manifest.php --format=csv).Trim()

    $restoreExists = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$RestoreDatabase';"
    if ($restoreExists -ne '0') { throw "$RestoreDatabase ya existe; no se eliminará una base temporal ajena." }
    $partsExists = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$PartsDatabase';"
    if ($partsExists -ne '0') { throw "$PartsDatabase ya existe; no se eliminará una base temporal ajena." }
    Invoke-MySqlQuery "CREATE DATABASE $RestoreDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
    $RestoreCreated = $true
    Get-Content -Raw $CompleteFile | & docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot bdmercadoganadero_restore_test'
    if ($LASTEXITCODE -ne 0) { throw 'Falló la restauración del respaldo completo.' }
    Invoke-MySqlQuery "CREATE DATABASE $PartsDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" | Out-Null
    $PartsCreated = $true
    Get-Content -Raw $SchemaFile | & docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot bdmercadoganadero_restore_parts_test'
    if ($LASTEXITCODE -ne 0) { throw 'Falló la restauración del respaldo de estructura.' }
    Get-Content -Raw $DataFile | & docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot bdmercadoganadero_restore_parts_test'
    if ($LASTEXITCODE -ne 0) { throw 'Falló la restauración del respaldo de datos.' }

    if ($InjectSchemaDifference) {
        Invoke-MySqlQuery "ALTER TABLE $PartsDatabase.tbproductor ADD CONSTRAINT ck_injected_metadata CHECK (tbproductorid IS NOT NULL);" | Out-Null
    }
    Compare-MySqlMetadata $SourceDatabase $RestoreDatabase 'origen/completo'
    Compare-MySqlMetadata $RestoreDatabase $PartsDatabase 'completo/estructura+datos'
    foreach ($database in @($SourceDatabase, $RestoreDatabase, $PartsDatabase)) {
        $collation = Invoke-MySqlQuery "SELECT CONCAT(DEFAULT_CHARACTER_SET_NAME,'/',DEFAULT_COLLATION_NAME) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$database';"
        if ($collation -ne 'utf8mb4/utf8mb4_unicode_ci') { throw "$database usa $collation." }
        $invalidTables = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$database' AND TABLE_TYPE='BASE TABLE' AND TABLE_COLLATION<>'utf8mb4_unicode_ci';"
        if ($invalidTables -ne '0') { throw "$database contiene tablas con intercalación incorrecta." }
        $constraintCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$database';"
        if ($constraintCount -ne '0') { throw "$database contiene $constraintCount restricciones." }
        $primaryKeyCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$database' AND CONSTRAINT_TYPE='PRIMARY KEY';"
        if ($primaryKeyCount -ne '0') { throw "$database contiene $primaryKeyCount PRIMARY KEY." }
        $foreignKeyCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$database' AND CONSTRAINT_TYPE='FOREIGN KEY';"
        if ($foreignKeyCount -ne '0') { throw "$database contiene $foreignKeyCount FOREIGN KEY." }
        $checkCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$database' AND CONSTRAINT_TYPE='CHECK';"
        if ($checkCount -ne '0') { throw "$database contiene $checkCount CHECK." }
        $tablesCsv = Invoke-MySqlQuery "SELECT GROUP_CONCAT(TABLE_NAME ORDER BY TABLE_NAME) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$database' AND TABLE_TYPE='BASE TABLE';"
        if ($tablesCsv -ne $ExpectedTablesCsv) { throw "$database contiene tablas inesperadas: $tablesCsv." }
        $indexCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$database';"
        if ($indexCount -ne '0') { throw "$database contiene $indexCount índices." }
        $productorIdExtra = Invoke-MySqlQuery "SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$database' AND TABLE_NAME='tbproductor' AND COLUMN_NAME='tbproductorid';"
        if ($productorIdExtra) { throw "$database.tbproductorid usa EXTRA=$productorIdExtra." }
        $automaticColumns = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$database' AND (COLUMN_DEFAULT IS NOT NULL OR EXTRA<>'' OR GENERATION_EXPRESSION<>'');"
        if ($automaticColumns -ne '0') { throw "$database contiene $automaticColumns columnas con generación automática." }
        $programmableObjects = Invoke-MySqlQuery "SELECT (SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='$database')+(SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='$database')+(SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA='$database');"
        if ($programmableObjects -ne '0') { throw "$database contiene $programmableObjects objetos programables." }
    }

    $TableSourceDatabase = if ($BackupDatabase -eq $SourceDatabase) { $SourceDatabase } else { $RestoreDatabase }
    $Tables = (Invoke-MySqlQuery "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$TableSourceDatabase' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;") -split "`n"
    foreach ($table in $Tables) {
        $sourceCount = if ($BackupDatabase -eq $SourceDatabase) { Invoke-MySqlQuery "SELECT COUNT(*) FROM $SourceDatabase.$table;" } else { 'LEGADO' }
        $restoreCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM $RestoreDatabase.$table;"
        $partsCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM $PartsDatabase.$table;"
        Write-Host ("{0,-38} origen={1} completo={2} partes={3}" -f $table, $sourceCount, $restoreCount, $partsCount)
        if ($BackupDatabase -eq $SourceDatabase -and $sourceCount -ne $restoreCount) { throw "El conteo difiere para $table." }
        if ($restoreCount -ne $partsCount) { throw "El conteo difiere para $table." }
        $sourceChecksum = if ($BackupDatabase -eq $SourceDatabase) { (Invoke-MySqlQuery "CHECKSUM TABLE $SourceDatabase.$table;") -split "\s+" | Select-Object -Last 1 } else { $null }
        $restoreChecksum = (Invoke-MySqlQuery "CHECKSUM TABLE $RestoreDatabase.$table;") -split "\s+" | Select-Object -Last 1
        $partsChecksum = (Invoke-MySqlQuery "CHECKSUM TABLE $PartsDatabase.$table;") -split "\s+" | Select-Object -Last 1
        if ($BackupDatabase -eq $SourceDatabase -and $sourceChecksum -ne $restoreChecksum) { throw "Los datos difieren para $table." }
        if ($restoreChecksum -ne $partsChecksum) { throw "Los datos difieren para $table." }
    }

    Invoke-MySqlQuery "SELECT pr.tbproductorid,pe.tbpersonaidentificacionnumero FROM $RestoreDatabase.tbproductor pr JOIN $RestoreDatabase.tbpersona pe ON pe.tbpersonaid=pr.tbpersonaid ORDER BY pr.tbproductorid LIMIT 1;" | Out-Null
    $tableCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SourceDatabase' AND TABLE_TYPE='BASE TABLE';"
    $constraintCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$SourceDatabase';"
    $indexCount = Invoke-MySqlQuery "SELECT COUNT(DISTINCT TABLE_NAME,INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='$SourceDatabase';"
    $primaryKeyCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$SourceDatabase' AND CONSTRAINT_TYPE='PRIMARY KEY';"
    $foreignKeyCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$SourceDatabase' AND CONSTRAINT_TYPE='FOREIGN KEY';"
    $checkCount = Invoke-MySqlQuery "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$SourceDatabase' AND CONSTRAINT_TYPE='CHECK';"
    Write-Host "Restauración correcta: tablas=$tableCount, restricciones=$constraintCount."
    Write-Host "Respaldo validado sin modificar MANIFEST ni SHA256: $BackupDatabase."
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
