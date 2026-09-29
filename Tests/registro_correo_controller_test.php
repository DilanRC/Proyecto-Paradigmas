<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Application/Controller/RegistroCorreoController.php';

use Application\Controller\RegistroCorreoController;

$consultados = [];
$controlador = new RegistroCorreoController(static function (string $correo) use (&$consultados): bool {
    $consultados[] = $correo;
    return $correo === 'registrado@example.test';
});

$ocupado = $controlador->procesar('POST', ['correoElectronico' => ' Registrado@Example.Test ']);
if ($ocupado['status'] !== 200 || $ocupado['body']['data']['disponible'] !== false) {
    throw new RuntimeException('El correo de Auth debe marcarse como ocupado.');
}
if ($ocupado['body']['message'] !== 'Ese correo ya tiene una cuenta. Inicie sesión para continuar.') {
    throw new RuntimeException('El correo ocupado debe indicar que se inicie sesión.');
}
if ($consultados !== ['registrado@example.test']) {
    throw new RuntimeException('La consulta debe recibir el correo normalizado.');
}

$disponible = $controlador->procesar('POST', ['correoElectronico' => 'nuevo@example.test']);
if ($disponible['status'] !== 200 || $disponible['body']['data']['disponible'] !== true) {
    throw new RuntimeException('El correo sin cuenta debe marcarse como disponible.');
}

$invalido = $controlador->procesar('POST', ['correoElectronico' => 'no-es-correo']);
if ($invalido['status'] !== 422 || $invalido['body']['data'] !== null) {
    throw new RuntimeException('El correo inválido debe rechazarse antes de consultar Auth.');
}

$camposExtra = $controlador->procesar('POST', [
    'correoElectronico' => 'nuevo@example.test',
    'admin' => true,
]);
if ($camposExtra['status'] !== 422) {
    throw new RuntimeException('La consulta debe rechazar campos no permitidos.');
}

echo "Registro correo controller: PASS\n";
