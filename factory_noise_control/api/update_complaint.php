<?php
/**
 * Update Public Noise Complaint API
 * Restricted to Administrators only.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_connect.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    $data = $_POST;
}

$complaintId = isset($data['complaint_id']) ? (int)$data['complaint_id'] : 0;
$status = isset($data['status']) ? strtoupper(trim((string)$data['status'])) : '';
$adminNotes = isset($data['admin_notes']) ? trim((string)$data['admin_notes']) : null;

if ($complaintId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid complaint_id is required.']);
    exit;
}

$validStatuses = ['NEW', 'INVESTIGATING', 'RESOLVED'];
if (!in_array($status, $validStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status. Must be NEW, INVESTIGATING, or RESOLVED.']);
    exit;
}

try {
    $pdo = getDatabaseConnection(true);
    $currentUser = getCurrentUser();
    $adminName = $currentUser['name'] ?? 'Plant Manager';

    if ($status === 'RESOLVED') {
        $stmt = $pdo->prepare("
            UPDATE public_complaints
            SET status = :status,
                admin_notes = COALESCE(:notes, admin_notes),
                resolved_by = :resolved_by,
                resolved_at = NOW()
            WHERE complaint_id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':notes' => $adminNotes,
            ':resolved_by' => $adminName,
            ':id' => $complaintId
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE public_complaints
            SET status = :status,
                admin_notes = COALESCE(:notes, admin_notes)
            WHERE complaint_id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':notes' => $adminNotes,
            ':id' => $complaintId
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => "Complaint #$complaintId status updated to $status."
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while updating complaint.'
    ]);
}
