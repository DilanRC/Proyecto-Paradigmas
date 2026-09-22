<?php

declare(strict_types=1);

require dirname(__DIR__) . '/Public/bootstrap.php';

$assertSame = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException("{$message}: esperado {$expected}, recibido {$actual}");
    }
};

$publicRoot = realpath(dirname(__DIR__) . '/Public');
$assertSame(true, $publicRoot !== false, 'Public debe existir');

$_SERVER['DOCUMENT_ROOT'] = $publicRoot;
$assertSame('/', tc_public_base_path(), 'VirtualHost con Public como raíz debe usar /');

$_SERVER['DOCUMENT_ROOT'] = dirname(dirname($publicRoot));
$esperada = '/' . basename(dirname($publicRoot)) . '/Public/';
$assertSame($esperada, tc_public_base_path(), 'XAMPP bajo htdocs debe incluir el prefijo del proyecto');

$_SERVER['DOCUMENT_ROOT'] = sys_get_temp_dir();
$assertSame('/', tc_public_base_path(), 'Un DocumentRoot que no contiene Public debe usar / como fallback');

echo "OK xampp_paths_test: base URL compatible con VirtualHost y subcarpeta.\n";
