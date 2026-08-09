<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$controller = file_get_contents("{$root}/Application/Controller/ProductorController.php");
$models = [
    'productor' => file_get_contents("{$root}/Application/Model/Productor.php"),
    'direccion' => file_get_contents("{$root}/Application/Model/ProductorDireccion.php"),
    'finca' => file_get_contents("{$root}/Application/Model/ProductorFinca.php"),
];
$section = static function (string $source, string $start, string $end): string {
    $startPosition = strpos($source, $start);
    $endPosition = strpos($source, $end, $startPosition === false ? 0 : $startPosition + strlen($start));
    if ($startPosition === false || $endPosition === false) {
        return '';
    }
    return substr($source, $startPosition, $endPosition - $startPosition);
};
$create = $section($controller, 'private function crear(', 'private function actualizar(');
$update = $section($controller, 'private function actualizar(', 'public function crearDireccion(');
$createDirection = $section($controller, 'public function crearDireccion(', 'private function desactivar(');
$appearsBefore = static function (string $source, string $first, string $second): bool {
    $firstPosition = strpos($source, $first);
    $secondPosition = strpos($source, $second);
    return $firstPosition !== false && $secondPosition !== false && $firstPosition < $secondPosition;
};
$checks = [];
$evaluate = static function (string $criterion, bool $passes, string $evidence) use (&$checks): void {
    $checks[] = compact('criterion', 'passes', 'evidence');
};

$createOrder = array_map(static fn (string $call): int|false => strpos($create, $call), [
    'productor->ejecutarConBloqueoAlta(',
    'direccion->ejecutarConBloqueoAlta(',
    'fincas->ejecutarConBloqueoAlta(',
    'transaccion(',
]);
$evaluate(
    'orden_alta',
    !in_array(false, $createOrder, true) && $createOrder[0] < $createOrder[1]
        && $createOrder[1] < $createOrder[2] && $createOrder[2] < $createOrder[3],
    'crear() anida productor -> dirección -> finca -> transacción',
);
$evaluate(
    'actualizacion_bloqueada',
    $appearsBefore($update, 'fincas->ejecutarConBloqueoAlta(', 'transaccion('),
    'actualizar() retiene el bloqueo de finca durante la transacción',
);
$evaluate(
    'direccion_bloqueada',
    $appearsBefore($createDirection, 'direccion->ejecutarConBloqueoAlta(', 'transaccion('),
    'crearDireccion() retiene el bloqueo de dirección durante la transacción',
);
foreach ([
    'productor' => 'tbproductorid',
    'direccion' => 'tbproductordireccionid',
    'finca' => 'tbfincaid',
] as $model => $id) {
    $evaluate(
        "max_{$model}",
        str_contains($models[$model], "MAX({$id})") && str_contains($models[$model], ' + 1'),
        "{$model} conserva MAX({$id}) + 1 en PHP",
    );
    $evaluate(
        "wrapper_{$model}",
        str_contains($models[$model], 'public function ejecutarConBloqueoAlta(callable $operacion): mixed')
            && str_contains($models[$model], 'finally {')
            && str_contains($models[$model], '$this->liberarBloqueoAlta();'),
        "{$model} libera el bloqueo en finally",
    );
}

$passed = count(array_filter($checks, static fn (array $check): bool => $check['passes']));
$score = (int) round(100 * $passed / count($checks));
echo json_encode([
    'eval' => 'bloqueos_consecutivos_manuales',
    'score' => $score,
    'threshold' => 100,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
if ($score < 100) {
    exit(1);
}
