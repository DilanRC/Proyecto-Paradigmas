<?php

declare(strict_types=1);

/**
 * Siembra publicaciones de demostración para ver Explorar con contenido.
 *
 * Todo lo que crea queda marcado con origen DEMO_EXPLORAR, que es lo que usa
 * --limpiar para borrarlo sin tocar datos reales. No usar contra producción.
 *
 *   docker compose exec -T app php Tools/seed-publicaciones-demo.php
 *   docker compose exec -T app php Tools/seed-publicaciones-demo.php --limpiar
 */

use Application\Service\PublicacionesDemo;
use Configuration\Database;

$raiz = dirname(__DIR__);
require_once $raiz . '/Configuration/Configuration.php';
require_once $raiz . '/Configuration/Database.php';
require_once $raiz . '/Application/Service/PublicacionesDemo.php';

const ORIGEN_DEMO = PublicacionesDemo::ORIGEN;

$conexion = Database::getConnection();
$limpiar = in_array('--limpiar', $argv, true);

if ($limpiar) {
    $conexion->beginTransaction();
    try {
        $publicaciones = $conexion->query(
            "SELECT tbanimalpublicacionid, tbanimalid FROM tbanimalpublicacion
             WHERE tbanimalpublicacionorigen = '" . ORIGEN_DEMO . "'"
        )->fetchAll();
        foreach ($publicaciones as $fila) {
            $conexion->prepare('DELETE FROM tbanimalpublicacionestadoperiodo
                WHERE tbanimalpublicacionid = :id')->execute(['id' => $fila['tbanimalpublicacionid']]);
            $conexion->prepare('DELETE FROM tbanimalpublicacion
                WHERE tbanimalpublicacionid = :id')->execute(['id' => $fila['tbanimalpublicacionid']]);
            $conexion->prepare('DELETE FROM tbanimalproduccionsalud
                WHERE tbanimalid = :id')->execute(['id' => $fila['tbanimalid']]);
            $conexion->prepare('DELETE FROM tbanimal
                WHERE tbanimalid = :id')->execute(['id' => $fila['tbanimalid']]);
        }
        $conexion->commit();
        printf("Limpiadas %d publicaciones de demostración.\n", count($publicaciones));
    } catch (Throwable $error) {
        if ($conexion->inTransaction()) $conexion->rollBack();
        throw $error;
    }
    exit(0);
}

$creadas = PublicacionesDemo::sembrar($conexion);
printf("Sembradas %d publicaciones de demostración.\n", $creadas);
