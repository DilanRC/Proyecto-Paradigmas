<?php

declare(strict_types=1);

// Paquete extraído entero dentro de htdocs: qué queda protegido y cómo arranca.
// Las respuestas HTTP reales se comprueban contra Apache (ver Documentation/Instalacion-XAMPP.md);
// esto evita que un cambio posterior quite la protección sin que nadie lo note.

require_once __DIR__ . '/bootstrap.php';

$raiz = dirname(__DIR__);
$rastreados = array_filter(explode("\n", (string) shell_exec('git -C ' . escapeshellarg($raiz) . ' ls-files')));

test_assert($rastreados !== [], 'git ls-files no devolvió archivos: la prueba necesita el repositorio');

$htaccess = (string) file_get_contents($raiz . '/.htaccess');
test_assert(str_contains($htaccess, 'Require all denied'), 'La raíz debe negar todo por defecto');
test_assert(str_contains($htaccess, '<If "-d %{REQUEST_FILENAME}">'),
    'La excepción de la raíz debe valer para la carpeta en sí a cualquier profundidad, no para una URL de un nivel');
test_assert(!str_contains($htaccess, 'REQUEST_URI'),
    'Una excepción por URL concede archivos privados cuando el proyecto es la raíz del servidor');

// <Files "index.php"> de la raíz también coincide en subcarpetas: cada carpeta interna lo niega.
$carpetas = [];
foreach ($rastreados as $archivo) {
    if (str_contains($archivo, '/')) {
        $carpetas[explode('/', $archivo)[0]] = true;
    }
}
foreach (array_keys($carpetas) as $carpeta) {
    if ($carpeta === 'Public' || str_starts_with($carpeta, '.')) {
        continue;
    }
    $regla = @file_get_contents("{$raiz}/{$carpeta}/.htaccess");
    test_assert(is_string($regla) && str_contains($regla, 'Require all denied') && str_contains($regla, '<Files "*">'),
        "La carpeta interna {$carpeta}/ necesita su .htaccess que niegue también sus index.php");
}
foreach ($rastreados as $archivo) {
    $primera = explode('/', $archivo)[0];
    if (basename($archivo) === 'index.php' && $primera !== 'Public' && $archivo !== 'index.php'
        && !is_file("{$raiz}/{$primera}/.htaccess")) {
        test_assert(false, "{$archivo} quedaría publicado: su carpeta de primer nivel no tiene .htaccess");
    }
}

$publico = (string) file_get_contents($raiz . '/Public/.htaccess');
test_assert(str_contains($publico, 'Require all granted') && str_contains($publico, '%{ENV:TC_BASE}'),
    'Public/ se concede a sí misma y redirige relativo a su carpeta');
test_assert(preg_match('/^RewriteRule \S+ \/\S* \[R=/m', $publico) === 0,
    'Ninguna redirección puede apuntar a la raíz del servidor');

// Rutas absolutas en el frontend rompen la app dentro de una subcarpeta.
foreach ($rastreados as $archivo) {
    if (preg_match('#^Public/js/.*\.js$#', $archivo) !== 1 || str_starts_with($archivo, 'Public/js/vendor/')) {
        continue;
    }
    $codigo = (string) file_get_contents("{$raiz}/{$archivo}");
    test_assert(preg_match('#[\'"`]/(assets|api|css|js|images|vendor)/#', $codigo) === 0,
        "{$archivo} usa una ruta absoluta; debe ser relativa a la base o al módulo");
}

// Primera visita: la portada lleva al asistente y el asistente nunca redirige a sí mismo.
$arranque = (string) file_get_contents($raiz . '/Public/bootstrap.php');
test_assert(str_contains($arranque, "=== 'instalacion.php'") && str_contains($arranque, 'tc_instalador_local_visible()'),
    'La redirección al asistente es solo local y excluye al propio asistente');

// Un esquema degradado (exit 2) avisa pero no impide que producción arranque.
$entrada = (string) file_get_contents($raiz . '/docker/apache/container-entrypoint.sh');
test_assert(str_contains($entrada, '|| estado=$?') && str_contains($entrada, '"$estado" -eq 2'),
    'El arranque debe distinguir el esquema degradado de un fallo de migración');

// El ZIP sale del commit y deja fuera memoria de agentes y respaldos.
$empaque = (string) file_get_contents($raiz . '/Tools/empaquetar-entrega.sh');
test_assert(str_contains($empaque, 'git -C "$RAIZ" archive') && str_contains($empaque, 'status --porcelain')
    && str_contains($empaque, 'Database/Backups'), 'El empaquetado parte del commit y excluye lo interno');

echo "OK entrega_xampp_test: carpetas internas negadas, rutas relativas y reglas de arranque y empaquetado presentes.\n";
