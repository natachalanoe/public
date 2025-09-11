<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../models/RoomModel.php';

header('Content-Type: application/json');

if (!isset($_GET['site_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Site ID is required']);
    exit;
}

$site_id = (int)$_GET['site_id'];

try {
    $roomModel = new RoomModel($db);
    $rooms = $roomModel->getRoomsBySiteId($site_id);
    echo json_encode($rooms);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} 