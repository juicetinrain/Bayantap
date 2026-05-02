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
            <div class="av-name"><?= htmlspecialchars(ucfirst($_SESSION['username'] ?? 'User')) ?></div>
            <div class="av-role"><?= htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Staff')) ?></div>
          </div>
          <span class="avatar-caret">▾</span>
        </button>
        <div class="user-dropdown" id="userDropdown">
          <div class="ud-header">
            <div class="ud-name"><?= htmlspecialchars(ucfirst($_SESSION['username'] ?? 'User')) ?></div>
            <div class="ud-email">user@bayantap.gov.ph</div>
          </div>
          <button class="ud-item" onclick="closeUserMenu()"><span class="ud-icon">👤</span> My Profile</button>
          <button class="ud-item" onclick="location.href='settings.php'"><span class="ud-icon">⚙️</span> Settings</button>
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
    <button class="drawer-link"><span class="dl-icon">👤</span> My Profile</button>
    <button class="drawer-link"><span class="dl-icon">🔔</span> Notifications <span class="dl-badge">3</span></button>

    <div class="drawer-divider"></div>
    <a class="drawer-link danger" href="logout.php"><span class="dl-icon">🚪</span> Log Out</a>

  </div><!-- /mobile-drawer -->
</nav>

<script>
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
    
    if(nd) nd.classList.remove('is-open');
    if(ud) ud.classList.remove('is-open');
    if(ub) ub.classList.remove('open');
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
    }
  });
</script>
