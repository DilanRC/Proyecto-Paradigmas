<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$archivos = array_map(static fn(string $n): string => (string) file_get_contents($root . $n), [
    '/Application/Model/Persona.php', '/Application/Model/Productor.php',
    '/Application/Model/Comprador.php', '/Application/Model/Transportista.php',
]);
$todo = implode("\n", $archivos);
$criterios = [
    'identidad central' => str_contains($todo, 'INSERT INTO tbpersona'),
    'reutilización por identificación' => str_contains($todo, 'obtenerOCrear'),
    'bloqueo transversal' => substr_count($todo, 'tindercows_persona_alta') >= 2,
    'estado efectivo' => substr_count($todo, 'tbpersonaestado') >= 4,
    'sin borrado físico' => !str_contains($todo, 'DELETE FROM'),
    'conflicto de datos' => str_contains($todo, 'datos personales diferentes'),
];
$aprobados = count(array_filter($criterios));
$puntaje = (int) round(100 * $aprobados / count($criterios));
foreach ($criterios as $nombre => $ok) echo sprintf("[%s] %s\n", $ok ? 'OK' : 'FALLO', $nombre);
echo "Puntaje: {$puntaje}% (umbral: 100%)\n";
if ($puntaje !== 100) exit(1);
