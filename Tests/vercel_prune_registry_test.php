<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
require_once "{$root}/Tools/vercel-prune-registry.php";

$imagen = static fn (string $id, string $fecha, array $tags = []): array => [
    'id' => $id,
    'createdAt' => $fecha,
    'tags' => $tags,
];

$catalogo = [
    $imagen('img_vieja', '2026-08-22T03:57:42.598Z', ['6895951ad745']),
    $imagen('img_media', '2026-08-31T22:02:57.713Z', ['3242aada195d']),
    $imagen('img_nueva', '2026-09-01T17:03:43.593Z', ['9fd9ba349c39']),
    $imagen('img_reciente', '2026-09-01T17:02:30.433Z', ['cd16f905563a']),
];

test_same(['img_media', 'img_vieja'], vercel_registry_borrables($catalogo, 2, []),
    'Conservar 2 debe dejar las dos más recientes y borrar el resto por antigüedad');
test_same([], vercel_registry_borrables($catalogo, 10, []),
    'Si el cupo supera al inventario no hay nada que borrar');

// El caso que rompió producción: la imagen que sirve el alias era la más
// antigua del registro, así que cualquier poda por fecha la habría borrado.
test_same(['img_media'], vercel_registry_borrables($catalogo, 2, ['6895951ad745']),
    'La imagen de producción nunca se borra aunque sea la más antigua');
test_same(['img_media', 'img_vieja'], vercel_registry_borrables($catalogo, 2, ['no-existe']),
    'Una etiqueta protegida inexistente no debe alterar la selección');

// Protegida no gasta cupo: conservar=1 debe dejar la protegida más la más nueva.
test_same(['img_reciente', 'img_media'], vercel_registry_borrables($catalogo, 1, ['6895951ad745']),
    'Las imágenes protegidas no consumen el cupo de conservación');

test_same([], vercel_registry_borrables([], 5, []), 'Un registro vacío no produce borrados');

$sinId = [$imagen('', '2026-09-01T17:03:43.593Z'), $imagen('img_ok', '2026-08-01T00:00:00.000Z')];
test_same([], vercel_registry_borrables($sinId, 1, []),
    'Una entrada sin id se ignora en vez de emitir un borrado vacío');

$empate = [
    $imagen('img_b', '2026-09-01T17:03:43.593Z'),
    $imagen('img_a', '2026-09-01T17:03:43.593Z'),
];
test_same(vercel_registry_borrables($empate, 1, []), vercel_registry_borrables(array_reverse($empate), 1, []),
    'Con fechas empatadas la selección debe ser estable ante el orden de entrada');

$falloCupo = false;
try {
    vercel_registry_borrables($catalogo, 0, []);
} catch (InvalidArgumentException) {
    $falloCupo = true;
}
test_assert($falloCupo, 'Conservar 0 debe rechazarse: vaciar el registro tumba producción');

// El script solo decide; la parte de red vive en el envoltorio y no se ejecuta
// al incluir el archivo.
$salida = [];
$codigo = 0;
exec(sprintf('cd %s && printf %s | php Tools/vercel-prune-registry.php --conservar=2 --proteger=6895951ad745',
    escapeshellarg($root),
    escapeshellarg(json_encode(['images' => $catalogo], JSON_THROW_ON_ERROR))), $salida, $codigo);
test_same(0, $codigo, 'La invocación por CLI debe terminar sin error');
test_same(['img_media'], $salida, 'La CLI debe emitir un id por línea respetando las protegidas');

$envoltorio = file_get_contents("{$root}/Tools/vercel-prune-registry.sh");
test_assert(str_contains($envoltorio, 'vcr image rm'),
    'El envoltorio debe borrar mediante la CLI de Vercel');
test_assert(str_contains($envoltorio, '--simular'),
    'El envoltorio debe ofrecer una pasada de simulación antes de borrar');
test_assert(str_contains($envoltorio, '"READY"'),
    'El envoltorio debe proteger solo producciones listas');

echo "OK vercel_prune_registry_test: poda determinista con producción protegida.\n";
