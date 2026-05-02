<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: dashboard.php');
  exit;
}
require_once 'db_connect.php';

$current_rate = get_setting('current_rate', '33.70');
$smtp_host = get_setting('smtp_host', 'smtp.gmail.com');
$smtp_port = get_setting('smtp_port', '587');
$smtp_user = get_setting('smtp_user', '');
$smtp_pass = get_setting('smtp_pass', '');
$smtp_from_email = get_setting('smtp_from_email', '');
$smtp_from_name = get_setting('smtp_from_name', 'BayanTap Water District');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BayanTap – System Settings</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css?v=5">
  <style>
    .settings-container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
    .settings-card { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow-md); padding: 32px; }
    .settings-header { margin-bottom: 32px; border-bottom: 1px solid var(--gray-100); padding-bottom: 16px; }
    .settings-header h2 { font-size: 1.5rem; font-weight: 800; color: var(--gray-900); }
    .settings-row { display: flex; align-items: center; justify-content: space-between; padding: 20px 0; border-bottom: 1px solid var(--gray-50); }
    .settings-info { flex: 1; }
    .settings-info strong { display: block; font-size: .95rem; margin-bottom: 4px; color: var(--gray-900); }
    .settings-info p { font-size: .82rem; color: var(--gray-500); }
    .settings-action { display: flex; align-items: center; gap: 12px; }
    .input-rate { padding: 10px 14px; border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm); font-size: 1.1rem; font-weight: 700; width: 120px; text-align: right; outline: none; transition: border-color .2s; }
    .input-rate:focus { border-color: var(--blue); }
    .unit { font-weight: 600; color: var(--gray-400); }
    .input-text { padding: 10px 14px; border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm); font-size: 0.95rem; width: 300px; outline: none; transition: border-color .2s; }
    .input-text:focus { border-color: var(--blue); }
    .section-title { margin-top: 40px; padding-top: 20px; border-top: 2px solid var(--gray-50); color: var(--gray-400); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
    .btn-save { background: var(--blue); color: #fff; padding: 12px 24px; border: none; border-radius: var(--radius-sm); font-weight: 700; cursor: pointer; transition: background .2s; }
    .btn-save:hover { background: var(--blue-dark); }
    .btn-save:disabled { opacity: 0.6; cursor: not-allowed; }
  </style>
</head>
<body>
  <?php $current_page = 'settings'; include 'navbar.php'; ?>

  <div class="settings-container">
    <div class="settings-card">
      <div class="settings-header">
        <h2>System Configuration</h2>
        <p>Global settings for BayanTap Water District portal</p>
      </div>

      <!-- Billing Section -->
      <div class="section-title" style="margin-top:0; border-top:none;">Billing Configuration</div>
      <div class="settings-row">
        <div class="settings-info">
          <strong>Billing Rate (per m³)</strong>
          <p>Current price applied to all newly generated household water bills.</p>
        </div>
        <div class="settings-action">
          <input type="number" step="0.01" id="currentRate" class="input-rate" value="<?= htmlspecialchars($current_rate) ?>">
          <span class="unit">₱ / m³</span>
        </div>
      </div>

      <!-- SMTP Section -->
      <div class="section-title">Email Configuration (SMTP)</div>

      <div class="settings-row">
        <div class="settings-info">
          <strong>SMTP Host</strong>
          <p>The hostname of your email service provider.</p>
        </div>
        <div class="settings-action">
          <input type="text" id="smtpHost" class="input-text" value="<?= htmlspecialchars($smtp_host) ?>">
        </div>
      </div>

      <div class="settings-row">
        <div class="settings-info">
          <strong>SMTP Port</strong>
          <p>Common ports: 587 (TLS) or 465 (SSL).</p>
        </div>
        <div class="settings-action">
          <input type="number" id="smtpPort" class="input-text" style="width: 120px;" value="<?= htmlspecialchars($smtp_port) ?>">
        </div>
      </div>

      <div class="settings-row">
        <div class="settings-info">
          <strong>SMTP Email / User</strong>
          <p>Your email address (e.g. your-name@gmail.com).</p>
        </div>
        <div class="settings-action">
          <input type="text" id="smtpUser" class="input-text" value="<?= htmlspecialchars($smtp_user) ?>">
        </div>
      </div>

      <div class="settings-row">
        <div class="settings-info">
          <strong>SMTP Password</strong>
          <p>Use an 'App Password' if you have 2FA enabled.</p>
        </div>
        <div class="settings-action">
          <input type="password" id="smtpPass" class="input-text" value="<?= htmlspecialchars($smtp_pass) ?>" placeholder="••••••••••••">
        </div>
      </div>

      <div class="settings-row">
        <div class="settings-info">
          <strong>Sender Name</strong>
          <p>The name that appears in the 'From' field.</p>
        </div>
        <div class="settings-action">
          <input type="text" id="smtpFromName" class="input-text" value="<?= htmlspecialchars($smtp_from_name) ?>">
        </div>
      </div>

      <div style="margin-top: 32px; display: flex; justify-content: flex-end; gap: 12px;">
        <button class="btn-save" style="background: var(--gray-100); color: var(--gray-700);" id="testBtn" onclick="testEmail()">🧪 Test SMTP</button>
        <button class="btn-save" id="saveBtn" onclick="saveSettings()">Save Configuration</button>
      </div>
    </div>
  </div>

  <script>


    async function saveSettings() {
      const btn = document.getElementById('saveBtn');
      const rate = document.getElementById('currentRate').value;
      const host = document.getElementById('smtpHost').value;
      const port = document.getElementById('smtpPort').value;
      const user = document.getElementById('smtpUser').value;
      const pass = document.getElementById('smtpPass').value;
      const fromName = document.getElementById('smtpFromName').value;

      btn.disabled = true;
      btn.textContent = 'Saving...';

      try {
        const response = await fetch('api_update_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            current_rate: rate,
            smtp_host: host,
            smtp_port: port,
            smtp_user: user,
            smtp_pass: pass,
            smtp_from_name: fromName,
            smtp_from_email: user
          })
        });
        const res = await response.json();
        if (res.success) {
          alert('Settings saved successfully!');
        } else {
          alert('Error: ' + res.message);
        }
      } catch (err) {
        alert('Failed to connect to the server.');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Save Configuration';
      }
    }

    async function testEmail() {
      const btn = document.getElementById('testBtn');
      const email = prompt("Enter an email address to send a test message to:");
      if (!email) return;

      btn.disabled = true;
      btn.textContent = 'Sending...';

      try {
        const response = await fetch('api_test_email.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: email })
        });
        const res = await response.json();
        if (res.success) {
          alert('Test email sent successfully!');
        } else {
          alert('Failed to send test email: ' + res.message);
        }
      } catch (err) {
        alert('An error occurred during the test.');
      } finally {
        btn.disabled = false;
        btn.textContent = '🧪 Test SMTP';
      }
    }
  </script>
</body>
</html>
