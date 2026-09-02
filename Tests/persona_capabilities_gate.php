<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$persona = file_get_contents($root . '/Application/Model/Persona.php');
$check = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$check($persona !== false, 'No se pudo leer Persona.php.');

// Comprador salió de la lista: dejó de ser una capacidad con modelo propio y
// pasó a ser una clasificación del Productor (DEC-DBREADY-008). Su modelo se
// retiró en el paso (d) y no debe volver.
$check(!file_exists("{$root}/Application/Model/Comprador.php"),
    'El modelo legacy de comprador volvió a aparecer.');
$check(!file_exists("{$root}/Application/Controller/CompradorController.php"),
    'El CRUD legacy de comprador volvió a aparecer.');

foreach (['Productor', 'Transportista'] as $capacidad) {
    $modelo = file_get_contents("{$root}/Application/Model/{$capacidad}.php");
    $check($modelo !== false, "No se pudo leer {$capacidad}.php.");
    $check(str_contains($modelo, 'INNER JOIN tbpersona'), "{$capacidad} no consulta tbpersona mediante JOIN.");
    $check(str_contains($modelo, 'tbpersonaid'), "{$capacidad} no enlaza tbpersonaid.");
    $check(!preg_match('/INSERT INTO tb(?:productor|comprador|transportista)[\s\S]{0,250}identificacionnumero/', $modelo),
        "{$capacidad} todavía inserta identidad duplicada.");
    $check(!str_contains($modelo, 'DELETE FROM'), "{$capacidad} contiene borrado físico.");
}

$check(str_contains($persona, "'tindercows_persona_alta'"), 'Persona no serializa altas.');
$check(str_contains($persona, 'datos personales diferentes'), 'Persona no detecta datos incompatibles.');
$check(str_contains($persona, 'tbpersonaestado'), 'Persona no controla el estado global.');
echo "persona_capabilities_gate: OK\n";
