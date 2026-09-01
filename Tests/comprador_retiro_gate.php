<?php

declare(strict_types=1);

/**
 * Gate estructural del paso (d) (DEC-DBREADY-008).
 *
 * No necesita base de datos: impide que un refactor vuelva a introducir el CRUD
 * manual de Comprador, confunda Productor con Vendedor o adelante el DROP de la
 * tabla legacy antes del paso (e).
 */

$root = dirname(__DIR__);
$check = static function (bool $condicion, string $mensaje): void {
    if (!$condicion) {
        throw new RuntimeException($mensaje);
    }
};
$leer = static function (string $ruta) use ($root): string {
    $contenido = file_get_contents($root . '/' . $ruta);
    if ($contenido === false) {
        throw new RuntimeException("No se pudo leer {$ruta}");
    }

    return $contenido;
};

foreach (['Application/Model/Comprador.php', 'Application/Controller/CompradorController.php'] as $retirado) {
    $check(!file_exists($root . '/' . $retirado), "Volvió el CRUD legacy: {$retirado}");
}

$controlador = $leer('Application/Controller/CompradorConsultaController.php');
$api = $leer('Public/api/compradores.php');
$vista = $leer('Application/View/compradores/index.php');
$js = $leer('Public/js/compradores.js');
$relaciones = $leer('Public/js/shared/capacidades.js');
$decision = $leer('Documentation/DEC-DBREADY-008.md');
$sql = $leer('Database/SqlScripts/000instalacioncompleta.sql');

$check(str_contains($controlador, 'listarClasificados'),
    'CompradorConsultaController debe leer la clasificación histórica.');
$check(str_contains($controlador, "'POST', 'PUT', 'DELETE', 'PATCH'"),
    'El controlador debe rechazar todos los métodos de escritura.');
$check(str_contains($api, "header('Allow: GET, OPTIONS');"),
    'La API debe declarar GET/OPTIONS como únicos métodos.');
$check(str_contains($api, "if ($metodo !== 'GET')"),
    'La API debe rechazar escrituras antes de abrir la conexión.');

foreach (['id="crear-comprador"', 'id="formulario-comprador"', 'id="modal-desactivar"'] as $selector) {
    $check(!str_contains($vista, $selector), "La vista recuperó una acción administrativa: {$selector}");
}
foreach (["'POST'", "'PUT'", "'DELETE'", "'PATCH'", 'buildCompradorPayload'] as $escritura) {
    $check(!str_contains($js, $escritura), "El JS de Compradores volvió a escribir: {$escritura}");
}
$check(str_contains($js, 'clasificadoDesde') && str_contains($js, 'personaEstado'),
    'El panel debe mostrar fecha de clasificación y disponibilidad de Persona.');

$check(str_contains($relaciones, "clave: 'comprador'") && str_contains($relaciones, 'derivada: true'),
    'Comprador debe mantenerse marcado como clasificación derivada.');
$check(!str_contains($relaciones, "alias: 'vendedor'"),
    'Productor no puede ser alias de Vendedor; VENDEDOR es otra clasificación.');

foreach (['clasificación derivada, nunca administrativa', 'T10', 'Paso (e)', 'tbcomprador'] as $termino) {
    $check(str_contains($decision, $termino), "DEC-DBREADY-008 no documenta {$termino}");
}

// El paso (d) NO ejecuta el paso (e): la tabla legacy sigue disponible para
// auditoría/backfill hasta que la migración real esté verificada y respaldada.
$check((bool) preg_match('/CREATE TABLE IF NOT EXISTS\s+tbcomprador\s*\(/i', $sql),
    'tbcomprador se eliminó antes del paso (e).');

// No debe existir una entidad Vendedor en el esquema objetivo.
$check(!(bool) preg_match('/CREATE TABLE IF NOT EXISTS\s+tbvendedor\s*\(/i', $sql),
    'El esquema volvió a crear tbvendedor.');

echo "OK comprador_retiro_gate: paso (d) read-only, sin CRUD manual, sin alias Vendedor y tbcomprador reservado para (e).\n";
