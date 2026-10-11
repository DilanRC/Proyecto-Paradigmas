<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

/**
 * Lo que revisa readJsonBody() para todos los endpoints, por HTTP real: caracteres de control (Postgres no guarda
 * el carácter nulo y la consulta fallaba con 500), cuerpos de más de 1 MB y que un aviso de PHP nunca salga en la
 * respuesta (display_errors apagado en docker/php-errores.ini).
 */

$url = 'http://127.0.0.1/api/registro-validar-identificacion.php';
$enviar = static fn (string $cuerpo): array => test_http_json('POST', $cuerpo, 'application/json', $url);

$nulo = $enviar('{"correoElectronico":"a\u0000b@example.test"}');
test_same(400, $nulo['status'], 'Un carácter nulo se rechaza con 400');
test_assert(str_contains((string) ($nulo['body']['message'] ?? ''), 'caracteres no permitidos'), 'El mensaje explica el problema');

$control = $enviar('{"correoElectronico":"a\u0007b@example.test"}');
test_same(400, $control['status'], 'Otros caracteres de control también');

$saltos = $enviar(json_encode(['correoElectronico' => "linea\nnueva\t@example.test"]));
test_assert($saltos['status'] !== 400, 'Saltos de línea y tabulaciones no son caracteres prohibidos');

$grande = $enviar(json_encode(['correoElectronico' => str_repeat('a', 1048577)]));
test_same(400, $grande['status'], 'Más de 1 MB se rechaza con 400');
test_assert(str_contains((string) ($grande['body']['message'] ?? ''), '1 MB'), 'El mensaje dice el máximo');

// "{}" es un objeto vacío (cada endpoint dice qué falta); "[]" o un texto suelto no son un formulario.
test_assert($enviar('{}')['status'] !== 400, 'Un objeto vacío llega al endpoint');
test_same(400, $enviar('[1,2]')['status'], 'Una lista no es un formulario');
test_assert(!preg_match('/JSON|cuerpo/i', (string) ($enviar('[1,2]')['body']['message'] ?? '')), 'El mensaje no usa términos técnicos');

// Una lista donde se espera texto: la respuesta sigue siendo JSON válido, sin avisos de PHP impresos.
$lista = $enviar('{"identificacionTipo":["x"],"identificacionNumero":{"a":1}}');
test_same(422, $lista['status'], 'Tipos equivocados se rechazan con 422');
test_assert(is_array($lista['body']), 'La respuesta es JSON (sin avisos de PHP antes)');

echo "OK api_entrada_http_test: caracteres de control, límite de 1 MB y respuestas sin avisos de PHP.\n";
