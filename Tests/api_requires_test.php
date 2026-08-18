<?php

declare(strict_types=1);

/**
 * No hay autoloader: cada endpoint de Public/api arma su propia lista de
 * require_once. Si el controlador instancia un modelo que la lista no carga,
 * PHP lanza Error en el constructor y el catch (Throwable) lo convierte en un
 * 500 genérico, idéntico para los cinco verbos. Tests/bootstrap.php no lo
 * detecta porque declara su propia lista.
 */

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

foreach (glob("{$root}/Public/api/*.php") as $endpoint) {
    $codigo = file_get_contents($endpoint);
    if (!preg_match("/foreach \(\[(.+?)\] as \\\$modelo\)/s", $codigo, $listado)) {
        continue;
    }
    preg_match_all("/'([A-Za-z]+)'/", $listado[1], $declarados);
    $cargados = $declarados[1];

    preg_match("#/Application/Controller/([A-Za-z]+\.php)#", $codigo, $rutaControlador);
    test_assert($rutaControlador !== [], basename($endpoint) . ' debe cargar un controlador');
    $controlador = file_get_contents("{$root}/Application/Controller/{$rutaControlador[1]}");

    preg_match_all('/^use Application\\\\Model\\\\([A-Za-z]+);$/m', $controlador, $usados);
    foreach ($usados[1] as $modelo) {
        test_assert(in_array($modelo, $cargados, true), sprintf(
            '%s usa %s pero %s no lo incluye en su require_once',
            $rutaControlador[1], $modelo, basename($endpoint)
        ));
    }
}

echo "OK api_requires_test: cada endpoint carga los modelos que su controlador instancia.\n";
