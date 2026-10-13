<?php

declare(strict_types=1);

date_default_timezone_set('Europe/Berlin');
require_once dirname(__DIR__) . '/Configuration/Configuration.php';
require_once dirname(__DIR__) . '/Application/Auth/ActorContext.php';
require_once dirname(__DIR__) . '/Application/HttpException.php';
require_once dirname(__DIR__) . '/Application/Service/ValidacionService.php';

function runtime_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

runtime_assert(date_default_timezone_get() === 'UTC', 'Las fechas de negocio deben usar UTC independientemente de php.ini');

foreach ([[[], true], [[0 => 'a', 1 => 'b'], true], [[1 => 'a'], false],
    [[1 => 'b', 0 => 'a'], false], [['nombre' => 'a'], false]] as [$value, $expected]) {
    runtime_assert(array_is_list($value) === $expected, 'Clasificación de listas incorrecta');
}

$actor = Application\Auth\ActorContext::usuarioVerificado(7, 'test-subject', 'TEST@example.test', 'authenticated');
runtime_assert($actor->personaId === 7 && $actor->correoElectronico === 'test@example.test', 'Lectura del actor');
runtime_assert(isset($actor->personaId) && !isset($actor->inexistente), 'isset del actor');
runtime_assert(json_decode(json_encode($actor), true)['personaId'] === 7, 'Serialización del actor');

$http = new Application\HttpException('Prueba', 422, null, ['nombre' => 'Obligatorio']);
$validation = new Application\Service\ValidacionException('Prueba', ['nombre' => 'Obligatorio']);
foreach ([[$actor, 'personaId', 999], [$http, 'estadoHttp', 200], [$validation, 'errores', []]] as [$object, $field, $value]) {
    $before = $object->$field;
    foreach (['write', 'unset'] as $operation) {
        $rejected = false;
        try {
            if ($operation === 'write') $object->$field = $value;
            else unset($object->$field);
        } catch (Error $error) {
            $rejected = true;
        }
        runtime_assert($rejected && $object->$field === $before, 'Se alteró una propiedad de solo lectura');
    }
}

echo 'OK runtime_compatibility_test: listas, identidad inmutable, errores y serialización en PHP ', PHP_VERSION, ".\n";
