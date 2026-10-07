<?php
/**
 * Factory Noise Monitor - Simple Login Page
 * 1 Admin and 2 User Accounts
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = trim((string)($_POST['password'] ?? ''));

    if (isset(SYSTEM_USERS[$username]) && SYSTEM_USERS[$username]['password'] === $password) {
        $_SESSION['user'] = SYSTEM_USERS[$username];
        header('Location: index.php');
        exit;
    } else {
        $errorMessage = 'Invalid username or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login &bull; Factory Noise Monitor</title>
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css">

  <style>
    .login-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      position: relative;
    }

    .login-box {
      width: 100%;
      max-width: 440px;
      background: #131c2e;
      border: 1px solid #2b3a58;
      border-radius: var(--radius-lg);
      padding: 2.25rem;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), 0 0 30px rgba(56, 189, 248, 0.1);
      position: relative;
      z-index: 10;
    }

    .login-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .login-icon {
      width: 52px;
      height: 52px;
      margin: 0 auto 1rem;
      background: linear-gradient(135deg, #0284c7, #6366f1);
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      box-shadow: 0 8px 20px rgba(14, 165, 233, 0.35);
    }

    .login-title {
      font-size: 1.35rem;
      font-weight: 800;
      color: #ffffff;
      margin-bottom: 0.35rem;
      letter-spacing: -0.02em;
    }

    .login-subtitle {
      font-size: 0.85rem;
      color: #94a3b8;
    }

    .quick-login-section {
      margin-top: 1.75rem;
      padding-top: 1.5rem;
      border-top: 1px solid #27354f;
    }

    .quick-title {
      font-size: 0.78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #94a3b8;
      margin-bottom: 0.75rem;
      text-align: center;
    }

    .quick-btns-grid {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }

    .quick-btn {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.55rem 0.85rem;
      background: #1a253c;
      border: 1px solid #2b3a58;
      border-radius: var(--radius-md);
      color: #f8fafc;
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.15s ease;
      text-align: left;
    }

    .quick-btn:hover {
      background: #25334c;
      border-color: #38bdf8;
      transform: translateY(-1px);
    }

    .quick-btn span.badge-pill-role {
      font-size: 0.7rem;
      padding: 0.15rem 0.5rem;
      border-radius: 999px;
    }
  </style>
</head>
<body class="noise-bg">

  <div class="login-container">
    <div class="login-box" style="overflow: hidden; padding-top: 0;">
      
      <!-- Facility Exterior Photo Banner -->
      <div style="width: calc(100% + 4.5rem); height: 130px; margin: 0 -2.25rem 1.5rem -2.25rem; position: relative; overflow: hidden;">
        <img src="images/factory_exterior.jpg" alt="Factory Facility" style="width: 100%; height: 100%; object-fit: cover; filter: brightness(0.75);">
        <div style="position: absolute; inset: 0; background: linear-gradient(to top, #131c2e 10%, rgba(19, 28, 46, 0.4) 70%, transparent);"></div>
        <div style="position: absolute; bottom: 10px; left: 24px; right: 24px; display: flex; justify-content: space-between; align-items: flex-end;">
          <span style="font-size: 0.72rem; color: #38bdf8; font-family: monospace; font-weight: 700; background: rgba(0,0,0,0.6); padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(56, 189, 248, 0.3);">
            🏭 ACME INDUSTRIAL ACOUSTICS
          </span>
          <span class="badge badge-compliant" style="font-size: 0.68rem; padding: 2px 6px;">
            24/7 ONLINE
          </span>
        </div>
      </div>
      
      <div class="login-header">
        <h1 class="login-title">Factory Noise Monitor</h1>
        <p class="login-subtitle">Sign in to access noise analytics &amp; repair tracking</p>
      </div>

      <?php if (!empty($errorMessage)): ?>
        <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 0.75rem 1rem; border-radius: var(--radius-md); font-size: 0.85rem; margin-bottom: 1.25rem;">
          <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <!-- Manual Login Form -->
      <form method="POST" action="login.php" id="login-form">
        <div class="form-item">
          <label for="username">Username</label>
          <input type="text" class="input-ctrl" id="username" name="username" placeholder="e.g. admin or user1" required autofocus>
        </div>

        <div class="form-item" style="margin-bottom: 1.5rem;">
          <label for="password">Password</label>
          <input type="password" class="input-ctrl" id="password" name="password" placeholder="Enter password" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.65rem 1rem; font-size: 0.95rem;">
          Sign In
        </button>
      </form>

      <!-- 1-Click Quick Demo Login (Admin & 2 Users) -->
      <div class="quick-login-section">
        <div class="quick-title">Quick 1-Click Login Accounts</div>
        
        <div class="quick-btns-grid">
          <!-- Admin -->
          <button type="button" class="quick-btn" onclick="quickLogin('admin', 'admin123')">
            <div>
              <strong>🔑 Admin</strong>
              <div style="font-size: 0.72rem; color: #94a3b8;">Plant Manager (Full Control &amp; Delete)</div>
            </div>
            <span class="badge-pill-role badge badge-critical">ADMIN</span>
          </button>

          <!-- User 1 -->
          <button type="button" class="quick-btn" onclick="quickLogin('user1', 'user123')">
            <div>
              <strong>👤 User 1</strong>
              <div style="font-size: 0.72rem; color: #94a3b8;">Alex (Acoustic Technician)</div>
            </div>
            <span class="badge-pill-role badge badge-compliant">USER</span>
          </button>

          <!-- User 2 -->
          <button type="button" class="quick-btn" onclick="quickLogin('user2', 'user123')">
            <div>
              <strong>👤 User 2</strong>
              <div style="font-size: 0.72rem; color: #94a3b8;">Sarah (Safety Operator)</div>
            </div>
            <span class="badge-pill-role badge badge-elevated">USER</span>
          </button>
        </div>
      </div>

      <!-- Public Grievance Portal Link for Common People / Residents -->
      <div style="margin-top: 1.5rem; text-align: center; padding-top: 1.25rem; border-top: 1px solid #27354f;">
        <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.35rem;">Are you a local resident living near the plant?</div>
        <a href="report_noise.php" style="color: #38bdf8; font-size: 0.86rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.4rem 0.8rem; background: rgba(56, 189, 248, 0.08); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: var(--radius-sm); transition: all 0.2s ease;">
          📢 Public Noise Reporting Form &rarr;
        </a>
      </div>

    </div>
  </div>

  <script>
    function quickLogin(username, password) {
      document.getElementById('username').value = username;
      document.getElementById('password').value = password;
      document.getElementById('login-form').submit();
    }
  </script>
</body>
</html>
