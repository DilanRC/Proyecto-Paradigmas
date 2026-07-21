<?php

declare(strict_types=1);

use Application\Controller\ProducerController;
use Application\Model\Producer;
use Configuration\Database;
use function Configuration\readJsonBody;
use function Configuration\sendJsonResponse;

require_once dirname(__DIR__, 2) . '/Configuration/Configuration.php';
require_once dirname(__DIR__, 2) . '/Configuration/Database.php';
require_once dirname(__DIR__, 2) . '/Application/Model/Producer.php';
require_once dirname(__DIR__, 2) . '/Application/Controller/ProducerController.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'OPTIONS') {
    header('Allow: GET, POST, PUT, DELETE, OPTIONS');
    http_response_code(204);
    exit;
}

$methodsWithBody = ['POST', 'PUT', 'DELETE'];
$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if (in_array($method, $methodsWithBody, true) && $contentType !== 'application/json') {
    sendJsonResponse(['success' => false, 'message' => 'The request body must use application/json.', 'data' => null], 415);
}

try {
    $body = in_array($method, $methodsWithBody, true) ? readJsonBody() : [];
    $controller = new ProducerController(new Producer(Database::getConnection()));
    $response = $controller->process($method, $_GET, $body);
    if ($response['status'] === 405) header('Allow: GET, POST, PUT, DELETE, OPTIONS');
    sendJsonResponse($response['body'], $response['status']);
} catch (UnexpectedValueException $exception) {
    sendJsonResponse(['success' => false, 'message' => $exception->getMessage(), 'data' => null], 400);
} catch (Throwable $exception) {
    error_log(sprintf('[TinderCows] %s in %s:%d', $exception->getMessage(), $exception->getFile(), $exception->getLine()));
    sendJsonResponse(['success' => false, 'message' => 'The request could not be completed. Check the database connection.', 'data' => null], 500);
}
