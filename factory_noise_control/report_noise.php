<?php
/**
 * Public Noise Complaint Page for Local Residents & Community
 * Allows common people to report factory noise with Name, Phone, and Location.
 */

declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';

$submittedSuccess = false;
$errorMessage = '';
$newRefCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $citizenName = trim((string)($_POST['citizen_name'] ?? ''));
    $phoneNumber = trim((string)($_POST['phone_number'] ?? ''));
    $locationAddress = trim((string)($_POST['location_address'] ?? ''));
    $noiseType = trim((string)($_POST['noise_type'] ?? 'Loud Industrial Hum'));
    $complaintDetails = trim((string)($_POST['complaint_details'] ?? ''));
    $latitude = isset($_POST['latitude']) && is_numeric($_POST['latitude']) ? (float)$_POST['latitude'] : 13.0845000;
    $longitude = isset($_POST['longitude']) && is_numeric($_POST['longitude']) ? (float)$_POST['longitude'] : 80.2680000;

    if (mb_strlen($citizenName) < 2) {
        $errorMessage = 'Please enter your full name.';
    } elseif (mb_strlen($phoneNumber) < 7) {
        $errorMessage = 'Please enter a valid phone number so our team can follow up.';
    } elseif (mb_strlen($locationAddress) < 5) {
        $errorMessage = 'Please provide your street address or neighborhood location.';
    } elseif (mb_strlen($complaintDetails) < 10) {
        $errorMessage = 'Please provide more details about the noise disturbance (at least 10 characters).';
    } else {
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
                    :loc,
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
                ':loc' => $locationAddress,
                ':lat' => $latitude,
                ':lng' => $longitude,
                ':type' => $noiseType,
                ':details' => $complaintDetails
            ]);

            $newId = (int)$pdo->lastInsertId();
            $newRefCode = 'NC-' . str_pad((string)$newId, 4, '0', STR_PAD_LEFT);
            $submittedSuccess = true;
        } catch (Exception $e) {
            $errorMessage = 'An error occurred while saving your report. Please try again or call our hotline.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Community Noise Grievance &bull; Factory Acoustic Compliance</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">

  <style>
    .public-page-wrapper {
      max-width: 900px;
      margin: 0 auto;
      padding: 2rem 1.25rem 4rem;
    }

    .public-hero-card {
      background: #131c2e;
      border: 1px solid #27354f;
      border-radius: var(--radius-lg);
      overflow: hidden;
      margin-bottom: 2rem;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
    }

    .public-hero-img-wrapper {
      position: relative;
      width: 100%;
      height: 260px;
      background: #0f172a;
      overflow: hidden;
    }

    .public-hero-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      filter: brightness(0.85);
    }

    .public-hero-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 1.5rem;
      background: linear-gradient(to top, rgba(19, 28, 46, 0.95), rgba(19, 28, 46, 0.2));
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .public-hero-title {
      font-size: 1.65rem;
      font-weight: 800;
      color: #ffffff;
      margin-bottom: 0.25rem;
      letter-spacing: -0.02em;
    }

    .public-hero-subtitle {
      font-size: 0.9rem;
      color: #cbd5e1;
    }

    .public-form-card {
      background: #131c2e;
      border: 1px solid #27354f;
      border-radius: var(--radius-lg);
      padding: 2.25rem;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    }

    .form-section-title {
      font-size: 1.2rem;
      font-weight: 700;
      color: #ffffff;
      margin-bottom: 0.4rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .form-section-desc {
      font-size: 0.88rem;
      color: #94a3b8;
      margin-bottom: 1.75rem;
      line-height: 1.5;
    }

    .success-panel {
      background: rgba(16, 185, 129, 0.12);
      border: 1px solid rgba(16, 185, 129, 0.4);
      border-radius: var(--radius-md);
      padding: 1.75rem;
      margin-bottom: 2rem;
      text-align: center;
    }

    .error-panel {
      background: rgba(239, 68, 68, 0.12);
      border: 1px solid rgba(239, 68, 68, 0.4);
      border-radius: var(--radius-md);
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      color: #fca5a5;
      font-size: 0.88rem;
    }

    .info-pills-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 1rem;
      margin-top: 1.5rem;
    }

    .info-pill-item {
      background: #0d1424;
      border: 1px solid #202b3f;
      border-radius: var(--radius-md);
      padding: 1rem;
      display: flex;
      align-items: flex-start;
      gap: 0.75rem;
    }
  </style>
</head>
<body class="theme-factory">
  <div class="public-page-wrapper">
    
    <!-- Top Nav / Back Link -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
      <a href="index.php" style="color: #38bdf8; text-decoration: none; font-size: 0.88rem; font-weight: 600; display: flex; align-items: center; gap: 0.4rem;">
        &larr; Back to Factory Noise Monitor
      </a>
      <a href="login.php" class="btn btn-secondary" style="font-size: 0.82rem; padding: 0.35rem 0.8rem;">
        🔑 Plant Staff Login
      </a>
    </div>

    <!-- Hero Card with Factory Photo -->
    <div class="public-hero-card">
      <div class="public-hero-img-wrapper">
        <img src="images/factory_exterior.jpg" alt="Industrial Manufacturing Plant & Acoustic Perimeter" class="public-hero-img">
        <div class="public-hero-overlay">
          <div>
            <h1 class="public-hero-title">📢 Community Noise Reporting</h1>
            <p class="public-hero-subtitle">Industrial Facility Environmental Noise &amp; Acoustic Grievance System</p>
          </div>
          <span class="badge badge-compliant" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
            🟢 24/7 Compliance Monitoring
          </span>
        </div>
      </div>
    </div>

    <?php if ($submittedSuccess): ?>
      <!-- Success Message -->
      <div class="success-panel">
        <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">✅</div>
        <h2 style="font-size: 1.4rem; font-weight: 800; color: #34d399; margin-bottom: 0.5rem;">
          Complaint Successfully Registered!
        </h2>
        <p style="color: #cbd5e1; font-size: 0.95rem; max-width: 600px; margin: 0 auto 1.25rem; line-height: 1.5;">
          Thank you for reporting. Your grievance has been submitted directly to the <strong>Plant Manager &amp; Environmental Safety Team</strong> for review and acoustic barrier inspection.
        </p>

        <div style="display: inline-block; background: #131c2e; border: 1px solid #27354f; border-radius: var(--radius-sm); padding: 0.75rem 1.5rem; margin-bottom: 1.5rem;">
          <div style="font-size: 0.78rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em;">Your Reference Tracking Number</div>
          <div class="mono" style="font-size: 1.6rem; font-weight: 800; color: #38bdf8;"><?= htmlspecialchars($newRefCode, ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <div>
          <a href="report_noise.php" class="btn btn-primary" style="margin-right: 0.5rem;">
            Submit Another Report
          </a>
          <a href="index.php" class="btn btn-secondary">
            Return to Dashboard
          </a>
        </div>
      </div>
    <?php endif; ?>

    <!-- Reporting Form -->
    <div class="public-form-card">
      <div class="form-section-title">
        <span>📝</span> Report a Noise Disturbance
      </div>
      <p class="form-section-desc">
        Are you living or working near our manufacturing perimeter and experiencing loud hammering, vibrating motors, or high-pitched squealing? Please provide your contact details and location below so our acoustic safety engineer can investigate.
      </p>

      <?php if (!empty($errorMessage)): ?>
        <div class="error-panel">
          ⚠️ <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="report_noise.php" novalidate id="public-complaint-form">
        
        <div class="form-row-2">
          <!-- Citizen Name -->
          <div class="form-item">
            <label for="citizen_name">Your Full Name *</label>
            <input type="text" name="citizen_name" id="citizen_name" class="input-ctrl" placeholder="e.g. Robert Henderson" required value="<?= htmlspecialchars($_POST['citizen_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <div class="field-error">Please enter your name.</div>
          </div>

          <!-- Phone Number -->
          <div class="form-item">
            <label for="phone_number">Contact Phone Number *</label>
            <input type="tel" name="phone_number" id="phone_number" class="input-ctrl mono" placeholder="e.g. +1 (555) 234-8901" required value="<?= htmlspecialchars($_POST['phone_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <div class="field-error">Please enter a valid phone number.</div>
          </div>
        </div>

        <!-- Location Address with Quick Sector Buttons -->
        <div class="form-item">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem; flex-wrap: wrap; gap: 0.4rem;">
            <label for="location_address" style="margin-bottom: 0; font-weight: 700;">
              📍 Your Location / Street Address *
            </label>
            <div style="display: flex; gap: 0.35rem; flex-wrap: wrap;">
              <button type="button" class="map-quick-tag" onclick="document.getElementById('location_address').value='North Perimeter Residential Area';">North Perimeter</button>
              <button type="button" class="map-quick-tag" onclick="document.getElementById('location_address').value='East Residential Gate & Colony';">East Gate</button>
              <button type="button" class="map-quick-tag" onclick="document.getElementById('location_address').value='South Boundary Road & Homes';">South Boundary</button>
              <button type="button" class="map-quick-tag" onclick="document.getElementById('location_address').value='West Industrial Buffer Sector';">West Sector</button>
            </div>
          </div>
          <input type="text" name="location_address" id="location_address" class="input-ctrl" placeholder="e.g. 42 Elm Street, North Perimeter (near Gate 2)" required value="<?= htmlspecialchars($_POST['location_address'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <div class="field-error">Please provide your address or neighborhood location.</div>
        </div>

        <div class="form-row-2">
          <!-- Type of Noise -->
          <div class="form-item">
            <label for="noise_type">Type of Noise Observed *</label>
            <select name="noise_type" id="noise_type" class="input-ctrl" required>
              <option value="Heavy Vibration & Metallic Thumping">Heavy Vibration &amp; Metallic Thumping</option>
              <option value="High-Pitched Screeching / Whining">High-Pitched Screeching / Whining</option>
              <option value="Continuous Low Industrial Rumble">Continuous Low Industrial Rumble</option>
              <option value="Intermittent Bangs / Dropping Parts">Intermittent Bangs / Dropping Parts</option>
              <option value="Ventilation & Exhaust Fan Drone">Ventilation &amp; Exhaust Fan Drone</option>
              <option value="Other Unusual Factory Noise">Other Unusual Factory Noise</option>
            </select>
          </div>

          <!-- Time observed -->
          <div class="form-item">
            <label for="time_observed">Time &amp; Duration</label>
            <input type="text" id="time_observed" class="input-ctrl" placeholder="e.g. Started around 6:00 AM, continuous" value="<?= htmlspecialchars($_POST['time_observed'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>
        </div>

        <!-- Complaint Details -->
        <div class="form-item">
          <label for="complaint_details">Describe the Noise Disturbance *</label>
          <textarea name="complaint_details" id="complaint_details" class="input-ctrl" rows="4" placeholder="Please describe how loud the noise is, whether vibrations are shaking windows, how it affects your sleep or day, and any other helpful details..." required><?= htmlspecialchars($_POST['complaint_details'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          <div class="field-error">Please describe the noise issue (at least 10 characters).</div>
        </div>

        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap;">
          <a href="index.php" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary" style="padding: 0.65rem 1.6rem; font-size: 0.95rem;">
            Submit Noise Complaint &rarr;
          </button>
        </div>

      </form>

      <!-- Facility Transparency Notes -->
      <div class="info-pills-grid">
        <div class="info-pill-item">
          <span style="font-size: 1.3rem;">🛡️</span>
          <div>
            <div style="font-size: 0.85rem; font-weight: 700; color: #ffffff;">OSHA &amp; EPA Standards</div>
            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.15rem;">We maintain factory perimeter limits strictly under 85 dB.</div>
          </div>
        </div>

        <div class="info-pill-item">
          <span style="font-size: 1.3rem;">⚡</span>
          <div>
            <div style="font-size: 0.85rem; font-weight: 700; color: #ffffff;">Rapid Response</div>
            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.15rem;">Complaints appear directly on the Plant Manager's dashboard.</div>
          </div>
        </div>

        <div class="info-pill-item">
          <span style="font-size: 1.3rem;">📞</span>
          <div>
            <div style="font-size: 0.85rem; font-weight: 700; color: #ffffff;">Community Helpline</div>
            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.15rem;">Acoustic Operations Hotline: +1 (800) 555-NOISE</div>
          </div>
        </div>
      </div>

    </div>

  </div>

  <script>
    // Form client-side validation
    document.getElementById('public-complaint-form')?.addEventListener('submit', function(e) {
      const name = document.getElementById('citizen_name');
      const phone = document.getElementById('phone_number');
      const location = document.getElementById('location_address');
      const details = document.getElementById('complaint_details');

      let hasError = false;
      [name, phone, location, details].forEach(el => el.classList.remove('is-invalid'));

      if (!name.value.trim() || name.value.trim().length < 2) {
        name.classList.add('is-invalid');
        hasError = true;
      }
      if (!phone.value.trim() || phone.value.trim().length < 7) {
        phone.classList.add('is-invalid');
        hasError = true;
      }
      if (!location.value.trim() || location.value.trim().length < 5) {
        location.classList.add('is-invalid');
        hasError = true;
      }
      if (!details.value.trim() || details.value.trim().length < 10) {
        details.classList.add('is-invalid');
        hasError = true;
      }

      if (hasError) {
        e.preventDefault();
      }
    });
  </script>
</body>
</html>
