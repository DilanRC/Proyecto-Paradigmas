<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/Configuration/Configuration.php';
require_once dirname(__DIR__) . '/Configuration/Database.php';

$report = [
    'php_cli' => PHP_VERSION,
    'php_binario' => PHP_BINARY,
    'php_apache' => 'desconocido: CLI no demuestra la version ni extensiones de Apache',
    'php_minimo_8_0' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'extensiones' => [],
    'allow_url_fopen' => filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN),
    'openssl' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'no disponible',
    'zona_horaria_aplicacion' => date_default_timezone_get(),
    'utc' => gmdate('Y-m-d\TH:i:s\Z'),
    'base_datos' => ['conexion' => 'no comprobada'],
];
foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo'] as $extension) {
    $report['extensiones'][$extension] = extension_loaded($extension);
}
try {
    $db = \Configuration\Database::getConnection();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $report['base_datos'] = ['conexion' => 'correcta', 'motor' => $driver, 'version' => $db->getAttribute(PDO::ATTR_SERVER_VERSION)];
    if ($driver === 'mysql') {
        $report['base_datos']['tablas'] = (int) $db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
        $report['base_datos']['restricciones'] = (int) $db->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND constraint_type IN ('FOREIGN KEY', 'UNIQUE', 'CHECK')")->fetchColumn();
        $report['base_datos']['llaves_primarias'] = (int) $db->query("SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND constraint_type = 'PRIMARY KEY'")->fetchColumn();
        $report['base_datos']['indices'] = (int) $db->query('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND index_name <> \'PRIMARY\'')->fetchColumn();
        $report['base_datos']['triggers'] = (int) $db->query('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = DATABASE()')->fetchColumn();
        $report['base_datos']['rutinas'] = (int) $db->query('SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = DATABASE()')->fetchColumn();
        $source = file_get_contents(dirname(__DIR__) . '/Database/SqlScripts/000instalacioncompleta.sql');
        preg_match_all('/CREATE TABLE IF NOT EXISTS ([A-Za-z0-9_]+)/i', $source === false ? '' : $source, $matches);
        $actualTables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $report['base_datos']['tablas_faltantes'] = count(array_diff($matches[1], $actualTables));
        $report['base_datos']['esquema_minimo'] = count($matches[1]) > 0
            && $report['base_datos']['tablas_faltantes'] === 0
            && $report['base_datos']['llaves_primarias'] === $report['base_datos']['tablas']
            && $report['base_datos']['restricciones'] === 0 && $report['base_datos']['indices'] === 0
            && $report['base_datos']['triggers'] === 0 && $report['base_datos']['rutinas'] === 0;
    } else {
        $report['base_datos']['esquema'] = 'no comprobado: diagnostico local orientado a MySQL/MariaDB';
    }
} catch (Throwable $error) {
    $report['base_datos'] = ['conexion' => 'fallo', 'detalle' => 'Revisa destino, credenciales, servicio y permisos. No se muestran datos privados del error.'];
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
$ok = $report['php_minimo_8_0'] && $report['extensiones']['pdo_mysql'] && $report['extensiones']['mbstring']
    && $report['extensiones']['openssl'] && $report['allow_url_fopen']
    && $report['base_datos']['conexion'] === 'correcta' && ($report['base_datos']['esquema_minimo'] ?? false);
exit($ok ? 0 : 1);
