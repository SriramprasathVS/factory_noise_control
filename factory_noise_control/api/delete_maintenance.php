<?php
/**
 * Simple CRUD: Delete Maintenance Request
 * Protected: Admin Only
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST allowed.']);
    exit;
}

// Role restriction: Admin Only
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied. Only Administrators can delete repair records.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$id = filter_var($data['request_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
    exit;
}

try {
    $pdo = getDatabaseConnection(true);
    $stmt = $pdo->prepare('DELETE FROM maintenance_requests WHERE request_id = :id');
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true, 'message' => "Order #{$id} deleted successfully."]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
