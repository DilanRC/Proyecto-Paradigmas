<?php

declare(strict_types=1);

use Application\Service\LocalDatabaseInstaller;
use Application\Service\LocalDatabaseInstallerException;
use Configuration\LocalDatabaseCredentials;

header('Cache-Control: no-store');
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/Service/LocalDatabaseInstaller.php';
require_once dirname(__DIR__) . '/Configuration/LocalDatabaseCredentials.php';

if (!LocalDatabaseInstaller::requestIsLocal($_SERVER)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No encontrado.';
    exit;
}

$basePath = tc_public_base_path();
try {
    $sessionPath = LocalDatabaseCredentials::sessionDirectory();
    session_save_path($sessionPath);
    if (session_save_path() !== $sessionPath) {
        throw new RuntimeException('El almacenamiento privado de sesión no está disponible.');
    }
} catch (Throwable) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo LocalDatabaseCredentials::supportsPrivateStoragePlatform()
        ? 'No se pudo iniciar el almacenamiento local privado. Revise los permisos temporales de XAMPP.'
        : 'El asistente web requiere XAMPP en Linux con la extensión POSIX para proteger las credenciales. En Windows, use la instalación manual documentada.';
    exit;
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');
session_name('ganadocerca_instalador');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $basePath,
    'secure' => isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();
$_SESSION['installer_csrf'] ??= bin2hex(random_bytes(32));

// Cierra una instalación o una reanudación: olvida las credenciales de la sesión y renueva el CSRF.
$terminar = static function (array $resultado): void {
    unset($_SESSION['installer_credentials'], $_SESSION['installer_form']);
    session_regenerate_id(true);
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
    $mensaje = 'Instalación verificada: 48 tablas, 48 llaves primarias y los catálogos iniciales requeridos.';
    if (($resultado['applicationUser'] ?? '') === LocalDatabaseInstaller::APP_USER) {
        $mensaje .= ' La aplicación usará la cuenta MySQL ' . LocalDatabaseInstaller::APP_USER . ', limitada a esta base.';
    }
    $_SESSION['installer_flash'] = ['type' => 'success', 'message' => $mensaje];
};

$redirigir = static function () use ($basePath): void {
    header('Location: ' . $basePath . 'instalacion', true, 303);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (!is_string($csrf) || !hash_equals((string) $_SESSION['installer_csrf'], $csrf)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'La sesión de instalación venció. Vuelva a cargar la página.';
        exit;
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    try {
        if ($action === 'inspect') {
            $credentials = LocalDatabaseInstaller::validateCredentials($_POST);
            LocalDatabaseInstaller::inspect($credentials);
            $_SESSION['installer_credentials'] = $credentials;
            $_SESSION['installer_form'] = [
                'host' => $credentials['host'],
                'port' => $credentials['port'],
                'user' => $credentials['user'],
            ];
            $_SESSION['installer_flash'] = [
                'type' => 'success',
                'message' => 'Conexión comprobada. Esta revisión no escribió en MySQL.',
            ];
        } elseif ($action === 'install') {
            if (($_POST['consent'] ?? '') !== 'yes') {
                throw new LocalDatabaseInstallerException(
                    'Confirme que la base bdmercadoganadero no existe antes de crearla.'
                );
            }
            $credentials = $_SESSION['installer_credentials'] ?? null;
            if (!is_array($credentials)) {
                throw new LocalDatabaseInstallerException(
                    'Compruebe la conexión local antes de confirmar la instalación.',
                    409,
                );
            }
            $terminar(LocalDatabaseInstaller::install($credentials));
        } elseif ($action === 'resume') {
            $credentials = $_SESSION['installer_credentials'] ?? null;
            if (!is_array($credentials)) {
                throw new LocalDatabaseInstallerException(
                    'Compruebe la conexión local antes de terminar la instalación.',
                    409,
                );
            }
            $terminar(LocalDatabaseInstaller::resume($credentials));
        } elseif ($action === 'save') {
            $credentials = $_SESSION['installer_credentials'] ?? null;
            if (!is_array($credentials)) {
                throw new LocalDatabaseInstallerException(
                    'Compruebe la conexión antes de guardarla.',
                    409,
                );
            }
            LocalDatabaseInstaller::saveExistingConnection($credentials);
            unset($_SESSION['installer_credentials'], $_SESSION['installer_form']);
            session_regenerate_id(true);
            $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
            $_SESSION['installer_flash'] = [
                'type' => 'success',
                'message' => 'Conexión local guardada. No se modificó la base existente.',
            ];
        } else {
            throw new LocalDatabaseInstallerException('La acción solicitada no está disponible.', 400);
        }
    } catch (LocalDatabaseInstallerException $exception) {
        $_SESSION['installer_flash'] = ['type' => 'error', 'message' => $exception->getMessage()];
        $credentials = $_SESSION['installer_credentials'] ?? null;
        if (is_array($credentials)) {
            $_SESSION['installer_form'] = [
                'host' => (string) ($credentials['host'] ?? '127.0.0.1'),
                'port' => (int) ($credentials['port'] ?? 3306),
                'user' => (string) ($credentials['user'] ?? 'root'),
            ];
        } elseif ($action === 'inspect') {
            $formHost = $_POST['host'] ?? null;
            $formPort = $_POST['port'] ?? null;
            $formUser = $_POST['user'] ?? null;
            $_SESSION['installer_form'] = [
                'host' => is_string($formHost) ? mb_substr($formHost, 0, 255, 'UTF-8') : '127.0.0.1',
                'port' => is_string($formPort) && ctype_digit($formPort) ? (int) $formPort : 3306,
                'user' => is_string($formUser) ? mb_substr($formUser, 0, 80, 'UTF-8') : 'root',
            ];
        }
        if (LocalDatabaseCredentials::state() !== null) {
            unset($_SESSION['installer_credentials']);
        }
    } catch (Throwable) {
        $_SESSION['installer_flash'] = [
            'type' => 'error',
            'message' => 'No se completó la operación. Revise MySQL y el almacenamiento local antes de volver a intentarlo.',
        ];
    }
    $redirigir();
}

$flash = is_array($_SESSION['installer_flash'] ?? null) ? $_SESSION['installer_flash'] : null;
unset($_SESSION['installer_flash']);
$sessionCredentials = is_array($_SESSION['installer_credentials'] ?? null)
    ? $_SESSION['installer_credentials']
    : null;
$environmentManaged = LocalDatabaseInstaller::credentialsManagedByEnvironment();
$activeCredentials = $sessionCredentials ?? LocalDatabaseInstaller::defaultCredentials();
// El formulario pide la cuenta que administra MySQL, no la limitada que usa la aplicación.
$formSource = ($activeCredentials['user'] ?? '') === LocalDatabaseInstaller::APP_USER
    ? LocalDatabaseInstaller::environmentDefaults()
    : $activeCredentials;
$formDefaults = is_array($_SESSION['installer_form'] ?? null)
    ? $_SESSION['installer_form']
    : [
        'host' => $formSource['host'] ?? '127.0.0.1',
        'port' => $formSource['port'] ?? 3306,
        'user' => $formSource['user'] ?? 'root',
    ];
$report = null;
$inspectionError = null;
try {
    $report = LocalDatabaseInstaller::inspect($activeCredentials);
} catch (LocalDatabaseInstallerException $exception) {
    $inspectionError = $exception->getMessage();
}

$installerState = $report['installationState'] ?? LocalDatabaseCredentials::state();
$mysqliAvailable = extension_loaded('mysqli');
$canInstall = $report !== null && !$report['databaseExists'] && $sessionCredentials !== null && $mysqliAvailable;
$interrupted = $report !== null && $report['databaseExists'] && $installerState === 'installing';
$canResume = $interrupted && $sessionCredentials !== null && $mysqliAvailable;
$canSave = $report !== null && $report['databaseExists'] && $report['schemaComplete']
    && $sessionCredentials !== null && !$environmentManaged && !LocalDatabaseInstaller::storedConnectionWorks();
$html = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formAction = $basePath . 'instalacion';
?>
<!doctype html>
<html lang="es" data-theme="dark">
<head>
    <base href="<?= $html($basePath) ?>">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#151a18">
    <title>Preparar base local | Ganado Cerca</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="css/tokens.css?v=official-shell-2">
    <link rel="stylesheet" href="css/base.css?v=product-9">
    <link rel="stylesheet" href="css/public-auth.css?v=brand-4">
    <script type="module" src="js/public-theme.js?v=theme-4"></script>
</head>
<body class="auth-page">
    <main class="auth-stage" aria-labelledby="install-title">
        <header class="auth-header">
            <a class="public-brand" href="./">
                <span class="public-brand__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="48" height="48">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="48" height="48">
                </span>
                <span>Ganado<strong>Cerca</strong></span>
            </a>
            <div class="auth-header__actions">
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Cambiar a modo claro" aria-pressed="true"><i class="theme-toggle__icon fa-solid fa-sun" aria-hidden="true"></i><span class="theme-toggle__label">Claro</span></button>
                <a class="auth-back" href="./"><span>Inicio</span></a>
            </div>
        </header>

        <div class="auth-layout">
            <section class="auth-context" aria-label="Información de la instalación">
                <span class="auth-context__logo" aria-hidden="true">
                    <img class="brand-logo brand-logo--dark" src="assets/logo_dark.png" alt="" width="176" height="176">
                    <img class="brand-logo brand-logo--light" src="assets/logo_light.png" alt="" width="176" height="176">
                </span>
                <p class="section-kicker">XAMPP local</p>
                <h2>Una base lista para revisar.</h2>
                <p>Este asistente prepara MySQL en esta computadora. No crea cuentas ni datos de ejemplo: las personas y publicaciones serán las que usted registre.</p>
                <p><a href="/phpmyadmin/">Abrir phpMyAdmin</a></p>
            </section>

            <section class="auth-card">
                <div class="auth-card__brand">
                    <div><p class="section-kicker">Configuración local</p><h1 id="install-title">Preparar la base local</h1></div>
                </div>
                <p class="auth-card__copy">Se usa el SQL canónico del proyecto. El esquema solo se importa cuando MySQL confirma que <code>bdmercadoganadero</code> no existe y usted lo confirma con POST.</p>

                <?php if ($flash !== null): ?>
                    <p class="setup-state setup-state--<?= $html($flash['type'] ?? 'info') ?>" role="status" aria-live="polite"><?= $html($flash['message'] ?? '') ?></p>
                <?php endif; ?>
                <?php if ($inspectionError !== null): ?>
                    <p class="setup-state setup-state--error" role="status" aria-live="polite"><?= $html($inspectionError) ?></p>
                <?php endif; ?>
                <?php if ($inspectionError !== null && $environmentManaged): ?>
                    <p class="setup-detail">La conexión viene de las variables <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_NAME</code>, <code>DB_USER</code> o <code>DB_PASS</code> que define Apache. Corríjalas en la configuración del servidor.</p>
                <?php endif; ?>
                <?php if ($report !== null && !$report['databaseExists'] && !$mysqliAvailable): ?>
                    <p class="setup-state setup-state--error" role="status">La conexión PDO funciona, pero falta la extensión <code>mysqli</code> necesaria para importar SQL. Actívela en el <code>php.ini</code> de Apache y reinicie XAMPP antes de instalar.</p>
                <?php endif; ?>

                <?php if ($interrupted): ?>
                    <div class="setup-state setup-state--warning" role="status">
                        <strong>La instalación quedó a medias.</strong>
                        <span>Este asistente creó la base y se detuvo antes de terminar: hay <?= $html($report['tableCount']) ?>/48 tablas.</span>
                        <span>Compruebe la conexión para terminarla; solo se crea lo que falta.</span>
                    </div>
                <?php elseif ($report !== null && $report['databaseExists'] && $report['schemaComplete']): ?>
                    <div class="setup-state setup-state--success" role="status">
                        <strong>Esquema completo.</strong>
                        <span><?= $html($report['tableCount']) ?>/48 tablas y <?= $html($report['primaryKeyCount']) ?>/48 llaves primarias.</span>
                        <span>MySQL <?= $html($report['mysqlVersion']) ?> en <?= $html($report['host'] ?? $activeCredentials['host']) ?>:<?= $html($activeCredentials['port']) ?>.</span>
                    </div>
                <?php elseif ($report !== null && $report['databaseExists']): ?>
                    <div class="setup-state setup-state--warning" role="status">
                        <strong>La base ya existe y no se modificó.</strong>
                        <span>Se encontraron <?= $html($report['tableCount']) ?>/48 tablas y <?= $html($report['primaryKeyCount']) ?>/48 llaves primarias.</span>
                        <span>El instalador no completa ni vuelve a importar un esquema existente.</span>
                    </div>
                    <p class="setup-detail">Si es la base de una entrega anterior: en <a href="/phpmyadmin/">phpMyAdmin</a> expórtela como respaldo y cámbiele el nombre (Operaciones → Cambiar el nombre de la base de datos); luego recargue esta página para crear la base nueva. Para conservar esos datos en esta versión, aplique las migraciones pendientes como indica <code>Documentation/Instalacion-XAMPP.md</code>.</p>
                    <?php if ($report['columnDifferences'] !== []): ?>
                        <p class="setup-detail">Columnas distintas del esquema actual (<?= $html(count($report['columnDifferences'])) ?>): <?= $html(implode('; ', array_slice($report['columnDifferences'], 0, 8))) ?><?= count($report['columnDifferences']) > 8 ? '…' : '' ?>.</p>
                    <?php endif; ?>
                    <?php if ($report['missingTables'] !== [] || $report['missingPrimaryKeys'] !== []): ?>
                        <p class="setup-detail">Tablas faltantes: <?= $html(implode(', ', $report['missingTables']) ?: 'ninguna') ?>. Tablas sin su llave primaria esperada: <?= $html(implode(', ', $report['missingPrimaryKeys']) ?: 'ninguna') ?>.</p>
                    <?php endif; ?>
                <?php elseif ($report !== null): ?>
                    <div class="setup-state setup-state--info" role="status">
                        <strong>MySQL está disponible.</strong>
                        <span>La base bdmercadoganadero todavía no existe.</span>
                    </div>
                <?php endif; ?>

                <?php if ($report !== null && $report['databaseExists']): ?>
                    <section class="setup-seeds" aria-labelledby="seed-status-title">
                        <h2 id="seed-status-title">Datos iniciales</h2>
                        <ul>
                            <?php foreach (LocalDatabaseInstaller::seedDefinitions() as $file => $definition): ?>
                                <?php $seed = $report['seeds'][$file] ?? []; ?>
                                <?php
                                    $stateText = !empty($seed['complete'])
                                        ? 'Detectada'
                                        : (!empty($seed['partial']) ? 'Parcial' : ($definition['required'] ? 'Faltante' : 'Omitida'));
                                    $stateText .= $definition['required'] ? ' · requerida' : ' · opcional';
                                ?>
                                <li><span><?= $html($definition['label']) ?></span><strong><?= $html($stateText) ?></strong></li>
                            <?php endforeach; ?>
                        </ul>
                        <p>La semilla 102 solo guarda una política de acceso; no crea cuentas ni contraseñas. La semilla 103 contiene personas ficticias y no se carga en esta instalación.</p>
                    </section>
                <?php endif; ?>

                <?php if ($canInstall): ?>
                    <form class="auth-form setup-confirm" method="post" action="<?= $html($formAction) ?>">
                        <input type="hidden" name="csrf" value="<?= $html($_SESSION['installer_csrf']) ?>">
                        <input type="hidden" name="action" value="install">
                        <label class="setup-consent"><input type="checkbox" name="consent" value="yes" required><span>Confirmo que la base bdmercadoganadero no existe y autorizo crearla con el esquema canónico y catálogos iniciales.</span></label>
                        <p class="setup-detail">MySQL confirma cada tabla por separado. Si la instalación se interrumpe, lo ya creado se conserva y esta página permite terminarla.</p>
                        <button class="auth-submit" type="submit">Instalar esquema y catálogos</button>
                    </form>
                <?php elseif ($canResume): ?>
                    <form class="auth-form setup-confirm" method="post" action="<?= $html($formAction) ?>">
                        <input type="hidden" name="csrf" value="<?= $html($_SESSION['installer_csrf']) ?>">
                        <input type="hidden" name="action" value="resume">
                        <button class="auth-submit" type="submit">Terminar la instalación</button>
                        <p class="setup-detail">Crea las tablas que falten y deja el método de pago, la política de acceso y los catálogos iniciales en su valor original. No borra nada.</p>
                    </form>
                <?php elseif ($canSave): ?>
                    <form class="auth-form setup-confirm" method="post" action="<?= $html($formAction) ?>">
                        <input type="hidden" name="csrf" value="<?= $html($_SESSION['installer_csrf']) ?>">
                        <input type="hidden" name="action" value="save">
                        <button class="auth-submit" type="submit">Guardar conexión local</button>
                        <p class="setup-detail">Esta acción solo guarda la conexión en un archivo temporal privado fuera de htdocs. No cambia datos de MySQL.</p>
                    </form>
                <?php elseif (!$environmentManaged || $sessionCredentials !== null): ?>
                    <form class="auth-form setup-credentials" method="post" action="<?= $html($formAction) ?>" autocomplete="off">
                        <input type="hidden" name="csrf" value="<?= $html($_SESSION['installer_csrf']) ?>">
                        <input type="hidden" name="action" value="inspect">
                        <div class="setup-grid">
                            <label class="auth-field"><span>Host MySQL</span><input name="host" type="text" value="<?= $html($formDefaults['host'] ?? '127.0.0.1') ?>" required autocomplete="off" spellcheck="false"></label>
                            <label class="auth-field"><span>Puerto</span><input name="port" type="number" min="1" max="65535" value="<?= $html($formDefaults['port'] ?? 3306) ?>" required inputmode="numeric"></label>
                        </div>
                        <label class="auth-field"><span>Usuario MySQL</span><input name="user" type="text" value="<?= $html($formDefaults['user'] ?? 'root') ?>" required maxlength="80" autocomplete="username" spellcheck="false"></label>
                        <label class="auth-field"><span>Contraseña MySQL</span><input name="password" type="password" maxlength="4096" autocomplete="current-password"><small>Se envía solo a este servidor local y no aparece en JavaScript, la URL ni los mensajes de error.</small></label>
                        <button class="auth-submit" type="submit">Comprobar conexión</button>
                    </form>
                <?php elseif ($report !== null && $report['databaseExists'] && $report['schemaComplete']): ?>
                    <p class="setup-detail">Apache ya proporciona la conexión MySQL. No se guardó una copia adicional de sus credenciales.</p>
                <?php endif; ?>

                <?php if ($report !== null && $report['databaseExists'] && $report['schemaComplete']): ?>
                    <p class="auth-card__copy"><a href="./">Ver la portada</a> · <a href="explorar">Ir a Explorar</a> · <a href="/phpmyadmin/">Consultar en phpMyAdmin</a></p>
                <?php endif; ?>
            </section>
        </div>
    </main>
</body>
</html>
