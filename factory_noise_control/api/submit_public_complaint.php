<?php
/**
 * Public Noise Complaint Submission API
 * Allows residents and community members to file noise grievances.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    // Fallback to $_POST if submitted as form data
    $data = $_POST;
}

$citizenName = trim((string)($data['citizen_name'] ?? ''));
$phoneNumber = trim((string)($data['phone_number'] ?? ''));
$locationAddress = trim((string)($data['location_address'] ?? ''));
$noiseType = trim((string)($data['noise_type'] ?? 'Loud Industrial Hum'));
$complaintDetails = trim((string)($data['complaint_details'] ?? ''));
$latitude = isset($data['latitude']) && is_numeric($data['latitude']) ? (float)$data['latitude'] : 13.0827000;
$longitude = isset($data['longitude']) && is_numeric($data['longitude']) ? (float)$data['longitude'] : 80.2707000;

// Validate inputs
if (mb_strlen($citizenName) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide your full name.']);
    exit;
}

if (mb_strlen($phoneNumber) < 7) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide a valid contact phone number.']);
    exit;
}

if (mb_strlen($locationAddress) < 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide your neighborhood location or street address.']);
    exit;
}

if (mb_strlen($complaintDetails) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please provide details about the noise (at least 10 characters).']);
    exit;
}

try {
    $pdo = getDatabaseConnection(true);

    $stmt = $pdo->prepare("
        INSERT INTO public_complaints (
            citizen_name,
            phone_number,
            location_address,
            latitude,
            longitude,
            noise_type,
            complaint_details,
            status
        ) VALUES (
            :name,
            :phone,
            :location,
            :lat,
            :lng,
            :type,
            :details,
            'NEW'
        )
    ");

    $stmt->execute([
        ':name' => $citizenName,
        ':phone' => $phoneNumber,
        ':location' => $locationAddress,
        ':lat' => $latitude,
        ':lng' => $longitude,
        ':type' => $noiseType,
        ':details' => $complaintDetails
    ]);

    $complaintId = (int)$pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'complaint_id' => $complaintId,
        'reference_code' => 'NC-' . str_pad((string)$complaintId, 4, '0', STR_PAD_LEFT),
        'message' => 'Your complaint has been submitted successfully to the factory environmental compliance team.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error while recording your complaint. Please try again later.'
    ]);
}
