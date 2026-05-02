<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> BayanTap – Login </title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="login.css">
</head>
<body>
<div class="page-wrap">
 
  <!-- LEFT PANEL — visible on desktop only -->
  <div class="left-panel">
 
    <div class="left-content">
      <!-- Logo -->
      <div class="left-logo">💧</div>
 
      <h1>BayanTap<br><span>Treasurer Portal</span></h1>
      <p>Manage household water billing, track payments, and generate official receipts for Marcos Village Water District — all in one secure place.</p>
 
      <!-- Feature highlights -->
      <div class="feature-list">
        <div class="feature-item">
          <div class="fi-icon">📋</div>
          <div>
            <strong>Resident Billing</strong>
            View and manage monthly water bills for all households
          </div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">🧾</div>
          <div>
            <strong>Official Receipts</strong>
            Generate and print payment receipts instantly
          </div>
        </div>
        <div class="feature-item">
          <div class="fi-icon">📊</div>
          <div>
            <strong>Payment Tracking</strong>
            Monitor paid, pending, and overdue accounts at a glance
          </div>
        </div>
      </div>
    </div><!-- /left-content -->
  </div><!-- /left-panel -->
 
  <!-- ══════════════════════════════════════════
       RIGHT PANEL — login form
  ══════════════════════════════════════════ -->
  <div class="right-panel">
 
    <!-- Tablet-only floating brand (visible only when left panel hidden) -->
    <div class="tablet-brand">
      <div class="tb-logo">💧</div>
      <div>
        <div class="tb-name">BayanTap</div>
        <div class="tb-sub">Marcos Village Water District</div>
      </div>
    </div>
 
    <div class="login-card">
 
      <!-- Brand row -->
      <div class="card-brand">
        <div class="card-logo">💧</div>
        <div class="card-brand-text">
          <h2>BayanTap</h2>
          <p>Marcos Village Water District</p>
        </div>
      </div>
 
      <!-- Heading -->
      <p class="sub">Sign in to access the Treasurer Portal.</p>
 
      <!-- Error / success alerts -->
      <div class="alert alert-error" id="alertError" role="alert">
        <span class="alert-icon">⚠️</span>
        <span id="alertErrorMsg">Invalid username or password. Please try again.</span>
      </div>
      <div class="alert alert-success" id="alertSuccess" role="alert">
        <span class="alert-icon">✅</span>
        <span>Login successful! Redirecting to dashboard…</span>
      </div>
 
      <!-- LOGIN FORM -->
      <form id="loginForm" onsubmit="handleLogin(event)" novalidate autocomplete="off">
 
        <!-- Username -->
        <div class="form-group">
          <label for="username">Username</label>
          <div class="input-wrap">
            <span class="input-icon">👤</span>
            <input
              type="text"
              id="username"
              name="username"
              placeholder="Enter your username"
              autocomplete="username"
              spellcheck="false"
            >
          </div>
          <div class="field-error" id="err-username">Please enter your username.</div>
        </div>
 
        <!-- Password -->
        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrap">
            <span class="input-icon">🔑</span>
            <input
              type="password"
              id="password"
              name="password"
              class="pw-input"
              placeholder="Enter your password"
              autocomplete="current-password"
            >
            <button type="button" class="toggle-pw" id="togglePw" onclick="togglePassword()" aria-label="Show/hide password" title="Show/hide password">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
          <div class="field-error" id="err-password">Please enter your password.</div>
        </div>
 
        <!-- Remember me + Forgot password -->
        <div class="remember-row">
          <label class="remember-label">
            <input type="checkbox" id="rememberMe">
            Remember me
          </label>
          <a href="#" class="forgot-link" onclick="showForgot(event)">Forgot password?</a>
        </div>
 
        <!-- Submit -->
        <button type="submit" class="btn-login" id="loginBtn">
          <span class="spinner" id="loginSpinner"></span>
          <span id="loginBtnText">🔐 Sign In</span>
        </button>
 
      </form>
 
    </div><!-- /login-card -->
  </div><!-- /right-panel -->
 
</div><!-- /page-wrap -->

 
<!-- ── FORGOT PASSWORD MODAL ── -->
<div id="forgotBackdrop" style="
  display:none; position:fixed; inset:0;
  background:rgba(15,23,42,.48); backdrop-filter:blur(3px);
  z-index:500; align-items:center; justify-content:center; padding:16px;">
 
  <div style="
    background:var(--white); border-radius:var(--radius);
    box-shadow:var(--shadow-lg); width:100%; max-width:400px;
    animation:forgotIn .22s cubic-bezier(.34,1.3,.64,1) both;">
 
    <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:18px 20px 14px;border-bottom:1px solid var(--gray-100);gap:12px;">
      <div>
        <div style="font-size:.95rem;font-weight:700;color:var(--gray-900);">🔑 Reset Password</div>
        <div style="font-size:.72rem;color:var(--gray-400);margin-top:2px;">Enter your username to receive reset instructions</div>
      </div>
    </div>
 
    <div style="padding:20px;">
      <div style="background:var(--amber-bg);border:1px solid #fcd34d;border-radius:var(--radius-sm);padding:11px 13px;font-size:.8rem;color:#92400e;margin-bottom:16px;display:flex;gap:9px;align-items:flex-start;">
        <span style="flex-shrink:0;font-size:15px;">⚠️</span>
        <span>Contact your <strong>system administrator</strong> if you need to reset your treasurer portal credentials.</span>
      </div>
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:.73rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-700);margin-bottom:6px;">Username</label>
        <input type="text" id="forgotUser" placeholder="Enter your username" style="width:100%;padding:11px 13px;border:1.5px solid var(--gray-200);border-radius:var(--radius-sm);font-family:inherit;font-size:.87rem;color:var(--gray-900);background:var(--gray-50);outline:none;min-height:46px;transition:border-color .2s,box-shadow .2s;">
      </div>
    </div>
 
    <div style="display:flex;gap:10px;padding:14px 20px 18px;border-top:1px solid var(--gray-100);justify-content:flex-end;flex-wrap:wrap;">
      <button onclick="closeForgot()" style="padding:10px 20px;border-radius:var(--radius-sm);border:none;background:var(--gray-100);color:var(--gray-700);font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;min-height:44px;">Cancel</button>
      <button onclick="submitForgot()" style="padding:10px 20px;border-radius:var(--radius-sm);border:none;background:var(--blue);color:var(--white);font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;min-height:44px;">Send Reset Link</button>
    </div>
  </div>
</div>
 
<script>

  /* ── Demo credentials ── */
  const DEMO_USER = 'treasurer';
  const DEMO_PASS = 'bayantap2026';
  /* ── Show / hide password ── */
  function togglePassword() {
    const pw  = document.getElementById('password');
    const btn = document.getElementById('togglePw');
    
    const eyeIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0Z"/><circle cx="12" cy="12" r="3"/></svg>`;
    const eyeOffIcon = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.52 13.52 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>`;

    if (pw.type === 'password') {
      pw.type      = 'text';
      btn.innerHTML = eyeOffIcon;
    } else {
      pw.type      = 'password';
      btn.innerHTML = eyeIcon;
    }
  }
 
  /* ── Field focus: clear error on that field ── */
  ['username','password'].forEach(id => {
    const el = document.getElementById(id);
    el.addEventListener('focus', () => {
      el.classList.remove('has-error');
      document.getElementById('err-' + id).classList.remove('is-visible');
      hideAlert('alertError');
    });
  });
 
  /* ── Validation helpers ── */
  function showFieldError(id, show) {
    const input = document.getElementById(id);
    const err   = document.getElementById('err-' + id);
    if (show) { input.classList.add('has-error');    err.classList.add('is-visible');    }
    else       { input.classList.remove('has-error'); err.classList.remove('is-visible'); }
  }
 
  function clearFieldErrors() {
    showFieldError('username', false);
    showFieldError('password', false);
  }
 
  function showAlert(id)  { document.getElementById(id).classList.add('is-visible');    }
  function hideAlert(id)  { document.getElementById(id).classList.remove('is-visible'); }
 
  /* ── Login handler ── */
  async function handleLogin(e) {
    e.preventDefault();
 
    const user = document.getElementById('username').value.trim();
    const pass = document.getElementById('password').value;
 
    // Clear previous state
    clearFieldErrors();
    hideAlert('alertError');
    hideAlert('alertSuccess');
 
    // Validate
    let valid = true;
    if (!user) { showFieldError('username', true); valid = false; }
    if (!pass) { showFieldError('password', true); valid = false; }
    if (!valid) return;
 
    // Loading state
    const btn     = document.getElementById('loginBtn');
    const spinner = document.getElementById('loginSpinner');
    const btnText = document.getElementById('loginBtnText');
 
    btn.disabled         = true;
    spinner.style.display = 'block';
    btnText.textContent   = 'Signing in…';
 
    try {
      const response = await fetch('process_login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username: user, password: pass })
      });
      
      const result = await response.json();
      
      // Artificial delay just to keep user's desired smooth transition feel
      setTimeout(() => {
        if (result.success) {
          // ── SUCCESS ──
          spinner.style.display = 'none';
          btnText.textContent   = '✅ Signed in!';
          btn.style.background  = 'var(--green)';
          showAlert('alertSuccess');
   
          // Redirect after short delay
          setTimeout(() => {
            window.location.href = 'dashboard.php';
          }, 1200);
   
        } else {
          // ── FAILURE ──
          btn.disabled          = false;
          spinner.style.display = 'none';
          btnText.textContent   = '🔐 Sign In';
          btn.style.background  = '';
   
          // Show error
          document.getElementById('alertErrorMsg').textContent = result.message;
          showAlert('alertError');
          showFieldError('username', true);
          showFieldError('password', true);
   
          // Focus username for retry
          document.getElementById('username').focus();
        }
      }, 700);
    } catch (err) {
        btn.disabled          = false;
        spinner.style.display = 'none';
        btnText.textContent   = '🔐 Sign In';
        btn.style.background  = '';
        document.getElementById('alertErrorMsg').textContent = 'Network error. Please try again.';
        showAlert('alertError');
    }
  }
 
  /* ── Forgot password modal ── */
  function showForgot(e) {
    e.preventDefault();
    const bd = document.getElementById('forgotBackdrop');
    bd.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    // Pre-fill username if already typed
    const u = document.getElementById('username').value.trim();
    if (u) document.getElementById('forgotUser').value = u;
  }
 
  function closeForgot() {
    document.getElementById('forgotBackdrop').style.display = 'none';
    document.body.style.overflow = '';
  }
 
  function submitForgot() {
    const u = document.getElementById('forgotUser').value.trim();
    if (!u) { document.getElementById('forgotUser').focus(); return; }
    closeForgot();
    // Show a friendly message
    document.getElementById('alertErrorMsg').textContent =
      'Reset instructions sent to the administrator for account "' + u + '". Please wait for a response.';
    document.getElementById('alertError').style.background  = 'var(--amber-bg)';
    document.getElementById('alertError').style.color       = '#92400e';
    document.getElementById('alertError').style.borderColor = '#fcd34d';
    document.querySelector('#alertError .alert-icon').textContent = '📧';
    showAlert('alertError');
  }
 
  // Close forgot modal on backdrop click
  document.getElementById('forgotBackdrop').addEventListener('click', function(e) {
    if (e.target === this) closeForgot();
  });
 
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeForgot();
  });
 
  /* ── Focus username on load ── */
  window.addEventListener('load', () => {
    document.getElementById('username').focus();
  });
</script>
 
</body>
</html>