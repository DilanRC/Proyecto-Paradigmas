<?php

declare(strict_types=1);

// El login puede cambiar su gate y sus imports versionados durante una sesión
// abierta. Evita que una pestaña reutilice una vista HTML vieja y vuelva a
// ejecutar un módulo de autenticación obsoleto.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/Application/View/login/index.php';
