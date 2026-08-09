[CmdletBinding()]
param(
    [Parameter(Mandatory = $true, Position = 0)]
    [ValidatePattern('^Avance[0-9]{2}(Correccion[0-9]{2})?$')]
    [string]$Avance,

    [Parameter(Position = 1)]
    [string]$Responsable
)

$ErrorActionPreference = 'Stop'
$DatabaseName = 'dbtindervacas'
$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$AdvanceMatch = [regex]::Match($Avance, '^Avance(?<avance>[0-9]{2})(Correccion(?<correccion>[0-9]{2}))?$')
$AdvanceNumber = $AdvanceMatch.Groups['avance'].Value
$CorrectionNumber = $AdvanceMatch.Groups['correccion'].Value
$AdvanceSlug = "avance$AdvanceNumber"
$OfficialTag = "avance-$AdvanceNumber"
if ($CorrectionNumber) {
    $AdvanceSlug += "_correccion$CorrectionNumber"
    $OfficialTag += "-correccion-$CorrectionNumber"
}
$TargetDirectory = Join-Path $ProjectRoot "Database/Backups/$Avance"
$WritesFrozen = $false
$ReadOnlyState = '0'
$SuperReadOnlyState = '0'

function Quote-ProcessArgument([string]$Value) {
    return '"' + ($Value -replace '(\\*)"', '$1$1\"' -replace '(\\+)$', '$1$1') + '"'
}

function Invoke-DockerProcess {
    param(
        [Parameter(Mandatory = $true)][string[]]$Arguments,
        [string]$OutputFile,
        [string]$InputFile
    )
    $startInfo = [Diagnostics.ProcessStartInfo]::new()
    $startInfo.FileName = 'docker'
    $startInfo.Arguments = (($Arguments | ForEach-Object { Quote-ProcessArgument $_ }) -join ' ')
    $startInfo.UseShellExecute = $false
    $startInfo.RedirectStandardError = $true
    $startInfo.RedirectStandardOutput = [bool]$OutputFile
    $startInfo.RedirectStandardInput = [bool]$InputFile
    $process = [Diagnostics.Process]::new()
    $process.StartInfo = $startInfo
    [void]$process.Start()
    $outputStream = $null
    $inputStream = $null
    try {
        if ($OutputFile) {
            $outputStream = [IO.File]::Create($OutputFile)
            $process.StandardOutput.BaseStream.CopyTo($outputStream)
        }
        if ($InputFile) {
            $inputStream = [IO.File]::OpenRead($InputFile)
            $inputStream.CopyTo($process.StandardInput.BaseStream)
            $process.StandardInput.Close()
        }
        $stderr = $process.StandardError.ReadToEnd()
        $process.WaitForExit()
        $exitCode = $process.ExitCode
    }
    finally {
        if ($outputStream) { $outputStream.Dispose() }
        if ($inputStream) { $inputStream.Dispose() }
        $process.Dispose()
    }
    if ($exitCode -ne 0) { throw "docker falló: $stderr" }
}

Push-Location $ProjectRoot
try {
    if (-not $Responsable) { $Responsable = (& git config user.name 2>$null) }
    if (-not $Responsable) { throw 'Indique -Responsable o configure git user.name.' }
    New-Item -ItemType Directory -Force -Path $TargetDirectory | Out-Null

    $CompleteFile = Join-Path $TargetDirectory "${DatabaseName}_${AdvanceSlug}_completo.sql"
    $SchemaFile = Join-Path $TargetDirectory "${DatabaseName}_${AdvanceSlug}_estructura.sql"
    $DataFile = Join-Path $TargetDirectory "${DatabaseName}_${AdvanceSlug}_datos.sql"
    $ManifestFile = Join-Path $TargetDirectory 'MANIFEST.md'
    $ChecksumsFile = Join-Path $TargetDirectory 'SHA256SUMS.txt'
    @($CompleteFile, $SchemaFile, $DataFile, $ManifestFile, $ChecksumsFile) | ForEach-Object {
        if (Test-Path $_) { throw "No se sobrescribe un respaldo existente: $_" }
    }

    $GitStatus = (& git status --porcelain --untracked-files=all)
    if ($GitStatus) { throw 'Cree primero el commit candidato; el árbol de trabajo debe estar limpio.' }
    & docker compose config --quiet
    if ($LASTEXITCODE -ne 0) { throw 'compose.yaml no es válido.' }
    & docker compose exec -T db sh -c 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" --silent' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'El servicio db no está disponible.' }

    $CandidateCommit = (& git rev-parse HEAD).Trim()
    $Branch = (& git branch --show-current).Trim()
    $MySqlVersion = (& docker compose exec -T db sh -c 'mysql -N -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT VERSION()"').Trim()
    $GeneratedAt = [DateTimeOffset]::Now.ToString('yyyy-MM-dd HH:mm zzz')

    $ReadOnlyValues = ((& docker compose exec -T db sh -c 'mysql -N -B -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT @@GLOBAL.read_only, @@GLOBAL.super_read_only"') -join "`n").Trim() -split '\s+'
    if ($LASTEXITCODE -ne 0 -or $ReadOnlyValues.Count -ne 2) { throw 'No se pudo leer el estado read_only de MySQL.' }
    $ReadOnlyState = $ReadOnlyValues[0]
    $SuperReadOnlyState = $ReadOnlyValues[1]
    & docker compose exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SET GLOBAL read_only=ON; SET GLOBAL super_read_only=ON;"' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'No se pudieron congelar las escrituras durante el respaldo.' }
    $WritesFrozen = $true

    $BaseArgs = @('compose', 'exec', '-T', 'db', 'sh', '-c')
    Invoke-DockerProcess ($BaseArgs + 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers --events --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 dbtindervacas') $CompleteFile
    Invoke-DockerProcess ($BaseArgs + 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --no-data --routines --triggers --events --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 dbtindervacas') $SchemaFile
    Invoke-DockerProcess ($BaseArgs + 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --no-create-info --single-transaction --skip-triggers --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 dbtindervacas') $DataFile

    & docker compose exec -T -e "READ_ONLY_STATE=$ReadOnlyState" -e "SUPER_READ_ONLY_STATE=$SuperReadOnlyState" db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SET GLOBAL super_read_only=OFF; SET GLOBAL read_only=$READ_ONLY_STATE; SET GLOBAL super_read_only=$SUPER_READ_ONLY_STATE;"' | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo restaurar el estado de escritura de MySQL.' }
    $WritesFrozen = $false

    @($CompleteFile, $SchemaFile, $DataFile) | ForEach-Object {
        if ((Get-Item $_).Length -eq 0 -or -not (Select-String -Quiet -SimpleMatch 'MySQL dump' -Path $_)) {
            throw "mysqldump produjo un archivo vacío o inválido: $_"
        }
    }

    $Manifest = @"
# Respaldo — $Avance

- Proyecto: TinderCows
- Base de datos: $DatabaseName
- Entrega: $Avance
- Fecha y hora: $GeneratedAt
- Motor: MySQL $MySqlVersion
- Rama: $Branch
- Commit candidato de código: $CandidateCommit
- Etiqueta oficial: $OfficialTag
- Responsable de exportación: $Responsable
- Archivo completo: $([IO.Path]::GetFileName($CompleteFile))
- Archivo de estructura: $([IO.Path]::GetFileName($SchemaFile))
- Archivo de datos: $([IO.Path]::GetFileName($DataFile))
- Intercalación comprobada: Pendiente
- Restauración completa comprobada: Pendiente
- Restauración estructura + datos comprobada: Pendiente
- Bases temporales utilizadas: dbtindervacas_restore_test, dbtindervacas_restore_parts_test
- Cantidad de tablas: Pendiente
- Cantidad de restricciones: Pendiente
- Cantidad de índices: Pendiente
- Cantidad de PRIMARY KEY: Pendiente
- Cantidad de FOREIGN KEY: Pendiente
- Cantidad de CHECK: Pendiente
- Resultado final: Pendiente
- Observaciones: Ejecutar Tools/Test-Restore.ps1 $Avance y registrar el resultado antes de etiquetar.
"@
    [IO.File]::WriteAllText($ManifestFile, $Manifest, [Text.UTF8Encoding]::new($false))

    $ChecksumLines = @($CompleteFile, $SchemaFile, $DataFile) | ForEach-Object {
        $hash = (Get-FileHash -Algorithm SHA256 $_).Hash.ToLowerInvariant()
        "$hash  $([IO.Path]::GetFileName($_))"
    }
    [IO.File]::WriteAllLines($ChecksumsFile, $ChecksumLines, [Text.UTF8Encoding]::new($false))

    foreach ($line in $ChecksumLines) {
        $parts = $line -split '  ', 2
        $actual = (Get-FileHash -Algorithm SHA256 (Join-Path $TargetDirectory $parts[1])).Hash.ToLowerInvariant()
        if ($actual -ne $parts[0]) { throw "Falló la suma SHA-256 de $($parts[1])." }
        Write-Host "$($parts[1]): CORRECTO"
    }
    Write-Host "Respaldo generado: $TargetDirectory"
    Write-Host "Siguiente paso obligatorio: Tools/Test-Restore.ps1 $Avance"
}
finally {
    if ($WritesFrozen) {
        & docker compose exec -T -e "READ_ONLY_STATE=$ReadOnlyState" -e "SUPER_READ_ONLY_STATE=$SuperReadOnlyState" db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SET GLOBAL super_read_only=OFF; SET GLOBAL read_only=$READ_ONLY_STATE; SET GLOBAL super_read_only=$SUPER_READ_ONLY_STATE;"' | Out-Null
    }
    Pop-Location
}
