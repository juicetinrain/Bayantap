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

$stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BayanTap – System Settings</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="CSS/dashboard.css?v=5">
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
    .users-table { width: 100%; border-collapse: collapse; margin-top: 24px; }
    .users-table th, .users-table td { padding: 14px 12px; border-bottom: 1px solid var(--gray-100); }
    .users-table th { text-align: left; font-size: .85rem; color: var(--gray-500); font-weight: 700; }
    .users-table td { vertical-align: middle; }
    .user-input { width: 100%; max-width: 220px; padding: 10px 14px; border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm); font-size: .95rem; outline: none; transition: border-color .2s; }
    .user-input:focus { border-color: var(--blue); }
    .user-action-button { background: #0f766e; color: #fff; padding: 10px 16px; border: none; border-radius: var(--radius-sm); cursor: pointer; font-weight: 700; }
    .user-action-button:hover { background: #115e59; }
    .user-role { color: var(--gray-500); font-size: .9rem; }

    /* ── Confirmation Modal ── */
    #confirm-overlay {
      position: fixed; inset: 0;
      background: rgba(15, 23, 42, 0.45);
      backdrop-filter: blur(3px);
      display: flex; align-items: center; justify-content: center;
      z-index: 9999;
      opacity: 0; pointer-events: none;
      transition: opacity .2s ease;
    }
    #confirm-overlay.active {
      opacity: 1; pointer-events: all;
    }
    #confirm-box {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 24px 60px rgba(0,0,0,.18);
      padding: 36px 32px 28px;
      width: 100%; max-width: 420px;
      transform: translateY(12px) scale(.97);
      transition: transform .22s ease, opacity .22s ease;
      opacity: 0;
    }
    #confirm-overlay.active #confirm-box {
      transform: translateY(0) scale(1);
      opacity: 1;
    }
    #confirm-icon {
      width: 52px; height: 52px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 18px;
    }
    #confirm-icon.warning { background: #fef3c7; }
    #confirm-icon.danger  { background: #fee2e2; }
    #confirm-title {
      font-size: 1.1rem; font-weight: 800;
      color: #0f172a; margin-bottom: 8px;
    }
    #confirm-message {
      font-size: .9rem; color: #64748b;
      line-height: 1.6; margin-bottom: 28px;
    }
    #confirm-actions {
      display: flex; gap: 10px; justify-content: flex-end;
    }
    #confirm-cancel {
      padding: 10px 20px; border-radius: 8px;
      border: 1.5px solid #e2e8f0;
      background: #fff; color: #475569;
      font-weight: 700; font-size: .9rem;
      cursor: pointer; transition: background .15s, border-color .15s;
      font-family: inherit;
    }
    #confirm-cancel:hover { background: #f8fafc; border-color: #cbd5e1; }
    #confirm-ok {
      padding: 10px 22px; border-radius: 8px;
      border: none;
      font-weight: 700; font-size: .9rem;
      cursor: pointer; transition: filter .15s;
      font-family: inherit; color: #fff;
    }
    #confirm-ok.warning { background: #f59e0b; }
    #confirm-ok.warning:hover { filter: brightness(1.1); }
    #confirm-ok.danger  { background: #ef4444; }
    #confirm-ok.danger:hover  { filter: brightness(1.1); }
    #confirm-ok.primary { background: var(--blue, #3b82f6); }
    #confirm-ok.primary:hover { filter: brightness(1.1); }
  </style>
</head>
<body>
  <?php $current_page = 'settings'; include 'navbar.php'; ?>

  <!-- Confirmation Modal -->
  <div id="confirm-overlay" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div id="confirm-box">
      <div id="confirm-icon" class="warning">⚠️</div>
      <div id="confirm-title">Confirm Action</div>
      <div id="confirm-message">Are you sure you want to proceed?</div>
      <div id="confirm-actions">
        <button id="confirm-cancel" onclick="confirmResolve(false)">Cancel</button>
        <button id="confirm-ok" class="primary" onclick="confirmResolve(true)">Confirm</button>
      </div>
    </div>
  </div>

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

      <div class="section-title">User Accounts</div>
      <p style="margin: 0 0 16px; color: var(--gray-500);">View and update login names or reset passwords for portal users.</p>
      <table class="users-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Username</th>
            <th>New Password</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <tr data-user-id="<?= htmlspecialchars($user['id']) ?>">
              <td><?= htmlspecialchars($user['username']) ?></td>
              <td><span class="user-role"><?= htmlspecialchars(ucfirst($user['role'])) ?></span></td>
              <td>
                <input type="text" class="user-input user-username" value="<?= htmlspecialchars($user['username']) ?>">
              </td>
              <td>
                <input type="password" class="user-input user-password" placeholder="Leave blank to keep current password">
              </td>
              <td>
                <button class="user-action-button" type="button" onclick="saveUser(<?= htmlspecialchars($user['id']) ?>)">Save</button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    /* ── Confirmation Modal Logic ── */
    let confirmResolve = () => {};

    function showConfirm({ title, message, okLabel = 'Confirm', okClass = 'primary', iconClass = 'warning', icon = '⚠️' }) {
      return new Promise((resolve) => {
        document.getElementById('confirm-title').textContent   = title;
        document.getElementById('confirm-message').textContent = message;
        document.getElementById('confirm-ok').textContent      = okLabel;
        document.getElementById('confirm-ok').className        = okClass;
        document.getElementById('confirm-icon').className      = 'confirm-icon ' + iconClass;
        document.getElementById('confirm-icon').textContent    = icon;
        document.getElementById('confirm-overlay').classList.add('active');

        confirmResolve = (result) => {
          document.getElementById('confirm-overlay').classList.remove('active');
          resolve(result);
        };
      });
    }

    // Close on overlay click (outside the box)
    document.getElementById('confirm-overlay').addEventListener('click', function(e) {
      if (e.target === this) confirmResolve(false);
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') confirmResolve(false);
    });


    /* ── Settings Actions ── */

    async function saveSettings() {
      const confirmed = await showConfirm({
        title: 'Save System Configuration?',
        message: 'This will update the billing rate and SMTP email settings for the entire portal. Are you sure you want to continue?',
        okLabel: 'Yes, Save',
        okClass: 'primary',
        iconClass: 'warning',
        icon: '⚙️'
      });
      if (!confirmed) return;

      const btn = document.getElementById('saveBtn');
      const rate     = document.getElementById('currentRate').value;
      const host     = document.getElementById('smtpHost').value;
      const port     = document.getElementById('smtpPort').value;
      const user     = document.getElementById('smtpUser').value;
      const pass     = document.getElementById('smtpPass').value;
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
      const email = prompt("Enter an email address to send a test message to:");
      if (!email) return;

      const confirmed = await showConfirm({
        title: 'Send Test Email?',
        message: `A test email will be sent to "${email}" using the current SMTP settings. Proceed?`,
        okLabel: 'Send Test',
        okClass: 'primary',
        iconClass: 'warning',
        icon: '📧'
      });
      if (!confirmed) return;

      const btn = document.getElementById('testBtn');
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

    async function saveUser(userId) {
      const row = document.querySelector(`[data-user-id="${userId}"]`);
      if (!row) return;

      const usernameInput = row.querySelector('.user-username');
      const passwordInput = row.querySelector('.user-password');
      const btn           = row.querySelector('.user-action-button');
      const username      = usernameInput.value.trim();
      const newPassword   = passwordInput.value;

      if (!username) {
        alert('Username cannot be empty.');
        return;
      }

      const hasPasswordChange = newPassword.length > 0;
      const confirmed = await showConfirm({
        title: `Update User Account?`,
        message: hasPasswordChange
          ? `You are about to change the username and reset the password for this account. This action cannot be undone.`
          : `You are about to update the username for this account. Continue?`,
        okLabel: 'Yes, Update',
        okClass: hasPasswordChange ? 'danger' : 'primary',
        iconClass: hasPasswordChange ? 'danger' : 'warning',
        icon: hasPasswordChange ? '🔑' : '👤'
      });
      if (!confirmed) return;

      btn.disabled = true;
      btn.textContent = 'Saving...';

      try {
        const response = await fetch('api_update_user.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: userId, username: username, new_password: newPassword })
        });
        const res = await response.json();
        if (res.success) {
          alert('User updated successfully.');
          passwordInput.value = '';
        } else {
          alert('Error: ' + res.message);
        }
      } catch (err) {
        alert('Failed to update the user account.');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Save';
      }
    }
  </script>
</body>
</html>