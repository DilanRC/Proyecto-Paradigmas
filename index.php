<?php

declare(strict_types=1);

// La aplicación se publica desde Public/. Quien abre la carpeta del proyecto llega ahí.
$carpeta = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
header('Location: ' . $carpeta . '/Public/', true, 302);
