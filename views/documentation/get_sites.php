<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../models/SiteModel.php';

header('Content-Type: application/json');

if (!isset($_GET['client_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Client ID is required']);
    exit;
}

$client_id = (int)$_GET['client_id'];

try {
    $siteModel = new SiteModel($db);
    $sites = $siteModel->getSitesByClientId($client_id);
    echo json_encode($sites);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} 