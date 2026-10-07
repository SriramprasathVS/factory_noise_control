<?php
/**
 * Simple API: Add Noise Reading to noise_logs
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: $_POST;

$zoneId = filter_var($data['zone_id'] ?? null, FILTER_VALIDATE_INT);
$sensorCode = trim((string)($data['sensor_code'] ?? ''));
$decibels = filter_var($data['decibel_level'] ?? null, FILTER_VALIDATE_FLOAT);
$notes = trim((string)($data['notes'] ?? 'Regular check'));

if (!$zoneId || $zoneId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please select a factory area.']);
    exit;
}

if (empty($sensorCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Sensor or meter name is required.']);
    exit;
}

if ($decibels === false || $decibels < 30.0 || $decibels > 140.0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Decibel level must be between 30 and 140 dB.']);
    exit;
}

// Determine status
$status = 'COMPLIANT';
if ($decibels >= 85.0) {
    $status = 'CRITICAL';
} elseif ($decibels >= 80.0) {
    $status = 'ELEVATED';
}

try {
    $pdo = getDatabaseConnection(true);

    $sql = 'INSERT INTO noise_logs 
              (zone_id, sensor_code, decibel_level, frequency_hz, compliance_status, material_observation, logged_at)
            VALUES 
              (:zid, :code, :db, 250, :status, :obs, NOW())';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':zid'    => $zoneId,
        ':code'   => strtoupper($sensorCode),
        ':db'     => $decibels,
        ':status' => $status,
        ':obs'    => htmlspecialchars($notes, ENT_QUOTES, 'UTF-8')
    ]);

    $newId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success'  => true,
        'message'  => "Noise reading saved successfully (#{$newId}).",
        'log_id'   => $newId,
        'status'   => $status
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
