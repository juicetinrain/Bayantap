<?php
$current_page = basename($_SERVER['PHP_SELF']);
$is_superuser = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>
<!-- ══════════════════════════════════════════
    NAVIGATION BAR
══════════════════════════════════════════ -->
<nav>
  <div class="nav-inner">

    <!-- Brand -->
    <a class="nav-brand" href="dashboard.php">
      <div class="nav-logo">💧</div>
      <div class="nav-text">
        <h1>BayanTap</h1>
        <p>Marcos Village Water District</p>
      </div>
    </a>

    <!-- Center nav links (desktop) -->
    <div class="nav-links">
      <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
        <span class="nl-icon">🏠</span> Dashboard
      </a>
      <a class="nav-link <?= $current_page == 'residents.php' ? 'active' : '' ?>" href="residents.php">
        <span class="nl-icon">👥</span> Residents
      </a>
      <a class="nav-link <?= $current_page == 'transaction.php' ? 'active' : '' ?>" href="transaction.php">
        <span class="nl-icon">💳</span> Transactions
      </a>
      <a class="nav-link <?= $current_page == 'billings.php' ? 'active' : '' ?>" href="billings.php">
        <span class="nl-icon">🧾</span> Billing
      </a>
      <a class="nav-link <?= $current_page == 'overdue.php' ? 'active' : '' ?>" href="overdue.php">
        <span class="nl-icon">⚠️</span> Overdue
      </a>
      <a class="nav-link <?= $current_page == 'reports.php' ? 'active' : '' ?>" href="reports.php">
        <span class="nl-icon">📊</span> Reports
      </a>
      <?php if($is_superuser): ?>
        <a class="nav-link <?= $current_page == 'settings.php' ? 'active' : '' ?>" href="settings.php">
          <span class="nl-icon">⚙️</span> Settings
        </a>
      <?php endif; ?>
    </div>

    <!-- Right side -->
    <div class="nav-right">


      <!-- Divider -->
      <div style="width:1px;height:28px;background:var(--gray-200);margin:0 4px;flex-shrink:0;"></div>

      <!-- User avatar + dropdown -->
      <div class="user-menu-wrap">
        <button class="user-avatar-btn" id="userMenuBtn" onclick="toggleUserMenu()" aria-label="User menu">
          <div class="avatar-circle">🏅</div>
          <div class="avatar-info">
            <div class="av-name"><?= htmlspecialchars(ucfirst($_SESSION['display_name'] ?? 'User')) ?></div>
            <div class="av-role"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Staff')) ?></div>
          </div>
          <span class="avatar-caret">▾</span>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <div class="ud-header">
            <div class="ud-name"><?= htmlspecialchars(ucfirst($_SESSION['display_name'] ?? 'User')) ?></div>
            <div class="ud-email">user@bayantap.gov.ph</div>
          </div>
          <button class="ud-item" onclick="openProfileModal()"><span class="ud-icon">👤</span> My Profile</button>
          <hr>
          <button class="ud-item logout" onclick="location.href='logout.php'"><span class="ud-icon">🚪</span> Logout</button>
        </div>
      </div>

      <!-- Hamburger (mobile only) -->
      <button class="hamburger" id="hamburger" onclick="toggleDrawer()" aria-label="Menu">
        <span class="hb-line"></span>
        <span class="hb-line"></span>
        <span class="hb-line"></span>
      </button>

    </div><!-- /nav-right -->
  </div><!-- /nav-inner -->

  <!-- Mobile drawer -->
  <div class="mobile-drawer" id="mobileDrawer">
    <!-- User info -->
    <div class="drawer-user">
      <div class="avatar-circle">🏅</div>
      <div class="du-info">
        <div class="du-name"><?= htmlspecialchars(ucfirst($_SESSION['username'] ?? 'User')) ?></div>
        <div class="du-role"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Staff')) ?></div>
      </div>
    </div>
    <div class="drawer-divider"></div>

    <!-- Nav links -->
    <div class="drawer-section-label">Navigation</div>
    <a class="drawer-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><span class="dl-icon">🏠</span> Dashboard</a>
    <a class="drawer-link <?= $current_page == 'residents.php' ? 'active' : '' ?>" href="residents.php"><span class="dl-icon">👥</span> Residents</a>
    <a class="drawer-link <?= $current_page == 'transaction.php' ? 'active' : '' ?>" href="transaction.php"><span class="dl-icon">💳</span> Transactions</a>
    <a class="drawer-link <?= $current_page == 'billings.php' ? 'active' : '' ?>" href="billings.php">
      <span class="dl-icon">🧾</span> Billing
      <span class="dl-badge">3</span>
    </a>
    <a class="drawer-link <?= $current_page == 'overdue.php' ? 'active' : '' ?>" href="overdue.php"><span class="dl-icon">⚠️</span> Overdue</a>
    <a class="drawer-link <?= $current_page == 'reports.php' ? 'active' : '' ?>" href="reports.php"><span class="dl-icon">📊</span> Reports</a>
    <?php if($is_superuser): ?>
      <a class="drawer-link <?= $current_page == 'settings.php' ? 'active' : '' ?>" href="settings.php"><span class="dl-icon">⚙️</span> Settings</a>
    <?php endif; ?>

    <div class="drawer-divider"></div>

    <!-- Account -->
    <div class="drawer-section-label">Account</div>
    <button class="drawer-link" onclick="openProfileModal()"><span class="dl-icon">👤</span> My Profile</button>


    <div class="drawer-divider"></div>
    <a class="drawer-link danger" href="logout.php"><span class="dl-icon">🚪</span> Log Out</a>

  </div><!-- /mobile-drawer -->

  <!-- ============================================================
     MODAL: My Profile
  ============================================================ -->
  <div class="modal-backdrop" id="modalProfile" role="dialog" aria-modal="true" style="z-index: 9999;">
    <div class="modal" style="max-width: 400px; text-align: left;">
      <div class="modal-header">
        <div>
          <h3>👤 My Profile</h3>
          <div class="modal-sub">Update your account details</div>
        </div>
        <button class="modal-close" onclick="closeProfileModal()" aria-label="Close">✕</button>
      </div>
      <div class="modal-body" style="padding: 24px;">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" id="prof-display-name" value="<?= htmlspecialchars($_SESSION['display_name'] ?? '') ?>" autocomplete="name">
        </div>
        
        <hr style="border: none; border-top: 1px dashed var(--gray-200); margin: 20px 0;">
        <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 12px; font-weight: 600; text-transform: uppercase;">Security</div>

        <div class="form-group">
          <label>New Password (Optional)</label>
          <input type="password" id="prof-new-pass" placeholder="Leave blank to keep current" autocomplete="new-password">
        </div>
        <div class="form-group" style="margin-top: 12px;">
          <label>Confirm New Password</label>
          <input type="password" id="prof-confirm-pass" placeholder="Confirm new password" autocomplete="new-password">
        </div>

        <hr style="border: none; border-top: 1px dashed var(--gray-200); margin: 20px 0;">

        <div class="form-group">
          <label style="color: var(--red);">Current Password (Required)</label>
          <input type="password" id="prof-current-pass" placeholder="Enter current password to save changes" required autocomplete="current-password">
        </div>

      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-cancel" onclick="closeProfileModal()">Cancel</button>
        <button class="modal-btn modal-btn-primary" id="btnSaveProfile" onclick="submitProfileUpdate()">💾 Save Profile</button>
      </div>
    </div>
  </div>

</nav>

<script>
  /* ── Notification dropdown ── */
  function toggleNotif() {
    const dd = document.getElementById('notifDropdown');
    const isOpen = dd.classList.contains('is-open');
    closeAllDropdowns();
    if (!isOpen) {
      dd.classList.add('is-open');
      const btn = document.getElementById('notifBtn');
      if(btn) btn.style.background = 'var(--gray-100)';
    }
  }

  function markAllRead() {
    document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
    document.querySelectorAll('.unread-pip').forEach(el => el.remove());
    const dot = document.getElementById('notifDot');
    if(dot) dot.style.display = 'none';
    closeAllDropdowns();
  }

  /* ── User menu dropdown ── */
  function toggleUserMenu() {
    const dd = document.getElementById('userDropdown');
    const btn = document.getElementById('userMenuBtn');
    const isOpen = dd.classList.contains('is-open');
    closeAllDropdowns();
    if (!isOpen) {
      dd.classList.add('is-open');
      if(btn) btn.classList.add('open');
    }
  }
  function closeUserMenu() {
    const dd = document.getElementById('userDropdown');
    const btn = document.getElementById('userMenuBtn');
    if(dd) dd.classList.remove('is-open');
    if(btn) btn.classList.remove('open');
  }

  function closeAllDropdowns() {
    const nd = document.getElementById('notifDropdown');
    const ud = document.getElementById('userDropdown');
    const ub = document.getElementById('userMenuBtn');
    const nb = document.getElementById('notifBtn');
    
    if(nd) nd.classList.remove('is-open');
    if(ud) ud.classList.remove('is-open');
    if(ub) ub.classList.remove('open');
    if(nb) nb.style.background = '';
  }

  /* Close dropdowns on outside click */
  document.addEventListener('click', function (e) {
    if (!e.target.closest('nav')) closeAllDropdowns();
  });

  /* ── Hamburger / mobile drawer ── */
  function toggleDrawer() {
    const drawer = document.getElementById('mobileDrawer');
    const ham = document.getElementById('hamburger');
    if(!drawer) return;
    const isOpen = drawer.classList.contains('is-open');
    closeAllDropdowns();
    if (isOpen) {
      drawer.classList.remove('is-open');
      if(ham) ham.classList.remove('open');
    } else {
      drawer.classList.add('is-open');
      if(ham) ham.classList.add('open');
    }
  }

  function closeDrawer() {
    const drawer = document.getElementById('mobileDrawer');
    const ham = document.getElementById('hamburger');
    if(drawer) drawer.classList.remove('is-open');
    if(ham) ham.classList.remove('open');
  }

  /* Close drawer on Escape */
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { 
      closeAllDropdowns(); 
      closeDrawer(); 
      if (typeof closeAllModals === 'function') closeAllModals(); 
      closeProfileModal();
    }
  });

  /* ── Profile Modal ── */
  function openProfileModal() {
    closeAllDropdowns();
    closeDrawer();
    document.getElementById('modalProfile').classList.add('is-open');
    document.body.style.overflow = 'hidden';
  }

  function closeProfileModal() {
    document.getElementById('modalProfile').classList.remove('is-open');
    document.body.style.overflow = '';
    // Reset form
    document.getElementById('prof-new-pass').value = '';
    document.getElementById('prof-confirm-pass').value = '';
    document.getElementById('prof-current-pass').value = '';
  }

  function submitProfileUpdate() {
    const username = document.getElementById('prof-username').value.trim();
    const newPass = document.getElementById('prof-new-pass').value;
    const confirmPass = document.getElementById('prof-confirm-pass').value;
    const currentPass = document.getElementById('prof-current-pass').value;

    if (!username) {
        alert("Username cannot be empty.");
        return;
    }
    if (newPass && newPass !== confirmPass) {
        alert("New passwords do not match.");
        return;
    }
    if (!currentPass) {
        alert("Current password is required to save changes.");
        return;
    }

    const btn = document.getElementById('btnSaveProfile');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Saving...';

    const formData = new FormData();
    formData.append('username', username);
    formData.append('new_password', newPass);
    formData.append('current_password', currentPass);

    fetch('api_update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert('✅ Profile updated successfully!');
            window.location.reload(); // Reload to reflect new username in the UI
        } else {
            alert('❌ Error: ' + (res.error || 'Unknown error'));
        }
    })
    .catch(() => alert('❌ Network error. Please try again.'))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
  }

</script>