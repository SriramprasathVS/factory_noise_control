<?php
/**
 * Update Maintenance Request Status & Assignment
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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$id = filter_var($data['request_id'] ?? null, FILTER_VALIDATE_INT);
$status = isset($data['status']) ? trim((string)$data['status']) : null;
$assignTo = isset($data['assigned_to']) ? trim((string)$data['assigned_to']) : null;

if (!$id || $id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
    exit;
}

try {
    $pdo = getDatabaseConnection(true);

    // Ensure assigned_to, resolved_by, and resolved_at exist
    try {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN assigned_to VARCHAR(50) DEFAULT 'unassigned'");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN resolved_by VARCHAR(100) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN resolved_at DATETIME DEFAULT NULL");
    } catch (Exception $e) {}

    $currentUser = getCurrentUser();
    $currentUserName = $currentUser ? ($currentUser['name'] . ' (' . $currentUser['username'] . ')') : 'Technician';

    $updates = [];
    $params = [':id' => $id];

    if ($status !== null) {
        $allowed = ['PENDING', 'IN_PROGRESS', 'RESOLVED'];
        if (!in_array($status, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
            exit;
        }

        // Users can mark work as done only (RESOLVED)
        if (!isAdmin() && $status !== 'RESOLVED') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Users can mark work as done only.']);
            exit;
        }

        $updates[] = 'status = :status';
        $params[':status'] = $status;

        if ($status === 'RESOLVED') {
            $updates[] = 'resolved_by = :resolved_by';
            $params[':resolved_by'] = $currentUserName;
            $updates[] = 'resolved_at = NOW()';
        } else {
            $updates[] = 'resolved_by = NULL';
            $updates[] = 'resolved_at = NULL';
        }
    }

    if ($assignTo !== null) {
        // Only Admin can assign or reassign work orders
        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Only Admin is authorized to assign or reassign work orders.']);
            exit;
        }

        $allowedUsers = ['unassigned', 'user1', 'user2', 'admin'];
        if (!in_array($assignTo, $allowedUsers, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid user assignment.']);
            exit;
        }
        $updates[] = 'assigned_to = :assigned_to';
        $params[':assigned_to'] = $assignTo;
    }

    if (empty($updates)) {
        echo json_encode(['success' => true, 'message' => 'No updates requested.']);
        exit;
    }

    $sql = 'UPDATE maintenance_requests SET ' . implode(', ', $updates) . ' WHERE request_id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $msg = 'Request updated.';
    if ($status === 'RESOLVED') {
        $msg = "Job #{$id} reported as DONE! Reflected on Admin dashboard as Fixed / Done.";
    } elseif ($status !== null) {
        $msg = "Job #{$id} status changed to {$status}.";
    } elseif ($assignTo !== null) {
        $userNamesMap = [
            'admin' => 'Plant Manager (Admin)',
            'user1' => 'Tech Alex (Acoustic)',
            'user2' => 'Sarah (Safety Operator)',
            'unassigned' => 'Unassigned (Open Pool)'
        ];
        $targetName = $userNamesMap[$assignTo] ?? $assignTo;
        $msg = "Job #{$id} successfully assigned to {$targetName}.";
    }

    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
