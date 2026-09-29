<#
  instalar-local.ps1
  Instala la base de datos del proyecto en una PC nueva.

  Detecta automaticamente:
    - Que servidor MySQL/MariaDB esta escuchando en el puerto 3306
      y usa SU cliente mysql.exe (importante: el cliente de XAMPP no
      puede hablar con un MySQL 8).
    - Si root tiene contrasena: prueba vacia, luego DB_ROOT_PASS del
      .env, y si ninguna sirve la pide por pantalla.
    - Usuario, contrasena y nombre de la base desde el .env.

  Uso (con MySQL encendido), en la raiz del proyecto:
      powershell -ExecutionPolicy Bypass -File .\instalar-local.ps1

  Para borrar la base y reinstalar desde cero:
      powershell -ExecutionPolicy Bypass -File .\instalar-local.ps1 -Reinstalar
#>
param([switch]$Reinstalar)

$ErrorActionPreference = "Continue"
$proy = $PSScriptRoot

function Fallo($msg) {
  Write-Host ""
  Write-Host "ERROR: $msg" -ForegroundColor Red
  Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
  exit 1
}

# ---------------------------------------------------------------
# 1. Leer el .env
# ---------------------------------------------------------------
$envRuta = Join-Path $proy ".env"
$cfg = @{}
if (Test-Path $envRuta) {
  foreach ($linea in Get-Content $envRuta) {
    if ($linea -match '^\s*#') { continue }
    if ($linea -match '^\s*([A-Za-z0-9_]+)\s*=\s*(.*)$') {
      $clave = $Matches[1]
      $valor = $Matches[2].Trim()
      if ($valor -match '^"(.*)"$') { $valor = $Matches[1] }
      elseif ($valor -match "^'(.*)'$") { $valor = $Matches[1] }
      $cfg[$clave] = $valor
    }
  }
  Write-Host "[OK] .env leido."
} else {
  Write-Host "[AVISO] No hay .env en $proy; se usan valores por defecto." -ForegroundColor Yellow
}

$dbName   = if ($cfg["DB_NAME"]) { $cfg["DB_NAME"] } else { "bdmercadoganadero" }
$dbUser   = if ($cfg["DB_USER"]) { $cfg["DB_USER"] } else { "tinder_cows" }
$dbPass   = if ($cfg["DB_PASS"]) { $cfg["DB_PASS"] } else { "tinder_vacas_dev" }
$rootEnv  = $cfg["DB_ROOT_PASS"]

if ($dbName -ne "bdmercadoganadero") {
  Write-Host "[AVISO] DB_NAME es '$dbName' pero 000instalacioncompleta.sql crea 'bdmercadoganadero'." -ForegroundColor Yellow
  Write-Host "        Ajusta el .env o el script SQL para que coincidan." -ForegroundColor Yellow
}

# ---------------------------------------------------------------
# 2. Detectar el servidor que escucha en 3306 y su cliente
# ---------------------------------------------------------------
$rutaServidor = $null
try {
  $pidEscucha = (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction Stop |
                 Select-Object -First 1).OwningProcess
  if ($pidEscucha) {
    $rutaServidor = (Get-Process -Id $pidEscucha -ErrorAction SilentlyContinue).Path
  }
} catch { }

if (-not $rutaServidor) {
  $p = Get-Process mysqld, mariadbd -ErrorAction SilentlyContinue | Select-Object -First 1
  if ($p) { $rutaServidor = $p.Path }
}

$cli = $null
if ($rutaServidor) {
  $cand = Join-Path (Split-Path $rutaServidor) "mysql.exe"
  if (Test-Path $cand) { $cli = $cand }
}

if (-not $cli) {
  Write-Host "[AVISO] No pude detectar el servidor en el puerto 3306; busco un cliente conocido." -ForegroundColor Yellow
  $candidatos = @(
    "C:\xampp\mysql\bin\mysql.exe",
    "D:\XAMP\mysql\bin\mysql.exe",
    "D:\xampp\mysql\bin\mysql.exe"
  ) + @(Get-ChildItem "C:\Program Files\MySQL\MySQL Server *\bin\mysql.exe" -ErrorAction SilentlyContinue |
        ForEach-Object { $_.FullName })
  $cli = $candidatos | Where-Object { Test-Path $_ } | Select-Object -First 1
}

if (-not $cli) {
  Fallo "No encontre mysql.exe. Enciende MySQL (XAMPP o servicio MySQL80) y vuelve a intentar."
}
Write-Host "[OK] Cliente: $cli"
if ($rutaServidor) { Write-Host "[OK] Servidor: $rutaServidor" }

# ---------------------------------------------------------------
# 3. Encontrar la contrasena de root
# ---------------------------------------------------------------
function Probar-Root($pass) {
  $env:MYSQL_PWD = $pass
  & $cli -u root --connect-timeout=5 -e "SELECT 1" 2>$null | Out-Null
  return ($LASTEXITCODE -eq 0)
}

$rootPass = $null
$intentos = @("")
if ($rootEnv) { $intentos += $rootEnv }

foreach ($i in $intentos) {
  if (Probar-Root $i) { $rootPass = $i; break }
}

if ($null -eq $rootPass) {
  Write-Host "[AVISO] root no acepta contrasena vacia ni la del .env." -ForegroundColor Yellow
  $seg = Read-Host "Escribe la contrasena de root" -AsSecureString
  $plano = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
             [Runtime.InteropServices.Marshal]::SecureStringToBSTR($seg))
  if (Probar-Root $plano) { $rootPass = $plano }
}

if ($null -eq $rootPass) {
  Fallo "No pude entrar como root. Si no recuerdas la contrasena, reseteala con --init-file y vuelve a correr el script."
}
$env:MYSQL_PWD = $rootPass
if ($rootPass -eq "") { Write-Host "[OK] root sin contrasena." }
else { Write-Host "[OK] root autenticado." }

# ---------------------------------------------------------------
# 4. Reinstalar (opcional)
# ---------------------------------------------------------------
if ($Reinstalar) {
  Write-Host "[..] Borrando la base $dbName..."
  & $cli -u root -e "DROP DATABASE IF EXISTS ``$dbName``;"
  if ($LASTEXITCODE -ne 0) { Fallo "No pude borrar la base." }
}

# ---------------------------------------------------------------
# 5. Usuario del proyecto
# ---------------------------------------------------------------
Write-Host "[..] Creando usuario $dbUser..."
$sqlUsuario = "CREATE USER IF NOT EXISTS '$dbUser'@'localhost' IDENTIFIED BY '$dbPass'; " +
              "ALTER USER '$dbUser'@'localhost' IDENTIFIED BY '$dbPass'; " +
              "GRANT ALL PRIVILEGES ON ``$dbName``.* TO '$dbUser'@'localhost'; FLUSH PRIVILEGES;"
& $cli -u root -e $sqlUsuario
if ($LASTEXITCODE -ne 0) { Fallo "No pude crear el usuario $dbUser." }
Write-Host "[OK] Usuario listo."

# ---------------------------------------------------------------
# 6. Esquema (solo si no existe)
# ---------------------------------------------------------------
$existe = & $cli -u root -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$dbName' AND table_name='tbpersona';"
if ($existe -eq "1") {
  Write-Host "[OK] El esquema ya existe; no lo reinstalo (usa -Reinstalar para empezar de cero)."
} else {
  Write-Host "[..] Instalando esquema..."
  $sqlInst = Join-Path $proy "Database\SqlScripts\000instalacioncompleta.sql"
  if (-not (Test-Path $sqlInst)) { Fallo "No existe $sqlInst" }
  cmd /c "`"$cli`" -u root --default-character-set=utf8mb4 < `"$sqlInst`""
  if ($LASTEXITCODE -ne 0) { Fallo "Fallo el instalador. Revisa el mensaje de arriba (linea del error)." }
  Write-Host "[OK] Esquema instalado."

  # El CREATE USER/GRANT se repite: si la base no existia antes, el GRANT ya la cubria
  # (MySQL permite grants sobre bases futuras), pero se refresca por seguridad.
  & $cli -u root -e "FLUSH PRIVILEGES;"
}

# ---------------------------------------------------------------
# 7. Seeds (solo si las tablas estan vacias)
# ---------------------------------------------------------------
$metodos = & $cli -u root -N -B $dbName -e "SELECT COUNT(*) FROM tbpagometodo;"
if ($metodos -eq "0") {
  Write-Host "[..] Cargando seeds..."
  foreach ($s in "101initialpagometodo", "102administrador") {
    $archivo = Join-Path $proy "Database\SeedData\$s.sql"
    if (-not (Test-Path $archivo)) { Fallo "No existe $archivo" }
    cmd /c "`"$cli`" -u root --default-character-set=utf8mb4 $dbName < `"$archivo`""
    if ($LASTEXITCODE -ne 0) { Fallo "Fallo el seed $s." }
  }
  Write-Host "[OK] Seeds cargados."
} else {
  Write-Host "[OK] Los seeds ya estaban cargados."
}

# ---------------------------------------------------------------
# 8. Verificar entrando con el usuario del proyecto
# ---------------------------------------------------------------
$env:MYSQL_PWD = $dbPass
$tablas = & $cli -u $dbUser -N -B $dbName -e "SHOW TABLES;"
if ($LASTEXITCODE -ne 0) { Fallo "El usuario $dbUser no puede entrar a $dbName." }
$admin = & $cli -u $dbUser -N -B $dbName -e "SELECT tbadministradorcorreoelectronico FROM tbadministrador;"

Write-Host ""
Write-Host "=========== RESULTADO ===========" -ForegroundColor Green
Write-Host "Tablas:         $(@($tablas).Count)"
Write-Host "Administrador:  $admin"
if ($cfg["SUPABASE_ADMIN_EMAILS"] -and $admin -and ($cfg["SUPABASE_ADMIN_EMAILS"] -notmatch [regex]::Escape($admin))) {
  Write-Host "[AVISO] El administrador de la base no coincide con SUPABASE_ADMIN_EMAILS del .env." -ForegroundColor Yellow
}
Write-Host "Listo. Enciende Apache y prueba la app." -ForegroundColor Green

Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue
