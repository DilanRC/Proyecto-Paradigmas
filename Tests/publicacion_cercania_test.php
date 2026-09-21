<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Application/Service/PublicacionCercaniaService.php';

use Application\Service\PublicacionCercaniaService;

function assert_cercania(bool $condicion, string $mensaje): void
{
    if (!$condicion) throw new RuntimeException($mensaje);
}

$misma = PublicacionCercaniaService::calcularDistanciaKm(9.9281, -84.0907, 9.9281, -84.0907);
assert_cercania(abs($misma) < 0.000001, 'La distancia al mismo punto debe ser cero.');

$unGrado = PublicacionCercaniaService::calcularDistanciaKm(0.0, 0.0, 1.0, 0.0);
assert_cercania($unGrado > 111.0 && $unGrado < 111.3, 'Un grado de latitud debe rondar 111.2 km.');

$ida = PublicacionCercaniaService::calcularDistanciaKm(9.9281, -84.0907, 10.0163, -84.2149);
$vuelta = PublicacionCercaniaService::calcularDistanciaKm(10.0163, -84.2149, 9.9281, -84.0907);
assert_cercania(abs($ida - $vuelta) < 0.000001, 'Haversine debe ser simétrica.');

echo "OK publicacion_cercania_test: Haversine estable y simétrico.\n";
