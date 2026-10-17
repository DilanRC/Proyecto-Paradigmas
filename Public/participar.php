<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
// Misma vista y lógica que Ajustes, mostrando solo "Más formas de participar".
$soloParticipacion = true;
require_once dirname(__DIR__) . '/Application/View/ajustes/index.php';
