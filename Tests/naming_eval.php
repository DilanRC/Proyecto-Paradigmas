<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$legacyPaths = ['Aplicacion', 'Configuracion', 'BaseDatos', 'Documentacion', 'Pruebas', 'Publico'];
foreach ($legacyPaths as $path) if (file_exists($root . '/' . $path)) throw new RuntimeException("Legacy path remains: {$path}");
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$forbidden = '/\b(Aplicacion|Configuracion|BaseDatos|Publico|ProductorControlador|Productor|productores|id_productor|tipo_identificacion|numero_identificacion|nombre_finca|ACTIVO|INACTIVO)\b/u';
foreach ($iterator as $file) {
    if (!$file->isFile() || str_contains($file->getPathname(), '/.git/')) continue;
    if ($file->getPathname() === __FILE__) continue;
    $contents = file_get_contents($file->getPathname());
    if ($contents !== false && preg_match($forbidden, $contents, $match)) throw new RuntimeException("Legacy term {$match[0]} remains in {$file->getPathname()}");
}
echo "Naming migration eval passed.\n";
