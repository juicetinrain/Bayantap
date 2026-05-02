<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BayanTap – Log Out</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="CSS/logout.css">
</head>
<body>
 
<!-- Background -->
<div class="page-bg">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>
 
<!-- Center content -->
<div class="center">
  <div class="card" id="logoutCard">
 
    <!-- ── CONFIRM STATE (default) ── -->
    <div id="confirmState">
 
      <!-- Brand -->
      <div class="brand">
        <div class="brand-logo">💧</div>
        <div class="brand-text">
          <h1>BayanTap</h1>
          <p>Marcos Village Water District</p>
        </div>
      </div>
 
      <!-- Active session pill -->
      <div class="session-pill">
        <span class="session-dot"></span>
        Active Session
      </div>
 
      <!-- Warning icon -->
      <div class="icon-ring">🔒</div>
 
      <h2>Log Out of Portal?</h2>
      <p class="subtitle">
        You're about to end your session as <strong>Treasurer Portal</strong>.<br>
        Make sure all records are saved before leaving.
      </p>
 
      <!-- Session summary -->
      <div class="session-box">
        <div class="sb-title">Current Session</div>
        <div class="session-row">
          <span class="sr-key">Portal</span>
          <span class="sr-val">Treasurer Portal</span>
        </div>
        <div class="session-row">
          <span class="sr-key">District</span>
          <span class="sr-val">Marcos Village</span>
        </div>
        <div class="session-row">
          <span class="sr-key">Session started</span>
          <span class="sr-val" id="sessionTime">—</span>
        </div>
      </div>
 
      <!-- Buttons -->
      <div class="btn-group">
        <button class="btn btn-danger" onclick="confirmLogout()">
          🔒 Yes, Log Me Out
        </button>
        <div class="divider"><span>or</span></div>
        <a href="dashboard.php" class="btn btn-secondary">
          ← Stay on Dashboard
        </a>
      </div>
 
      <div class="card-footer">
        Your session data is secure. Logging out will clear your<br>
        active session from this device.<br><br>
      </div>
 
    </div><!-- /confirmState -->
 
    <!-- ── LOGGED OUT STATE (shown after confirming) ── -->
    <div class="logged-out-state" id="loggedOutState">
 
      <div class="check-ring">✅</div>
 
      <h2>You've been logged out</h2>
      <p>Your session has ended. All data has been saved securely. Thank you for using BayanTap Treasurer Portal.</p>
 
      <div class="countdown-wrap">
        <span>Redirecting to login in</span>
        <span class="countdown-num" id="countdownNum">5</span>
        <span>seconds…</span>
      </div>
 
      <div class="btn-group">
        <a href="login.php" class="btn btn-secondary" style="background:var(--blue);color:#fff;box-shadow:0 4px 14px rgba(37,99,235,.28);">
          🔑 Log Back In
        </a>
        <a href="dashboard.php" class="btn btn-secondary">
          🏠 Go to Homepage
        </a>
      </div>
 
      <div class="card-footer" style="margin-top:20px;">
        BayanTap Water District · Marcos Village<br>
      </div>
 
    </div><!-- /loggedOutState -->
 
  </div><!-- /card -->
</div><!-- /center -->
 
<!-- Bottom watermark -->
<div class="watermark">
  © 2026 BayanTap · Marcos Village Water District · Treasurer Portal
</div>
 
<script>
  /* ── Set session start time to now ── */
  (function() {
    const now = new Date();
    const h   = now.getHours();
    const m   = String(now.getMinutes()).padStart(2, '0');
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12  = h % 12 || 12;
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const label = `${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()} · ${h12}:${m} ${ampm}`;
    document.getElementById('sessionTime').textContent = label;
  })();
 
  /* ── Confirm logout ── */
  function confirmLogout() {
    const btn = document.querySelector('.btn-danger');
 
    // Button loading state
    btn.disabled    = true;
    btn.textContent = '⏳ Logging out…';
    btn.style.opacity = '.75';
 
    // Simulate a brief "processing" delay then switch states
    setTimeout(async () => {
      await fetch('process_logout.php');
      document.getElementById('confirmState').style.display  = 'none';
      const out = document.getElementById('loggedOutState');
      out.style.display = 'block';
      startCountdown();
    }, 900);
  }
 
  /* ── Countdown → auto-redirect ── */
  function startCountdown() {
    let secs = 5;
    const el = document.getElementById('countdownNum');
    const interval = setInterval(() => {
      secs--;
      el.textContent = secs;
      if (secs <= 0) {
        clearInterval(interval);
        // In production this would redirect to the real login page:
        window.location.href = 'login.php';
      }
    }, 1000);
  }
</script>
 
</body>
</html>