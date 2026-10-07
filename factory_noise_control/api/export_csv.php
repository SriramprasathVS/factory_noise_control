<?php
/**
 * Simple CSV Export of Noise Logs
 */
declare(strict_types=1);

require_once __DIR__ . '/../db_connect.php';

$pdo = getDatabaseConnection(false);
if (!$pdo) {
    die("Database connection failed.");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=factory_noise_report_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Log ID', 'Sensor Code', 'Factory Area', 'Sound Level (dB)', 'Observation', 'Status', 'Timestamp']);

$sql = '
    SELECT 
        nl.log_id,
        nl.sensor_code,
        fz.zone_name,
        nl.decibel_level,
        nl.material_observation,
        nl.compliance_status,
        nl.logged_at
    FROM noise_logs nl
    JOIN factory_zones fz ON nl.zone_id = fz.zone_id
    ORDER BY nl.log_id DESC
';

$stmt = $pdo->query($sql);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $statusSimple = ($row['compliance_status'] === 'COMPLIANT') ? 'Safe' : (($row['compliance_status'] === 'ELEVATED') ? 'Warning' : 'Too Loud');
    fputcsv($output, [
        $row['log_id'],
        $row['sensor_code'],
        $row['zone_name'],
        $row['decibel_level'],
        $row['material_observation'],
        $statusSimple,
        $row['logged_at']
    ]);
}
fclose($output);
exit;
