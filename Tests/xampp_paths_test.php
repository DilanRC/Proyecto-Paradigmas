<?php

declare(strict_types=1);

require dirname(__DIR__) . '/Public/bootstrap.php';

$assertSame = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException("{$message}: esperado {$expected}, recibido {$actual}");
    }
};

unset($_SERVER['REDIRECT_TC_REWRITE']);
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['DOCUMENT_ROOT'] = '/opt/lampp/htdocs';
$_SERVER['TC_REWRITE'] = '1';
$assertSame('/', tc_public_base_path(), 'VirtualHost con Public como raíz debe usar /');

$_SERVER['SCRIPT_NAME'] = '/GanadoCerca/Public/index.php';
$_SERVER['DOCUMENT_ROOT'] = '/opt/lampp/htdocs';
$esperada = '/GanadoCerca/Public/';
$assertSame($esperada, tc_public_base_path(), 'XAMPP bajo htdocs debe incluir el prefijo del proyecto');

echo "OK xampp_paths_test: base URL compatible con VirtualHost y subcarpeta con mod_rewrite.\n";
