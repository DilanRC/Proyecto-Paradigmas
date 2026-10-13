<?php

declare(strict_types=1);

// MVC (observación de Calidad): los controladores validan y coordinan; el SQL
// vive en los modelos. Falla si un controlador vuelve a preparar o ejecutar una
// consulta por su cuenta.
$violaciones = [];
foreach (glob(dirname(__DIR__) . '/Application/Controller/*.php') ?: [] as $archivo) {
    foreach (file($archivo) ?: [] as $numero => $linea) {
        if (preg_match('/->\s*(prepare|query|exec)\s*\(/', $linea)) {
            $violaciones[] = basename($archivo) . ':' . ($numero + 1) . ' ' . trim($linea);
        }
    }
}
if ($violaciones !== []) {
    fwrite(STDERR, "SQL dentro de un controlador; muévalo a su modelo:\n" . implode("\n", $violaciones) . "\n");
    exit(1);
}
echo "OK controladores_sin_sql_test: ningún controlador prepara ni ejecuta SQL.\n";
