<?php

declare(strict_types=1);

// Postgres (producción) pliega a minúscula los alias sin comillas: `AS fincaId`
// llega como `fincaid` y $fila['fincaId'] no existe. En MySQL (local) sí
// funciona, así que el error solo aparece en producción. Los alias SQL van en
// minúscula y la clave pública camelCase se arma en PHP.
$raiz = dirname(__DIR__);
$errores = [];
$archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/Application'));
foreach ($archivos as $archivo) {
    if ($archivo->getExtension() !== 'php') continue;
    foreach (file($archivo->getPathname()) as $numero => $linea) {
        if (preg_match('/\bAS\s+[a-z_]+[A-Z]\w*/', $linea, $coincidencia)) {
            $errores[] = sprintf('%s:%d  %s', substr($archivo->getPathname(), strlen($raiz) + 1),
                $numero + 1, $coincidencia[0]);
        }
    }
}

if ($errores !== []) {
    fwrite(STDERR, "Alias SQL con mayúsculas (rompen en Postgres):\n  " . implode("\n  ", $errores) . "\n");
    exit(1);
}
echo "OK: ningún alias SQL usa mayúsculas.\n";
