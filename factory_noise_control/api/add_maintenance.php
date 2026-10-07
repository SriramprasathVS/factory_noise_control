<?php
/**
 * Factory Acoustic Monitoring and Material Compliance System
 * API Endpoint: api/add_maintenance.php
 * 
 * Secure HTTP POST Handler with User Assignment:
 * - Assign work to specific user (user1, user2, admin, unassigned)
 * - Strict input sanitization
 * - Parameterized SQL queries using PDO Prepared Statements
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Only HTTP POST is accepted.'
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data) || empty($data)) {
    $data = $_POST;
}

// Field extraction
$zoneIdRaw        = $data['zone_id'] ?? null;
$equipmentTagRaw  = trim((string)($data['equipment_tag'] ?? ''));
$priorityRaw      = trim((string)($data['priority'] ?? ''));
$reportedByRaw    = trim((string)($data['reported_by'] ?? ''));
$descriptionRaw   = trim((string)($data['issue_description'] ?? ''));
$assignedToRaw    = trim((string)($data['assigned_to'] ?? 'unassigned'));

$errors = [];

// 1. Zone ID validation
$zoneId = filter_var($zoneIdRaw, FILTER_VALIDATE_INT);
if ($zoneId === false || $zoneId <= 0) {
    $errors['zone_id'] = 'A valid factory zone is required.';
}

// 2. Equipment / Machine Tag validation (flexible)
$tagClean = strtoupper($equipmentTagRaw);
if (empty($tagClean)) {
    $errors['equipment_tag'] = 'Machine or equipment name is required.';
} elseif (!preg_match('/^[A-Z0-9\-_ ]{2,20}$/', $tagClean)) {
    $errors['equipment_tag'] = 'Machine name should be 2 to 20 letters/numbers (e.g. STAMP-01).';
}

// 3. Priority validation (Whitelist)
$allowedPriorities = ['LOW', 'MEDIUM', 'HIGH', 'EMERGENCY'];
if (!in_array($priorityRaw, $allowedPriorities, true)) {
    $errors['priority'] = 'Invalid priority.';
}

// 4. Reporter Name validation
$reportedBy = htmlspecialchars($reportedByRaw, ENT_QUOTES, 'UTF-8');
if (mb_strlen($reportedBy) < 2) {
    $errors['reported_by'] = 'Please enter your name.';
}

// 5. Issue Description validation
$description = htmlspecialchars($descriptionRaw, ENT_QUOTES, 'UTF-8');
if (mb_strlen($description) < 10) {
    $errors['issue_description'] = 'Please write at least 10 characters describing the problem.';
}

// 6. Assigned User: Only Admin can assign; users report to open pool for Admin to assign
if (isAdmin()) {
    $allowedUsers = ['unassigned', 'user1', 'user2', 'admin'];
    $assignedTo = in_array($assignedToRaw, $allowedUsers, true) ? $assignedToRaw : 'unassigned';
} else {
    $assignedTo = 'unassigned';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed for one or more fields.',
        'errors'  => $errors
    ]);
    exit;
}

try {
    $pdo = getDatabaseConnection(false);

    if (!$pdo) {
        http_response_code(201);
        echo json_encode([
            'success'   => true,
            'message'   => 'Work order received in demo mode.',
            'order_id'  => mt_rand(600, 999),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    // Ensure assigned_to column exists
    try {
        $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN assigned_to VARCHAR(50) DEFAULT 'unassigned'");
    } catch (Exception $e) {}

    // Verify foreign key integrity
    $checkStmt = $pdo->prepare('SELECT zone_id, zone_name FROM factory_zones WHERE zone_id = :zid LIMIT 1');
    $checkStmt->execute([':zid' => $zoneId]);
    $zoneRecord = $checkStmt->fetch();

    if (!$zoneRecord) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Referenced factory zone does not exist.'
        ]);
        exit;
    }

    // Parameterized INSERT query including assigned_to
    $sql = 'INSERT INTO maintenance_requests 
              (zone_id, equipment_tag, priority, issue_description, reported_by, assigned_to, status, created_at)
            VALUES 
              (:zone_id, :equipment_tag, :priority, :issue_description, :reported_by, :assigned_to, :status, NOW())';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':zone_id'           => $zoneId,
        ':equipment_tag'     => $tagClean,
        ':priority'          => $priorityRaw,
        ':issue_description' => $description,
        ':reported_by'       => $reportedBy,
        ':assigned_to'       => $assignedTo,
        ':status'            => 'PENDING'
    ]);

    $insertedId = (int)$pdo->lastInsertId();

    // If Priority is HIGH or EMERGENCY, log alert into noise_logs
    if ($priorityRaw === 'HIGH' || $priorityRaw === 'EMERGENCY') {
        $simulatedDb = ($priorityRaw === 'EMERGENCY') ? 91.50 : 86.80;
        $alertStmt = $pdo->prepare(
            'INSERT INTO noise_logs 
              (zone_id, sensor_code, decibel_level, frequency_hz, compliance_status, material_observation, logged_at)
             VALUES 
              (:zone_id, :sensor_code, :decibel_level, 120, :status, :obs, NOW())'
        );
        $alertStmt->execute([
            ':zone_id'       => $zoneId,
            ':sensor_code'   => 'MIC-' . $tagClean,
            ':decibel_level' => $simulatedDb,
            ':status'        => 'CRITICAL',
            ':obs'           => 'Automated alert from Work Order #' . $insertedId
        ]);
    }

    http_response_code(201);
    echo json_encode([
        'success'     => true,
        'message'     => "Repair job #{$insertedId} created and assigned.",
        'order_id'    => $insertedId,
        'assigned_to' => $assignedTo,
        'zone_name'   => $zoneRecord['zone_name'],
        'timestamp'   => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log("[Database Insertion Error] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while saving repair request.'
    ]);
}
