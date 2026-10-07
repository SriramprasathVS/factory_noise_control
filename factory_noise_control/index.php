<?php
/**
 * Factory Noise Monitor - Professional Analysis Dashboard & Work Assignment
 * Powered by PHP, MySQL, Chart.js & Role-Based Authentication
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requireLogin();

require_once __DIR__ . '/db_connect.php';

$currentUser = getCurrentUser();
$isAdminUser = isAdmin();

$pdo = getDatabaseConnection(false);
$isDatabaseConnected = ($pdo !== null);

$zones = [];
$logs = [];
$maintenanceList = [];
$myTasks = [];
$avgNoise = 0.0;
$dangerZones = 0;
$safeZones = 0;
$openRepairs = 0;
$loudZoneNames = [];

$countWaiting = 0;
$countInProgress = 0;
$countFixed = 0;

$peakDb = 0.0;
$peakSensor = 'N/A';
$lowestDb = 0.0;
$lowestSensor = 'N/A';
$complianceRate = 100.0;

$chartAreaLabels = [];
$chartAreaData = [];
$chartBarColors = [];
$countSafe = 0;
$countWarning = 0;
$countDanger = 0;
$timelineLabels = [];
$timelineDbs = [];

// User display mapping
$userNamesMap = [
    'admin'      => 'Plant Manager (Admin)',
    'user1'      => 'Tech Alex (Acoustic)',
    'user2'      => 'Sarah (Safety Operator)',
    'unassigned' => 'Unassigned (Open Pool)'
];

if ($isDatabaseConnected) {
    try {
        // Ensure assigned_to, resolved_by, resolved_at columns exist
        try {
            $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN assigned_to VARCHAR(50) DEFAULT 'unassigned'");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN resolved_by VARCHAR(100) DEFAULT NULL");
        } catch (Exception $e) {}
        try {
            $pdo->exec("ALTER TABLE maintenance_requests ADD COLUMN resolved_at DATETIME DEFAULT NULL");
        } catch (Exception $e) {}

        // 1. Get Factory Areas
        $stmtZones = $pdo->query('SELECT * FROM factory_zones ORDER BY zone_id ASC');
        $zones = $stmtZones->fetchAll();

        // 2. Get Noise Readings
        $sqlJoin = '
            SELECT 
                nl.log_id,
                nl.sensor_code,
                nl.decibel_level,
                nl.frequency_hz,
                nl.compliance_status,
                nl.material_observation,
                nl.logged_at,
                fz.zone_id,
                fz.zone_name,
                fz.max_decibel_limit,
                fz.dampener_material_type
            FROM noise_logs nl
            JOIN factory_zones fz ON nl.zone_id = fz.zone_id
            ORDER BY nl.log_id DESC
        ';
        $stmtLogs = $pdo->query($sqlJoin);
        $logs = $stmtLogs->fetchAll();

        // 3. Get Repair Requests with assigned_to & resolution info
        $maintStmt = $pdo->query('
            SELECT 
                mr.request_id,
                mr.equipment_tag,
                mr.priority,
                mr.issue_description,
                mr.reported_by,
                COALESCE(mr.assigned_to, "unassigned") AS assigned_to,
                mr.status,
                mr.created_at,
                mr.resolved_by,
                mr.resolved_at,
                fz.zone_name
            FROM maintenance_requests mr
            JOIN factory_zones fz ON mr.zone_id = fz.zone_id
            ORDER BY mr.request_id DESC
        ');
        $maintenanceList = $maintStmt->fetchAll();

        // Tasks assigned to current logged-in user
        $myTasks = array_filter($maintenanceList, function($m) use ($currentUser) {
            return $m['assigned_to'] === $currentUser['username'];
        });

        // Unassigned tasks (available in open pool)
        $unassignedTasks = array_filter($maintenanceList, function($m) {
            return ($m['assigned_to'] === 'unassigned' || empty($m['assigned_to'])) && $m['status'] !== 'RESOLVED';
        });

        // Completed tasks (Fixed / Done)
        $completedTasks = array_filter($maintenanceList, function($m) {
            return $m['status'] === 'RESOLVED';
        });

        foreach ($maintenanceList as $m) {
            if ($m['status'] === 'PENDING') $countWaiting++;
            elseif ($m['status'] === 'IN_PROGRESS') $countInProgress++;
            elseif ($m['status'] === 'RESOLVED') $countFixed++;
        }

        // 4. Calculate Stats
        if (!empty($logs)) {
            $sumDb = array_sum(array_column($logs, 'decibel_level'));
            $avgNoise = round($sumDb / count($logs), 1);

            $sortedLogs = $logs;
            usort($sortedLogs, fn($a, $b) => $b['decibel_level'] <=> $a['decibel_level']);
            $peakDb = (float)$sortedLogs[0]['decibel_level'];
            $peakSensor = $sortedLogs[0]['sensor_code'] . ' (' . $sortedLogs[0]['zone_name'] . ')';

            $lowestRecord = end($sortedLogs);
            $lowestDb = (float)$lowestRecord['decibel_level'];
            $lowestSensor = $lowestRecord['sensor_code'] . ' (' . $lowestRecord['zone_name'] . ')';

            foreach ($logs as $l) {
                if ($l['compliance_status'] === 'CRITICAL' || $l['decibel_level'] >= 85.0) $countDanger++;
                elseif ($l['compliance_status'] === 'ELEVATED') $countWarning++;
                else $countSafe++;
            }

            $complianceRate = round(($countSafe / count($logs)) * 100, 1);
        }

        // 5. Chart 1: Current dB per Area
        foreach ($zones as $z) {
            $areaLogs = array_filter($logs, fn($l) => $l['zone_id'] == $z['zone_id']);
            $firstLog = reset($areaLogs);
            $currentDb = $firstLog ? (float)$firstLog['decibel_level'] : 70.0;
            
            $chartAreaLabels[] = $z['zone_name'];
            $chartAreaData[] = $currentDb;
            $chartBarColors[] = $currentDb >= 85.0 ? 'rgba(239, 68, 68, 0.85)' : ($currentDb >= 80.0 ? 'rgba(245, 158, 11, 0.85)' : 'rgba(16, 185, 129, 0.85)');

            if ($currentDb >= 85.0) {
                $dangerZones++;
                $loudZoneNames[] = $z['zone_name'];
            } else {
                $safeZones++;
            }
        }

        // 6. Chart 3: Timeline trend (last 10 readings chronological)
        $timelineLogs = array_reverse(array_slice($logs, 0, 10));
        foreach ($timelineLogs as $tl) {
            $timePart = substr((string)$tl['logged_at'], 11, 5);
            $timelineLabels[] = $timePart . ' [' . $tl['sensor_code'] . ']';
            $timelineDbs[] = (float)$tl['decibel_level'];
        }

        // Open repairs
        $openStmt = $pdo->query('SELECT COUNT(*) AS total FROM maintenance_requests WHERE status != "RESOLVED"');
        $openData = $openStmt->fetch();
        if ($openData) {
            $openRepairs = (int)$openData['total'];
        }

        // Public community complaints (Admin oversight)
        $publicComplaints = [];
        $countNewComplaints = 0;
        if ($isAdminUser) {
            try {
                $cStmt = $pdo->query('SELECT * FROM public_complaints ORDER BY complaint_id DESC LIMIT 50');
                $publicComplaints = $cStmt->fetchAll();
                foreach ($publicComplaints as $pc) {
                    if ($pc['status'] === 'NEW') $countNewComplaints++;
                }
            } catch (Exception $e) {
                $publicComplaints = [];
            }
        }

    } catch (PDOException $e) {
        error_log($e->getMessage());
        $isDatabaseConnected = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Factory Noise Monitor &bull; Analysis Dashboard</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <!-- Top Header with User Session & Role -->
  <header class="top-header">
    <div class="header-content">
      <div class="brand-area">
        <div class="brand-badge">🔊</div>
        <div>
          <div class="brand-title">Factory Noise Monitor</div>
          <div class="brand-subtitle">Noise Analysis &amp; Work Order Delegation</div>
        </div>
      </div>

      <div class="header-nav">
        <!-- Logged In User Pill -->
        <div style="display: flex; align-items: center; gap: 0.5rem; background: #1a253c; padding: 0.35rem 0.85rem; border-radius: 999px; border: 1px solid #2b3a58;">
          <span style="font-size: 0.85rem;">👤</span>
          <span style="font-size: 0.82rem; font-weight: 600; color: #ffffff;">
            <?= htmlspecialchars($currentUser['name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?>
          </span>
          <span class="badge <?= $isAdminUser ? 'badge-critical' : 'badge-compliant' ?>" style="font-size: 0.68rem; padding: 0.12rem 0.45rem;">
            <?= htmlspecialchars($currentUser['badge'] ?? 'USER', ENT_QUOTES, 'UTF-8') ?>
          </span>
        </div>

        <!-- Public Grievance Portal Link (Residents & Citizens) -->
        <a href="report_noise.php" target="_blank" class="btn btn-secondary" style="font-size: 0.82rem; display: flex; align-items: center; gap: 0.35rem;" title="Public Community Grievance Portal">
          📢 Public Noise Portal
        </a>

        <?php if ($isAdminUser): ?>
          <button class="btn btn-secondary" id="btn-open-noise-modal">
            + Record Sound Reading
          </button>
          <button class="btn btn-primary" id="btn-open-modal">
            + Assign Work Order
          </button>
        <?php else: ?>
          <button class="btn btn-primary" id="btn-open-modal">
            + Report Problem
          </button>
        <?php endif; ?>

        <!-- Logout Link -->
        <a href="logout.php" class="btn btn-secondary" style="color: #f87171; border-color: rgba(239, 68, 68, 0.35);" title="Sign out">
          Logout
        </a>
      </div>
    </div>
  </header>

  <main class="container">

    <!-- Hearing Safety Alert Banner (Essential for All) -->
    <?php if ($dangerZones > 0): ?>
      <div class="alert-banner">
        <span style="font-size: 1.3rem;">⚠️</span>
        <div>
          <strong>Hearing Safety Warning:</strong> <?= $dangerZones ?> factory areas exceed the 85 dB safety limit 
          (<em><?= htmlspecialchars(implode(', ', $loudZoneNames), ENT_QUOTES, 'UTF-8') ?></em>). 
          Ear protection is mandatory for workers in these areas!
        </div>
      </div>
    <?php endif; ?>

    <?php if ($isAdminUser): ?>
      <!-- 4 Full Executive KPI Summary Numbers for Admin -->
      <section class="kpi-row">
        <div class="kpi-card">
          <div class="kpi-card-label">Average Factory Noise</div>
          <div class="kpi-card-value mono"><?= number_format($avgNoise, 1) ?> dB</div>
          <div class="kpi-card-desc">Safety benchmark is 85.0 dB</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-card-label">Peak Loudness</div>
          <div class="kpi-card-value mono" style="color: #ef4444;"><?= number_format($peakDb, 1) ?> dB</div>
          <div class="kpi-card-desc" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            <?= htmlspecialchars($peakSensor, ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-card-label">Compliance Rate</div>
          <div class="kpi-card-value mono" style="color: #38bdf8;"><?= number_format($complianceRate, 1) ?>%</div>
          <div class="kpi-card-desc">Sensors below 85 dB limit</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-card-label">Open Repairs</div>
          <div class="kpi-card-value mono" id="open-repairs-count"><?= $openRepairs ?></div>
          <div class="kpi-card-desc">Waiting or In Progress</div>
        </div>
      </section>
    <?php else: ?>
      <!-- Essential KPI Data for Factory Users -->
      <section class="kpi-row">
        <div class="kpi-card">
          <div class="kpi-card-label">My Assigned Tasks</div>
          <div class="kpi-card-value mono" style="color: #38bdf8;"><?= count(array_filter($myTasks, fn($t) => $t['status'] !== 'RESOLVED')) ?></div>
          <div class="kpi-card-desc">Jobs waiting for you to complete</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-card-label">Factory Safety Status</div>
          <div class="kpi-card-value" style="font-size: 1.35rem; color: <?= $dangerZones > 0 ? '#ef4444' : '#10b981' ?>;">
            <?= $dangerZones > 0 ? '⚠️ Ear Protection Needed' : '🟢 Sound Levels Safe' ?>
          </div>
          <div class="kpi-card-desc"><?= $dangerZones > 0 ? $dangerZones . ' areas exceed 85 dB threshold' : 'All areas below 85 dB threshold' ?></div>
        </div>

        <div class="kpi-card">
          <div class="kpi-card-label">Plant Average Noise</div>
          <div class="kpi-card-value mono"><?= number_format($avgNoise, 1) ?> dB</div>
          <div class="kpi-card-desc">Benchmark is 85.0 dB</div>
        </div>
      </section>
    <?php endif; ?>

    <!-- SECTION: MY ASSIGNED WORK (Regular Users Only - Users mark as done only) -->
    <?php if (!$isAdminUser): ?>
    <section class="section-card" style="border-left: 4px solid var(--primary);">
      <div class="section-title-row">
        <div>
          <h2 class="section-title">🛠️ My Assigned Work</h2>
          <p class="section-desc">
            Tasks assigned directly to <strong><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></strong>.
            Review your work orders and click <strong>"✅ Mark as Done"</strong> once complete.
          </p>
        </div>
        
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
          <button type="button" class="sub-tab-btn active" id="tab-btn-mine" onclick="switchTaskTab('mine')">
            Active Tasks (<?= count(array_filter($myTasks, fn($t) => $t['status'] !== 'RESOLVED')) ?>)
          </button>
          <button type="button" class="sub-tab-btn" id="tab-btn-done" onclick="switchTaskTab('done')">
            Completed (<?= count(array_filter($myTasks, fn($t) => $t['status'] === 'RESOLVED')) ?>)
          </button>
        </div>
      </div>

      <!-- Active Tasks Tab -->
      <div id="tab-content-mine">
        <?php 
          $activeMyTasks = array_filter($myTasks, fn($t) => $t['status'] !== 'RESOLVED');
        ?>
        <?php if (empty($activeMyTasks)): ?>
          <div style="background: var(--bg-subtle); padding: 1.5rem; border-radius: var(--radius-md); color: var(--text-muted); text-align: center; font-size: 0.88rem; border: 1px dashed var(--border-color);">
            🎉 <strong>No pending jobs assigned to you right now.</strong><br>
            <span style="font-size: 0.82rem; color: #94a3b8; display: inline-block; margin-top: 0.4rem;">
              When the plant manager assigns a work order to you, it will appear here. If you see high noise or broken equipment, click <strong>"+ Report Problem"</strong>.
            </span>
          </div>
        <?php else: ?>
          <div class="my-tasks-container">
            <?php foreach ($activeMyTasks as $task): ?>
              <?php 
                $pClass = ($task['priority'] === 'EMERGENCY' || $task['priority'] === 'HIGH') ? 'badge-critical' : 'badge-elevated';
                if ($task['priority'] === 'LOW') $pClass = 'badge-compliant';
              ?>
              <div class="my-task-card" id="my-task-<?= (int)$task['request_id'] ?>">
                <div class="my-task-header">
                  <div>
                    <div style="font-weight: 700; color: #ffffff; font-size: 0.95rem;">
                      #<?= (int)$task['request_id'] ?> &bull; <?= htmlspecialchars($task['equipment_tag'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">
                      <?= htmlspecialchars($task['zone_name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  </div>
                  <span class="badge <?= $pClass ?>"><?= htmlspecialchars($task['priority'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>

                <div style="font-size: 0.85rem; color: #cbd5e1; background: #111827; padding: 0.65rem 0.8rem; border-radius: var(--radius-sm); border: 1px solid #27354f;">
                  <?= htmlspecialchars($task['issue_description'], ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: var(--text-muted); flex-wrap: wrap; gap: 0.5rem;">
                  <span>Reported By: <strong><?= htmlspecialchars($task['reported_by'], ENT_QUOTES, 'UTF-8') ?></strong></span>
                  
                  <button class="btn-report-done" onclick="markWorkDone(<?= (int)$task['request_id'] ?>)">
                    ✅ Mark as Done
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Completed Tasks Tab -->
      <div id="tab-content-done" style="display: none;">
        <?php 
          $doneMyTasks = array_filter($myTasks, fn($t) => $t['status'] === 'RESOLVED');
        ?>
        <?php if (empty($doneMyTasks)): ?>
          <div style="background: var(--bg-subtle); padding: 1.5rem; border-radius: var(--radius-md); color: var(--text-muted); text-align: center; font-size: 0.88rem; border: 1px dashed var(--border-color);">
            No completed tasks yet. Once you click <strong>"Mark as Done"</strong> on your assigned work, it will be listed here.
          </div>
        <?php else: ?>
          <div class="my-tasks-container">
            <?php foreach ($doneMyTasks as $task): ?>
              <div class="my-task-card is-done">
                <div class="my-task-header">
                  <div>
                    <div style="font-weight: 700; color: #ffffff; font-size: 0.95rem;">
                      #<?= (int)$task['request_id'] ?> &bull; <?= htmlspecialchars($task['equipment_tag'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.15rem;">
                      <?= htmlspecialchars($task['zone_name'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  </div>
                  <span class="badge badge-compliant" style="font-weight: 700;">✅ Fixed / Done</span>
                </div>

                <div style="font-size: 0.85rem; color: #cbd5e1; background: #111827; padding: 0.65rem 0.8rem; border-radius: var(--radius-sm); border: 1px solid #27354f;">
                  <?= htmlspecialchars($task['issue_description'], ENT_QUOTES, 'UTF-8') ?>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.78rem; color: #34d399;">
                  <span>Fixed by: <strong><?= htmlspecialchars($task['resolved_by'] ?: 'You', ENT_QUOTES, 'UTF-8') ?></strong></span>
                  <span class="mono" style="font-size: 0.72rem; color: #94a3b8;"><?= htmlspecialchars($task['resolved_at'] ?: $task['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- SECTION: Visual Analytics Dashboard (Admin Access) -->
    <?php if ($isAdminUser): ?>
    <section class="section-card">
      <div class="section-title-row">
        <div>
          <h2 class="section-title">Visual Noise Analysis</h2>
          <p class="section-desc">Interactive area sound levels and safety severity breakdown</p>
        </div>
        <span style="font-size: 0.8rem; color: #94a3b8; background: var(--bg-subtle); padding: 0.35rem 0.85rem; border-radius: 999px; border: 1px solid var(--border-color);">
          🔴 Too Loud &ge; 85 dB &nbsp;|&nbsp; 🟡 Warning 80-85 dB &nbsp;|&nbsp; 🟢 Safe &lt; 80 dB
        </span>
      </div>

      <div class="charts-grid">
        <div class="chart-box">
          <div class="chart-header">
            <span class="chart-title">Current Noise by Factory Area vs. 85 dB Limit</span>
          </div>
          <div class="chart-canvas-wrapper">
            <canvas id="areaBarChart"></canvas>
          </div>
        </div>

        <div class="chart-box">
          <div class="chart-header">
            <span class="chart-title">Noise Severity Distribution</span>
          </div>
          <div class="chart-canvas-wrapper">
            <canvas id="severityDonutChart"></canvas>
          </div>
        </div>
      </div>

      <div class="chart-box" style="margin-top: 0.5rem;">
        <div class="chart-header">
          <span class="chart-title">Recent Noise Telemetry Trend</span>
        </div>
        <div class="chart-canvas-wrapper timeline-chart-wrapper">
          <canvas id="timelineLineChart"></canvas>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- SECTION: Factory Areas -->
    <section class="section-card">
      <div class="section-title-row">
        <div>
          <h2 class="section-title">Factory Areas</h2>
          <p class="section-desc">Current sound level and sound barrier integrity by department</p>
        </div>
      </div>

      <div class="zones-grid">
        <?php foreach ($zones as $z): ?>
          <?php
            $limit = (float)$z['max_decibel_limit'];
            $areaLogs = array_filter($logs, fn($l) => $l['zone_id'] == $z['zone_id']);
            $firstLog = reset($areaLogs);
            $currentDb = $firstLog ? (float)$firstLog['decibel_level'] : 70.0;
            
            $isDanger = $currentDb >= $limit;
            $isWarning = $currentDb >= ($limit - 5) && !$isDanger;

            $statusText = $isDanger ? 'Too Loud' : ($isWarning ? 'Warning' : 'Safe');
            $badgeClass = $isDanger ? 'badge-critical' : ($isWarning ? 'badge-elevated' : 'badge-compliant');
            $barColor = $isDanger ? 'var(--status-critical)' : ($isWarning ? 'var(--status-elevated)' : 'var(--status-compliant)');

            // Factory zone picture mapping
            $zoneImages = [
              1 => 'images/zone_stamping.jpg',
              2 => 'images/zone_cnc.jpg',
              3 => 'images/zone_logistics.jpg',
              4 => 'images/zone_assembly.jpg'
            ];
            $zonePhoto = $zoneImages[(int)$z['zone_id']] ?? 'images/factory_exterior.jpg';
          ?>
          <div class="zone-box">
            <!-- Zone Factory Picture with Decibel Overlay -->
            <div style="position: relative; width: 100%; height: 135px; border-radius: var(--radius-sm); overflow: hidden; margin-bottom: 0.85rem; border: 1px solid rgba(255, 255, 255, 0.08);">
              <img src="<?= htmlspecialchars($zonePhoto, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($z['zone_name'], ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease;" loading="lazy">
              <div style="position: absolute; inset: 0; background: linear-gradient(to top, rgba(13, 20, 36, 0.95), rgba(13, 20, 36, 0.15) 60%);"></div>
              <div style="position: absolute; bottom: 8px; left: 10px; right: 10px; display: flex; justify-content: space-between; align-items: flex-end;">
                <span class="mono" style="font-size: 0.72rem; color: #f1f5f9; background: rgba(0, 0, 0, 0.65); padding: 0.15rem 0.45rem; border-radius: 4px; backdrop-filter: blur(4px);">
                  📍 <?= htmlspecialchars($z['floor_area'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="badge <?= $badgeClass ?>" style="font-size: 0.72rem; padding: 0.2rem 0.55rem; box-shadow: 0 2px 8px rgba(0,0,0,0.5);">
                  <?= $statusText ?>
                </span>
              </div>
            </div>

            <div class="zone-box-top" style="margin-bottom: 0.4rem;">
              <div>
                <div class="zone-title" style="font-size: 0.98rem;"><?= htmlspecialchars($z['zone_name'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="zone-subtitle mono"><?= htmlspecialchars($z['zone_code'], ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            </div>

            <div class="progress-bar">
              <div class="progress-fill" style="width: <?= min(max(($currentDb / 105) * 100, 15), 100) ?>%; background: <?= $barColor ?>;"></div>
            </div>

            <div class="zone-box-meta">
              <span>Noise: <strong class="mono" style="color: <?= $barColor ?>;"><?= number_format($currentDb, 1) ?> dB</strong></span>
              <span>Limit: <?= number_format($limit, 1) ?> dB</span>
            </div>

            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.35rem;">
              Sound Barrier: <?= htmlspecialchars($z['dampener_material_type'], ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- SECTION: MASTER REPAIR REQUESTS & ASSIGNMENT TABLE (Admin Feature) -->
    <?php if ($isAdminUser): ?>
    <section class="section-card">
      <div class="section-title-row">
        <div>
          <h2 class="section-title">All Repair Requests &amp; Assignments</h2>
          <p class="section-desc">
            Assign problems to technicians, track completion status, mark jobs as done, or delete orders.
          </p>
        </div>
        <button class="btn btn-primary" onclick="document.getElementById('simple-modal').classList.add('active');">
          + Assign Work Order
        </button>
      </div>

      <!-- Admin Overview Banner (Visible to Admin) -->
      <div class="admin-overview-banner">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.75rem;">
          <div style="display: flex; align-items: center; gap: 0.6rem;">
            <span style="font-size: 1.3rem;">👑</span>
            <div>
              <div style="font-size: 0.95rem; font-weight: 700; color: #ffffff;">Admin Master Work Order Overview</div>
              <div style="font-size: 0.78rem; color: #94a3b8;">Review technician assignments and verify completed work records.</div>
            </div>
          </div>
          <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <span class="badge badge-compliant" style="font-size: 0.78rem;">✅ <?= $countFixed ?> Fixed / Done</span>
            <span class="badge badge-elevated" style="font-size: 0.78rem;">⏳ <?= $countInProgress ?> In Progress</span>
            <span class="badge badge-critical" style="font-size: 0.78rem;">🕒 <?= $countWaiting ?> Waiting</span>
          </div>
        </div>

        <?php if (!empty($completedTasks)): ?>
          <?php $latestDone = reset($completedTasks); ?>
          <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: var(--radius-sm); padding: 0.6rem 0.85rem; font-size: 0.82rem; color: #cbd5e1; display: flex; align-items: center; gap: 0.5rem;">
            <span style="color: #34d399; font-size: 1.05rem;">✅</span>
            <div>
              <strong style="color: #34d399;">Reported Done by User:</strong>
              Order <strong>#<?= (int)$latestDone['request_id'] ?> (<?= htmlspecialchars($latestDone['equipment_tag']) ?>)</strong> 
              in <em><?= htmlspecialchars($latestDone['zone_name']) ?></em> was reported as <strong>Fixed / Done</strong> 
              by <strong><?= htmlspecialchars($latestDone['resolved_by'] ?: 'Technician') ?></strong>
              <?= !empty($latestDone['resolved_at']) ? ' on ' . htmlspecialchars($latestDone['resolved_at']) : '' ?>.
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Quick Filter Tabs -->
      <div class="tab-pills">
        <button class="tab-btn active" onclick="filterRepairs('ALL', this)">All (<?= count($maintenanceList) ?>)</button>
        <button class="tab-btn" onclick="filterRepairs('PENDING', this)">Waiting (<?= $countWaiting ?>)</button>
        <button class="tab-btn" onclick="filterRepairs('IN_PROGRESS', this)">In Progress (<?= $countInProgress ?>)</button>
        <button class="tab-btn" onclick="filterRepairs('RESOLVED', this)">✅ Fixed / Done (<?= $countFixed ?>)</button>
      </div>

      <div class="table-wrapper">
        <table class="clean-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Machine</th>
              <th>Area</th>
              <th>Urgency</th>
              <th>Problem Description</th>
              <th>Reported By</th>
              <th>Assigned To</th>
              <th>Status</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody id="repairs-table-body">
            <?php if (empty($maintenanceList)): ?>
              <tr>
                <td colspan="9" style="text-align: center; color: #94a3b8; padding: 1.5rem;">
                  No repair requests right now. Click "+ Assign Work Order" to create one.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($maintenanceList as $m): ?>
                <?php
                  $pClass = 'badge-elevated';
                  if ($m['priority'] === 'EMERGENCY' || $m['priority'] === 'HIGH') $pClass = 'badge-critical';
                  if ($m['priority'] === 'LOW') $pClass = 'badge-compliant';
                  
                  $assignedKey = $m['assigned_to'];
                  $isDone = ($m['status'] === 'RESOLVED');
                ?>
                <tr class="repair-row <?= $isDone ? 'row-done' : '' ?>" data-repair-status="<?= htmlspecialchars($m['status'], ENT_QUOTES, 'UTF-8') ?>" id="repair-row-<?= (int)$m['request_id'] ?>">
                  <td><span class="mono" style="font-weight: 700;">#<?= (int)$m['request_id'] ?></span></td>
                  <td><span class="mono" style="font-weight: 600; color: var(--primary);"><?= htmlspecialchars($m['equipment_tag'], ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td><?= htmlspecialchars($m['zone_name'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><span class="badge <?= $pClass ?>"><?= htmlspecialchars($m['priority'], ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td style="max-width: 220px; font-size: 0.82rem;"><?= htmlspecialchars($m['issue_description'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td style="font-size: 0.82rem; color: #94a3b8;"><?= htmlspecialchars($m['reported_by'], ENT_QUOTES, 'UTF-8') ?></td>
                  
                  <!-- Assigned To Column: Admin can assign/reassign to any user -->
                  <td>
                    <select class="select-field" style="padding: 0.25rem 0.5rem; font-size: 0.78rem;" onchange="changeAssignment(<?= (int)$m['request_id'] ?>, this.value)">
                      <option value="unassigned" <?= $assignedKey === 'unassigned' ? 'selected' : '' ?>>Unassigned (Open Pool)</option>
                      <option value="user1" <?= $assignedKey === 'user1' ? 'selected' : '' ?>>👤 Tech Alex (user1)</option>
                      <option value="user2" <?= $assignedKey === 'user2' ? 'selected' : '' ?>>👤 Sarah (user2)</option>
                      <option value="admin" <?= $assignedKey === 'admin' ? 'selected' : '' ?>>🔑 Plant Manager (admin)</option>
                    </select>
                  </td>

                  <!-- Status Column (Clearly shows Fixed / Done for Admin) -->
                  <td id="status-cell-<?= (int)$m['request_id'] ?>">
                    <?php if ($isDone): ?>
                      <span class="badge badge-compliant" style="font-weight: 700; font-size: 0.8rem;">
                        ✅ Fixed / Done
                      </span>
                      <?php if (!empty($m['resolved_by'])): ?>
                        <div style="font-size: 0.72rem; color: #34d399; margin-top: 0.2rem; font-weight: 600;">
                          By: <?= htmlspecialchars($m['resolved_by'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($m['resolved_at'])): ?>
                        <div style="font-size: 0.68rem; color: #94a3b8; font-family: monospace;">
                          <?= htmlspecialchars($m['resolved_at'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                      <?php endif; ?>
                    <?php else: ?>
                      <select class="select-field" style="padding: 0.25rem 0.5rem; font-size: 0.78rem;" onchange="changeStatus(<?= (int)$m['request_id'] ?>, this.value)">
                        <option value="PENDING" <?= $m['status'] === 'PENDING' ? 'selected' : '' ?>>Waiting</option>
                        <option value="IN_PROGRESS" <?= $m['status'] === 'IN_PROGRESS' ? 'selected' : '' ?>>In Progress</option>
                        <option value="RESOLVED">Fixed / Done</option>
                      </select>
                    <?php endif; ?>
                  </td>

                  <!-- Action Column: Admin can Mark Done or Delete -->
                  <td style="text-align: right;">
                    <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                      <?php if (!$isDone): ?>
                        <button class="btn-report-done" style="padding: 0.25rem 0.6rem; font-size: 0.74rem;" onclick="markWorkDone(<?= (int)$m['request_id'] ?>)">
                          ✅ Mark Done
                        </button>
                      <?php endif; ?>

                      <button class="btn btn-secondary" style="padding: 0.25rem 0.55rem; font-size: 0.74rem; color: #f87171; border-color: rgba(239, 68, 68, 0.3);" onclick="deleteRepair(<?= (int)$m['request_id'] ?>)">
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- SECTION: PUBLIC & COMMUNITY NOISE COMPLAINTS (Admin Exclusive) -->
    <section class="section-card" style="border-left: 4px solid #f59e0b;">
      <div class="section-title-row">
        <div>
          <div style="display: flex; align-items: center; gap: 0.6rem;">
            <h2 class="section-title">📢 Community &amp; Public Noise Complaints</h2>
            <?php if ($countNewComplaints > 0): ?>
              <span class="badge badge-critical" style="font-size: 0.78rem; font-weight: 700;">
                <?= $countNewComplaints ?> New Grievances
              </span>
            <?php else: ?>
              <span class="badge badge-compliant" style="font-size: 0.78rem;">
                ✓ All Addressed
              </span>
            <?php endif; ?>
          </div>
          <p class="section-desc">
            Direct noise grievances reported by residents living near the plant perimeter (includes resident name, phone number, and location).
          </p>
        </div>

        <a href="report_noise.php" target="_blank" class="btn btn-secondary" style="font-size: 0.82rem; display: flex; align-items: center; gap: 0.35rem;">
          🔗 Open Resident Form &rarr;
        </a>
      </div>

      <div class="table-wrapper">
        <table class="clean-table">
          <thead>
            <tr>
              <th>Ref ID</th>
              <th>Resident Name</th>
              <th>Contact Phone</th>
              <th>Resident Location / Address</th>
              <th>Noise Type</th>
              <th>Disturbance Description</th>
              <th>Status</th>
              <th>Reported Time</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($publicComplaints)): ?>
              <tr>
                <td colspan="9" style="text-align: center; color: #94a3b8; padding: 1.5rem;">
                  No community noise complaints registered yet.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($publicComplaints as $c): ?>
                <?php
                  $cStatus = $c['status'];
                  $cBadge = ($cStatus === 'NEW') ? 'badge-critical' : (($cStatus === 'INVESTIGATING') ? 'badge-elevated' : 'badge-compliant');
                  $cStatusLabel = ($cStatus === 'NEW') ? '🔴 New' : (($cStatus === 'INVESTIGATING') ? '🟠 Investigating' : '✅ Resolved');
                ?>
                <tr id="complaint-row-<?= (int)$c['complaint_id'] ?>">
                  <td><span class="mono" style="font-weight: 700; color: #38bdf8;">#NC-<?= str_pad((string)$c['complaint_id'], 4, '0', STR_PAD_LEFT) ?></span></td>
                  <td><strong><?= htmlspecialchars($c['citizen_name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                  <td>
                    <a href="tel:<?= htmlspecialchars($c['phone_number'], ENT_QUOTES, 'UTF-8') ?>" class="mono" style="color: #38bdf8; text-decoration: none; font-size: 0.82rem;">
                      📞 <?= htmlspecialchars($c['phone_number'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                  </td>
                  <td style="font-size: 0.82rem; max-width: 220px;">
                    <div>📍 <?= htmlspecialchars($c['location_address'], ENT_QUOTES, 'UTF-8') ?></div>
                  </td>
                  <td style="font-size: 0.82rem; color: #cbd5e1;">
                    <?= htmlspecialchars($c['noise_type'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td style="font-size: 0.82rem; max-width: 230px; color: #94a3b8;">
                    <?= htmlspecialchars($c['complaint_details'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td id="complaint-status-cell-<?= (int)$c['complaint_id'] ?>">
                    <span class="badge <?= $cBadge ?>"><?= $cStatusLabel ?></span>
                    <?php if ($cStatus === 'RESOLVED' && !empty($c['resolved_by'])): ?>
                      <div style="font-size: 0.7rem; color: #34d399; margin-top: 0.2rem;">By: <?= htmlspecialchars($c['resolved_by'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="mono" style="font-size: 0.74rem; color: #94a3b8;"><?= htmlspecialchars($c['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td style="text-align: right;">
                    <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center;">
                      <?php if ($cStatus !== 'RESOLVED'): ?>
                        <?php if ($cStatus === 'NEW'): ?>
                          <button class="btn btn-secondary" style="font-size: 0.72rem; padding: 0.2rem 0.5rem;" onclick="updateComplaintStatus(<?= (int)$c['complaint_id'] ?>, 'INVESTIGATING')">
                            Investigate
                          </button>
                        <?php endif; ?>
                        <button class="btn-report-done" style="font-size: 0.72rem; padding: 0.2rem 0.55rem;" onclick="updateComplaintStatus(<?= (int)$c['complaint_id'] ?>, 'RESOLVED')">
                          ✅ Resolve
                        </button>
                      <?php else: ?>
                        <span style="font-size: 0.75rem; color: #34d399; font-weight: 600;">Resolved</span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- SECTION: Sensor Readings (Admin Feature) -->
    <section class="section-card">
      <div class="section-title-row">
        <div>
          <h2 class="section-title">Noise Sensor Readings</h2>
          <p class="section-desc">Latest sound levels recorded by sensors in each area</p>
        </div>

        <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
          <a href="api/export_csv.php" class="btn btn-secondary" style="font-size: 0.82rem;">
            📥 Download Report (.CSV)
          </a>
          <button class="btn btn-primary" id="btn-open-noise-modal-2" style="font-size: 0.82rem;">
            + Record Reading
          </button>
        </div>
      </div>

      <div class="filter-bar">
        <input type="text" id="simple-search" class="search-field" placeholder="Search by sensor or area name...">
        <select id="simple-filter-status" class="select-field">
          <option value="ALL">All Statuses</option>
          <option value="COMPLIANT">Safe (&lt;80 dB)</option>
          <option value="ELEVATED">Warning (80-85 dB)</option>
          <option value="CRITICAL">Too Loud (&gt;=85 dB)</option>
        </select>
      </div>

      <div class="table-wrapper">
        <table class="clean-table">
          <thead>
            <tr>
              <th>Sensor Tag</th>
              <th>Factory Area</th>
              <th>Sound Level</th>
              <th>Material Observation</th>
              <th>Status</th>
              <th>Recorded Time</th>
            </tr>
          </thead>
          <tbody id="readings-body">
            <?php foreach ($logs as $l): ?>
              <?php
                $status = $l['compliance_status'];
                $badgeClass = ($status === 'COMPLIANT') ? 'badge-compliant' : (($status === 'ELEVATED') ? 'badge-elevated' : 'badge-critical');
                $statusSimple = ($status === 'COMPLIANT') ? 'Safe' : (($status === 'ELEVATED') ? 'Warning' : 'Too Loud');
                $valColor = ($status === 'CRITICAL') ? '#f87171' : (($status === 'ELEVATED') ? '#fbbf24' : '#34d399');
              ?>
              <tr data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                <td><strong class="mono" style="color: var(--primary);"><?= htmlspecialchars($l['sensor_code'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                <td><?= htmlspecialchars($l['zone_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><strong class="mono" style="color: <?= $valColor ?>;"><?= number_format((float)$l['decibel_level'], 1) ?> dB</strong></td>
                <td style="color: #94a3b8; font-size: 0.82rem;"><?= htmlspecialchars($l['material_observation'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= $statusSimple ?></span></td>
                <td class="mono" style="font-size: 0.78rem; color: #94a3b8;"><?= htmlspecialchars($l['logged_at'], ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

  </main>

  <!-- Modal 1: Report Problem / Assign Work -->
  <div class="modal-backdrop" id="simple-modal" role="dialog" aria-modal="true">
    <div class="modal-dialog">
      <div class="modal-header-simple">
        <?php if ($isAdminUser): ?>
          <h3>Assign &amp; Dispatch Work Order</h3>
        <?php else: ?>
          <h3>Report Factory Noise Problem</h3>
        <?php endif; ?>
        <button class="modal-close-btn" id="btn-close-modal">&times;</button>
      </div>

      <form id="simple-form" novalidate>
        <div class="modal-body-simple">
          
          <?php if (!$isAdminUser): ?>
            <div style="background: var(--bg-subtle); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.65rem 0.85rem; font-size: 0.82rem; color: #94a3b8; margin-bottom: 0.9rem; line-height: 1.45;">
              📢 <strong>Report to Management:</strong> When you submit this problem, it is sent to the Plant Manager (Admin) to review and assign.
            </div>
          <?php endif; ?>

          <div class="form-item">
            <label for="req-zone">Factory Area *</label>
            <select class="input-ctrl" id="req-zone" required>
              <option value="">-- Choose Area --</option>
              <?php foreach ($zones as $z): ?>
                <option value="<?= htmlspecialchars((string)$z['zone_id'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($z['zone_name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="field-error">Please pick an area.</div>
          </div>

          <div class="form-row-2">
            <div class="form-item">
              <label for="req-tag">Machine or Sensor Name *</label>
              <input type="text" class="input-ctrl mono" id="req-tag" placeholder="e.g. STAMP-01" required>
              <div class="field-error">Enter machine name (e.g. STAMP-01).</div>
            </div>

            <div class="form-item">
              <label for="req-priority">Urgency *</label>
              <select class="input-ctrl" id="req-priority" required>
                <option value="LOW">Low</option>
                <option value="MEDIUM" selected>Medium</option>
                <option value="HIGH">High</option>
                <option value="EMERGENCY">Emergency (Too Loud)</option>
              </select>
            </div>
          </div>

          <!-- ASSIGNMENT LOGIC: Only Admin can assign; Regular Users cannot assign to others -->
          <?php if ($isAdminUser): ?>
            <div class="form-item">
              <label for="req-assigned">Assign Work To User *</label>
              <select class="input-ctrl" id="req-assigned">
                <option value="unassigned">-- Unassigned (Open Pool) --</option>
                <option value="user1">👤 Tech Alex (user1)</option>
                <option value="user2">👤 Sarah (user2)</option>
                <option value="admin">🔑 Plant Manager (admin)</option>
              </select>
              <div style="display: flex; gap: 0.35rem; margin-top: 0.4rem; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 0.72rem; color: #94a3b8;">Quick Assign:</span>
                <button type="button" class="sub-tab-btn" style="padding: 0.15rem 0.45rem; font-size: 0.72rem;" onclick="document.getElementById('req-assigned').value='user1'">👤 Tech Alex</button>
                <button type="button" class="sub-tab-btn" style="padding: 0.15rem 0.45rem; font-size: 0.72rem;" onclick="document.getElementById('req-assigned').value='user2'">👤 Sarah</button>
                <button type="button" class="sub-tab-btn" style="padding: 0.15rem 0.45rem; font-size: 0.72rem;" onclick="document.getElementById('req-assigned').value='admin'">🔑 Plant Manager</button>
              </div>
            </div>
          <?php else: ?>
            <!-- Non-admin users cannot assign to others; default goes to unassigned for Admin review -->
            <input type="hidden" id="req-assigned" value="unassigned">
          <?php endif; ?>

          <div class="form-item">
            <label for="req-reporter">Reported By *</label>
            <input type="text" class="input-ctrl" id="req-reporter" value="<?= htmlspecialchars($currentUser['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            <div class="field-error">Please enter your name.</div>
          </div>

          <div class="form-item" style="margin-bottom: 0;">
            <label for="req-desc">What is the problem? *</label>
            <textarea class="input-ctrl" id="req-desc" rows="3" placeholder="Describe the loud sound, broken dampener, or vibration problem..." required></textarea>
            <div class="field-error">Please describe the issue (at least 10 characters).</div>
          </div>

        </div>

        <div class="modal-footer-simple">
          <button type="button" class="btn btn-secondary" id="btn-cancel-modal">Cancel</button>
          <?php if ($isAdminUser): ?>
            <button type="submit" class="btn btn-primary">Dispatch &amp; Assign Work</button>
          <?php else: ?>
            <button type="submit" class="btn btn-primary">Submit Problem Report</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <?php if ($isAdminUser): ?>
  <!-- Modal 2: Record New Noise Reading (Admin Only) -->
  <div class="modal-backdrop" id="noise-reading-modal" role="dialog" aria-modal="true">
    <div class="modal-dialog">
      <div class="modal-header-simple">
        <h3>Record Noise Reading</h3>
        <button class="modal-close-btn" id="btn-close-noise-modal">&times;</button>
      </div>

      <form id="noise-reading-form" novalidate>
        <div class="modal-body-simple">
          
          <div class="form-item">
            <label for="noise-zone">Factory Area *</label>
            <select class="input-ctrl" id="noise-zone" required>
              <option value="">-- Choose Area --</option>
              <?php foreach ($zones as $z): ?>
                <option value="<?= htmlspecialchars((string)$z['zone_id'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($z['zone_name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="field-error">Please choose an area.</div>
          </div>

          <div class="form-row-2">
            <div class="form-item">
              <label for="noise-sensor">Sensor / Meter Name *</label>
              <input type="text" class="input-ctrl mono" id="noise-sensor" placeholder="e.g. MIC-STAMP-02" required>
              <div class="field-error">Sensor code is required.</div>
            </div>

            <div class="form-item">
              <label for="noise-db">Sound Level (in dB) *</label>
              <input type="number" step="0.1" min="30" max="140" class="input-ctrl mono" id="noise-db" placeholder="e.g. 78.5" required>
              <div class="field-error">Enter a valid sound reading (30 - 140 dB).</div>
            </div>
          </div>

          <div class="form-item" style="margin-bottom: 0;">
            <label for="noise-notes">Observation / Notes</label>
            <input type="text" class="input-ctrl" id="noise-notes" placeholder="e.g. Normal hum, or loud grinding noise">
          </div>

        </div>

        <div class="modal-footer-simple">
          <button type="button" class="btn btn-secondary" id="btn-cancel-noise-modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Sound Reading</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <div class="toast-box" id="toast-box"></div>

  <!-- Interactive Logic -->
  <script>
    <?php if ($isAdminUser): ?>
    // Chart.js Visualizations (Admin Exclusive)
    if (typeof Chart !== 'undefined') {
      Chart.defaults.color = '#94a3b8';
      Chart.defaults.font.family = "'Inter', sans-serif";

      // Chart 1: Bar Chart of Noise per Area
      const canvasBar = document.getElementById('areaBarChart');
      if (canvasBar) {
        new Chart(canvasBar.getContext('2d'), {
          type: 'bar',
          data: {
            labels: <?= json_encode($chartAreaLabels) ?>,
            datasets: [{
              label: 'Current Sound Level (dB)',
              data: <?= json_encode($chartAreaData) ?>,
              backgroundColor: <?= json_encode($chartBarColors) ?>,
              borderRadius: 6,
              borderWidth: 0
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  afterLabel: function(context) {
                    const val = context.raw;
                    return val >= 85 ? '⚠️ Exceeds OSHA 85dB Limit' : '✓ Safe (< 85dB)';
                  }
                }
              }
            },
            scales: {
              y: {
                min: 40,
                max: 105,
                grid: { color: 'rgba(255, 255, 255, 0.05)' },
                ticks: { callback: function(value) { return value + ' dB'; } }
              },
              x: {
                grid: { display: false },
                ticks: { maxRotation: 25, minRotation: 15 }
              }
            }
          }
        });
      }

      // Chart 2: Severity Donut Chart
      const canvasDonut = document.getElementById('severityDonutChart');
      if (canvasDonut) {
        new Chart(canvasDonut.getContext('2d'), {
          type: 'doughnut',
          data: {
            labels: ['Safe (<80 dB)', 'Warning (80-85 dB)', 'Too Loud (>=85 dB)'],
            datasets: [{
              data: [<?= $countSafe ?>, <?= $countWarning ?>, <?= $countDanger ?>],
              backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
              borderColor: '#151d2f',
              borderWidth: 3
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } }
            },
            cutout: '68%'
          }
        });
      }

      // Chart 3: Timeline Line Chart
      const canvasLine = document.getElementById('timelineLineChart');
      if (canvasLine) {
        new Chart(canvasLine.getContext('2d'), {
          type: 'line',
          data: {
            labels: <?= json_encode($timelineLabels) ?>,
            datasets: [{
              label: 'Recorded Decibels (dB)',
              data: <?= json_encode($timelineDbs) ?>,
              borderColor: '#38bdf8',
              backgroundColor: 'rgba(56, 189, 248, 0.12)',
              fill: true,
              tension: 0.35,
              pointBackgroundColor: '#38bdf8',
              pointBorderColor: '#ffffff',
              pointRadius: 4
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              y: {
                min: 40,
                max: 100,
                grid: { color: 'rgba(255, 255, 255, 0.05)' },
                ticks: { callback: function(val) { return val + ' dB'; } }
              },
              x: { grid: { color: 'rgba(255, 255, 255, 0.03)' } }
            }
          }
        });
      }
    }
    <?php endif; ?>

    // Toast helper
    function showToast(message, type = 'success') {
      const toastBox = document.getElementById('toast-box');
      if (!toastBox) return;
      const toast = document.createElement('div');
      toast.className = `toast-item ${type === 'success' ? 'toast-success' : 'toast-error'}`;
      toast.innerHTML = `<span>${message}</span><button style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1.1rem;">&times;</button>`;
      toast.querySelector('button').addEventListener('click', () => toast.remove());
      toastBox.appendChild(toast);
      setTimeout(() => toast.remove(), 4000);
    }

    // Filter repair tabs (Admin)
    function filterRepairs(status, btn) {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const rows = document.querySelectorAll('.repair-row');
      rows.forEach(r => {
        const rowStatus = r.getAttribute('data-repair-status');
        if (status === 'ALL' || rowStatus === status) {
          r.style.display = '';
        } else {
          r.style.display = 'none';
        }
      });
    }

    // Switch between work order tabs
    function switchTaskTab(tabName) {
      document.querySelectorAll('.sub-tab-btn').forEach(btn => btn.classList.remove('active'));
      document.getElementById('tab-btn-' + tabName)?.classList.add('active');

      const tabMine = document.getElementById('tab-content-mine');
      const tabDone = document.getElementById('tab-content-done');

      if (tabMine) tabMine.style.display = (tabName === 'mine') ? '' : 'none';
      if (tabDone) tabDone.style.display = (tabName === 'done') ? '' : 'none';
    }

    // Mark as Done (Available to both Admin and Users on their tasks)
    async function markWorkDone(id) {
      try {
        const res = await fetch('api/update_maintenance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ request_id: id, status: 'RESOLVED' })
        });
        const data = await res.json();
        if (data.success) {
          showToast(`🎉 Work Order #${id} marked as DONE! Reflected immediately on Admin dashboard.`);
          setTimeout(() => window.location.reload(), 1200);
        } else {
          showToast(data.message, 'error');
        }
      } catch (e) {
        showToast('Connection error', 'error');
      }
    }

    // Change status from master table (Admin Only)
    async function changeStatus(id, newStatus) {
      try {
        const res = await fetch('api/update_maintenance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ request_id: id, status: newStatus })
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message || 'Status updated!');
          const row = document.getElementById(`repair-row-${id}`);
          if (row) row.setAttribute('data-repair-status', newStatus);
          if (newStatus === 'RESOLVED') {
            const cell = document.getElementById(`status-cell-${id}`);
            if (cell) cell.innerHTML = '<span class="badge badge-compliant" style="font-weight: 700;">✅ Fixed / Done</span>';
          }
          setTimeout(() => window.location.reload(), 1200);
        } else {
          showToast(data.message, 'error');
        }
      } catch (e) {
        showToast('Connection error', 'error');
      }
    }

    // Assign / Reassign task (Admin Only)
    async function changeAssignment(id, newUser) {
      try {
        const res = await fetch('api/update_maintenance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ request_id: id, assigned_to: newUser })
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message || `Work order assigned!`);
          setTimeout(() => window.location.reload(), 1000);
        } else {
          showToast(data.message, 'error');
        }
      } catch (e) {
        showToast('Connection error', 'error');
      }
    }

    // Delete repair order (Admin Only)
    async function deleteRepair(id) {
      if (!confirm(`Are you sure you want to delete Work Order #${id}?`)) return;
      try {
        const res = await fetch('api/delete_maintenance.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ request_id: id })
        });
        const data = await res.json();
        if (data.success) {
          showToast(`Work Order #${id} deleted successfully!`);
          const row = document.getElementById(`repair-row-${id}`);
          if (row) row.remove();
          const countEl = document.getElementById('open-repairs-count');
          if (countEl) {
            const current = parseInt(countEl.textContent || '0', 10);
            if (current > 0) countEl.textContent = current - 1;
          }
        } else {
          showToast(data.message, 'error');
        }
      } catch (e) {
        showToast('Connection error', 'error');
      }
    }

    // Update Public Complaint Status (Admin Only)
    async function updateComplaintStatus(id, newStatus) {
      try {
        const res = await fetch('api/update_complaint.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ complaint_id: id, status: newStatus })
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message || `Complaint status updated to ${newStatus}!`);
          setTimeout(() => window.location.reload(), 1000);
        } else {
          showToast(data.message || 'Error updating complaint', 'error');
        }
      } catch (e) {
        showToast('Connection error updating complaint', 'error');
      }
    }



    document.addEventListener('DOMContentLoaded', () => {
      const modalRepair = document.getElementById('simple-modal');
      const btnOpenRepair = document.getElementById('btn-open-modal');
      const btnCloseRepair = document.getElementById('btn-close-modal');
      const btnCancelRepair = document.getElementById('btn-cancel-modal');
      const formRepair = document.getElementById('simple-form');

      const modalNoise = document.getElementById('noise-reading-modal');
      const btnOpenNoise1 = document.getElementById('btn-open-noise-modal');
      const btnOpenNoise2 = document.getElementById('btn-open-noise-modal-2');
      const btnCloseNoise = document.getElementById('btn-close-noise-modal');
      const btnCancelNoise = document.getElementById('btn-cancel-noise-modal');
      const formNoise = document.getElementById('noise-reading-form');

      const searchInput = document.getElementById('simple-search');
      const filterStatus = document.getElementById('simple-filter-status');
      const tableRows = document.querySelectorAll('#readings-body tr');

      btnOpenRepair?.addEventListener('click', () => modalRepair?.classList.add('active'));
      const closeRepairModal = () => {
        if (modalRepair) {
          modalRepair.classList.remove('active');
          formRepair.reset();
          document.querySelectorAll('.input-ctrl').forEach(el => el.classList.remove('is-invalid'));
        }
      };
      btnCloseRepair?.addEventListener('click', closeRepairModal);
      btnCancelRepair?.addEventListener('click', closeRepairModal);

      const openNoiseModal = () => modalNoise?.classList.add('active');
      const closeNoiseModal = () => {
        if (modalNoise) {
          modalNoise.classList.remove('active');
          formNoise?.reset();
          document.querySelectorAll('.input-ctrl').forEach(el => el.classList.remove('is-invalid'));
        }
      };
      btnOpenNoise1?.addEventListener('click', openNoiseModal);
      btnOpenNoise2?.addEventListener('click', openNoiseModal);
      btnCloseNoise?.addEventListener('click', closeNoiseModal);
      btnCancelNoise?.addEventListener('click', closeNoiseModal);

      window.addEventListener('click', (e) => {
        if (e.target === modalRepair) closeRepairModal();
        if (modalNoise && e.target === modalNoise) closeNoiseModal();
      });

      function filterReadings() {
        if (!searchInput || !filterStatus) return;
        const q = (searchInput.value || '').toLowerCase().trim();
        const s = filterStatus.value;

        tableRows.forEach(row => {
          const text = row.textContent.toLowerCase();
          const rowStatus = row.getAttribute('data-status');
          const matchesQuery = !q || text.includes(q);
          const matchesStatus = (s === 'ALL') || (rowStatus === s);

          row.style.display = (matchesQuery && matchesStatus) ? '' : 'none';
        });
      }

      searchInput?.addEventListener('input', filterReadings);
      filterStatus?.addEventListener('change', filterReadings);

      // Submit Problem / Work Order Form
      formRepair?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const fieldZone = document.getElementById('req-zone');
        const fieldTag = document.getElementById('req-tag');
        const fieldPriority = document.getElementById('req-priority');
        const fieldAssigned = document.getElementById('req-assigned');
        const fieldReporter = document.getElementById('req-reporter');
        const fieldDesc = document.getElementById('req-desc');

        let hasError = false;
        [fieldZone, fieldTag, fieldReporter, fieldDesc].forEach(f => f?.classList.remove('is-invalid'));

        if (!fieldZone?.value) { fieldZone?.classList.add('is-invalid'); hasError = true; }
        if (!fieldTag?.value.trim()) { fieldTag?.classList.add('is-invalid'); hasError = true; }
        if (!fieldReporter?.value.trim() || fieldReporter.value.trim().length < 2) {
          fieldReporter?.classList.add('is-invalid'); hasError = true;
        }
        if (!fieldDesc?.value.trim() || fieldDesc.value.trim().length < 10) {
          fieldDesc?.classList.add('is-invalid'); hasError = true;
        }

        if (hasError) return;

        const payload = {
          zone_id: parseInt(fieldZone.value, 10),
          equipment_tag: fieldTag.value.trim().toUpperCase(),
          priority: fieldPriority.value,
          assigned_to: fieldAssigned ? fieldAssigned.value : 'unassigned',
          reported_by: fieldReporter.value.trim(),
          issue_description: fieldDesc.value.trim()
        };

        try {
          const res = await fetch('api/add_maintenance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const result = await res.json();

          if (result.success) {
            <?php if ($isAdminUser): ?>
              showToast(`Work order #${result.order_id} assigned to ${fieldAssigned.value}!`);
            <?php else: ?>
              showToast(`Problem report #${result.order_id} submitted to Plant Manager!`);
            <?php endif; ?>
            closeRepairModal();
            setTimeout(() => window.location.reload(), 1200);
          } else {
            showToast(result.message || 'Error saving request', 'error');
          }
        } catch (err) {
          showToast('Could not save request.', 'error');
        }
      });

      // Submit Noise Reading Form (Admin Only)
      formNoise?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const fieldZone = document.getElementById('noise-zone');
        const fieldSensor = document.getElementById('noise-sensor');
        const fieldDb = document.getElementById('noise-db');
        const fieldNotes = document.getElementById('noise-notes');

        let hasError = false;
        [fieldZone, fieldSensor, fieldDb].forEach(f => f?.classList.remove('is-invalid'));

        if (!fieldZone?.value) { fieldZone?.classList.add('is-invalid'); hasError = true; }
        if (!fieldSensor?.value.trim()) { fieldSensor?.classList.add('is-invalid'); hasError = true; }
        const dbVal = parseFloat(fieldDb?.value);
        if (isNaN(dbVal) || dbVal < 30 || dbVal > 140) {
          fieldDb?.classList.add('is-invalid');
          hasError = true;
        }

        if (hasError) return;

        const payload = {
          zone_id: parseInt(fieldZone.value, 10),
          sensor_code: fieldSensor.value.trim().toUpperCase(),
          decibel_level: dbVal,
          notes: fieldNotes?.value.trim() || 'Regular check'
        };

        try {
          const res = await fetch('api/add_noise_log.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const result = await res.json();

          if (result.success) {
            showToast(`Noise reading saved (${dbVal} dB)!`);
            closeNoiseModal();
            setTimeout(() => window.location.reload(), 1000);
          } else {
            showToast(result.message || 'Error saving reading', 'error');
          }
        } catch (err) {
          showToast('Could not save reading.', 'error');
        }
      });

    });
  </script>
</body>
</html>
