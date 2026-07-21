<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$requiredPaths = [
    'Application/Controller/ProducerController.php', 'Application/Model/Producer.php',
    'Configuration/Configuration.php', 'Configuration/Database.php',
    'Database/SqlScripts/001_create_producers.sql', 'Public/api/producers.php',
    'Public/js/producers.js', 'Public/css/styles.css',
];
foreach ($requiredPaths as $path) {
    if (!is_file($root . '/' . $path)) {
        throw new RuntimeException("Missing English path: {$path}");
    }
}
$requiredTerms = ['namespace Application\\Controller', 'final class ProducerController', 'function process', 'CREATE TABLE IF NOT EXISTS producers', 'producer_id', 'const API_URL = \'api/producers.php\'', 'success'];
$source = '';
foreach ($requiredPaths as $path) $source .= file_get_contents($root . '/' . $path);
foreach ($requiredTerms as $term) {
    if (!str_contains($source, $term)) throw new RuntimeException("Missing migrated term: {$term}");
}
echo "Naming migration gate passed.\n";
