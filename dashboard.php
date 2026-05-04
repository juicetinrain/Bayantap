<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
require_once 'db_connect.php';

// Fetch distinct months for filter
$mStmt = $pdo->query("SELECT DISTINCT billing_month FROM billings ORDER BY STR_TO_DATE(CONCAT('01 ', billing_month), '%d %b %Y') ASC");
$available_months = $mStmt->fetchAll(PDO::FETCH_COLUMN);
// Default to most recent month if not specified
$selected_month = $_GET['month'] ?? ($available_months[0] ?? date('M Y'));
if ($selected_month !== 'all' && !in_array($selected_month, $available_months) && !empty($available_months)) {
  $selected_month = $available_months[0];
}

// Compute Stats
$current_month = date('M Y');

if ($selected_month === 'all') {
    // Current month's pending bills
    $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM billings WHERE billing_month = ? AND status != 'paid'");
    $pendingStmt->execute([$current_month]);
    $pending = $pendingStmt->fetchColumn();

    // Past month's unpaid/pending bills (Overdue)
    $overdueStmt = $pdo->prepare("SELECT COUNT(*) FROM billings WHERE billing_month != ? AND status != 'paid' 
      AND STR_TO_DATE(CONCAT('01 ', billing_month), '%d %b %Y') < STR_TO_DATE(CONCAT('01 ', ?), '%d %b %Y')");
    $overdueStmt->execute([$current_month, $current_month]);
    $overdue = $overdueStmt->fetchColumn();

    // Total Paid
    $paidStmt = $pdo->query("SELECT COUNT(*) FROM billings WHERE status = 'paid'");
    $paid = $paidStmt->fetchColumn();

    $stats = [
        'total_households' => $pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn(),
        'paid' => $paid,
        'pending' => $pending,
        'unpaid' => $overdue
    ];
} else {
    // When a specific month is selected, we follow that month's status
    $statsStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM residents) AS total_households,
            (SELECT COUNT(*) FROM billings WHERE billing_month = ? AND status = 'paid') AS paid,
            (SELECT COUNT(*) FROM billings WHERE billing_month = ? AND status = 'pending') AS pending,
            (SELECT COUNT(*) FROM billings WHERE billing_month = ? AND status = 'unpaid') AS unpaid
    ");
    $statsStmt->execute([$selected_month, $selected_month, $selected_month]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BayanTap – Treasurer Portal</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="CSS/dashboard.css">
</head>

<body>

  <!-- ══════════════════════════════════════════
     NAVIGATION BAR
  ══════════════════════════════════════════ -->
  <?php include 'navbar.php'; ?>

  <!-- HERO STATS -->
  <!-- PAGE HEADER -->
  <div class="page-header">
    <div class="page-title-section">
      <h1 class="page-title">Dashboard Overview</h1>
      <p class="page-subtitle">Welcome back! Here's what's happening today.</p>
    </div>
  </div>

  <!-- MAIN -->
  <div class="main">
    <?php
    $total_bills = ($stats['paid'] ?? 0) + ($stats['pending'] ?? 0) + ($stats['unpaid'] ?? 0);
    $paid_pct = $total_bills > 0 ? round(($stats['paid'] / $total_bills) * 100) : 0;
    $pending_pct = $total_bills > 0 ? round(($stats['pending'] / $total_bills) * 100) : 0;
    $unpaid_pct = $total_bills > 0 ? round(($stats['unpaid'] / $total_bills) * 100) : 0;
    ?>
    <div class="stats-grid" style="margin-bottom: 24px;">
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon">📋</div>
          <span class="stat-badge badge-blue">Total</span>
        </div>
        <div class="stat-label">Total Households</div>
        <div class="stat-value">
          <?= number_format($stats['total_households'] ?? 0) ?>
        </div>
        <div class="stat-sub neutral">📈 Active Accounts</div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon">✅</div>
          <span class="stat-badge badge-green"><?= $paid_pct ?>%</span>
        </div>
        <div class="stat-label">Paid This Month</div>
        <div class="stat-value">
          <?= number_format($stats['paid'] ?? 0) ?>
        </div>
        <div class="stat-sub positive">📈 12% from last month</div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon">🕐</div>
          <span class="stat-badge badge-amber"><?= $pending_pct ?>%</span>
        </div>
        <div class="stat-label">Pending</div>
        <div class="stat-value">
          <?= number_format($stats['pending'] ?? 0) ?>
        </div>
        <div class="stat-sub neutral">Awaiting Collection</div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div class="stat-icon">⛔</div>
          <span class="stat-badge badge-red"><?= $unpaid_pct ?>%</span>
        </div>
        <div class="stat-label">Overdue</div>
        <div class="stat-value">
          <?= number_format($stats['unpaid'] ?? 0) ?>
        </div>
        <div class="stat-sub alert">Requires follow-up</div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <!-- Search -->
      <div class="search-wrap">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8" />
          <path d="m21 21-4.35-4.35" />
        </svg>
        <input type="text" id="searchInput" placeholder="Search by name or household number…">
      </div>

      <!-- Status filter -->
      <div class="filter-wrap">
        <select class="filter-select" id="statusFilter" aria-label="Filter by status">
          <option value="">All Status</option>
          <option value="paid">Paid</option>
          <option value="unpaid">Unpaid</option>
          <option value="pending">Pending</option>
        </select>
      </div>

      <!-- Month filter -->
      <div class="filter-wrap">
        <select class="filter-select" id="monthFilter"
          onchange="window.location.href='?month=' + encodeURIComponent(this.value)" aria-label="Filter by month">
          <option value="all" <?= $selected_month === 'all' ? 'selected' : '' ?>>All Time</option>
          <?php foreach ($available_months as $m): ?>
            <option value="<?= htmlspecialchars($m) ?>" <?= $m === $selected_month ? 'selected' : '' ?>>
              <?= htmlspecialchars($m) ?>
            </option>
            <?php
          endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid" style="grid-template-columns: 1fr;">

      <!-- Bills Table -->
      <div class="table-card">
        <div class="table-card-header">
          <div class="header-left">
            <h2>Resident Bills</h2>
            <p>
              <?= $selected_month === 'all' ? 'All Time' : htmlspecialchars($selected_month) ?> · Marcos Village
            </p>
          </div>
        </div>
        <!-- table-wrap handles horizontal scroll on small screens -->
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Household</th>
                <th>Name</th>
                <th>Usage</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if ($selected_month === 'all') {
                  $stmt = $pdo->prepare("SELECT b.*, r.block_no, r.lot_no, r.full_name, r.household_id FROM billings b JOIN residents r ON b.resident_id = r.id ORDER BY b.id ASC");
                  $stmt->execute();
              } else {
                  $stmt = $pdo->prepare("SELECT b.*, r.block_no, r.lot_no, r.full_name, r.household_id FROM billings b JOIN residents r ON b.resident_id = r.id WHERE b.billing_month = ? ORDER BY b.id ASC");
                  $stmt->execute([$selected_month]);
              }
              while ($row = $stmt->fetch()):
                $usage_main = $row['usage_m3'] . " m³";
                $usage_range = $row['previous_reading'] . " → " . $row['current_reading'];
                $amount = "₱" . number_format($row['amount_due'], 2);

                // Calculate remaining balance for partial payments
                $remaining_balance = $row['amount_due'];
                if ($row['status'] === 'partial') {
                    $paidStmt = $pdo->prepare("SELECT SUM(amount_paid) as total_paid FROM transactions WHERE receipt_no = ?");
                    $paidStmt->execute([$row['receipt_no']]);
                    $paidRow = $paidStmt->fetch(PDO::FETCH_ASSOC);
                    $total_paid = (float)($paidRow['total_paid'] ?? 0);
                    $remaining_balance = (float)$row['amount_due'] - $total_paid;
                    $amount = "₱" . number_format($remaining_balance, 2) . " <span style='font-size:0.75rem; color:var(--gray-400);'>(remaining)</span>";
                }

                $current_month_comp = date('M Y');
                $is_past_month = ($row['billing_month'] !== $current_month_comp && 
                                 strtotime("01 " . $row['billing_month']) < strtotime("01 " . $current_month_comp));

                if ($row['status'] === 'paid') {
                  $status_chip = '<span class="status-chip chip-paid">PAID</span>';
                  $date_text = "Paid: " . date("M j, Y", strtotime($row['paid_date'] ?? 'now'));
                } elseif ($row['status'] === 'started') {
                  $status_chip = '<span class="status-chip chip-started">STARTED</span>';
                  $date_text = "New Account";
                } elseif ($row['status'] === 'partial') {
                  $status_chip = '<span class="status-chip chip-partial" style="background:#e0e7ff;color:#4f46e5;">PARTIAL PAYMENT</span>';
                  $date_text = "Partially Paid";
                } elseif ($is_past_month) {
                  $status_chip = '<span class="status-chip chip-unpaid">OVERDUE</span>';
                  $date_text = "Overdue (" . $row['billing_month'] . ")";
                } elseif ($row['status'] === 'pending') {
                  $status_chip = '<span class="status-chip" style="background:var(--amber-bg);color:var(--amber);">PENDING</span>';
                  $date_text = "Pending";
                } else {
                  $status_chip = '<span class="status-chip chip-unpaid">UNPAID</span>';
                  $date_text = "Unpaid";
                }
                ?>
                <tr>
                  <td>
                    <div class="hh-cell">
                      <div class="hh-id-pill" style="background:var(--blue-light); color:var(--blue); font-weight:700; font-size:0.75rem; padding:4px 10px; border-radius:20px; border:1px solid rgba(37,99,235,0.1);">
                        <?= htmlspecialchars($row['household_id'] ?? 'N/A') ?>
                      </div>
                      <span class="hh-id" style="font-size:0.75rem; color:var(--gray-400); margin-top:2px;">
                        <?= htmlspecialchars($row['block_no'] . ' ' . $row['lot_no']) ?>
                      </span>
                    </div>
                  </td>
                  <td>
                    <div class="res-name">
                      <?= htmlspecialchars($row['full_name']) ?>
                    </div>
                    <div class="res-date">
                      <?= htmlspecialchars($date_text) ?>
                    </div>
                  </td>
                  <td>
                    <div class="usage-main">
                      <?= htmlspecialchars($usage_main) ?>
                    </div>
                    <div class="usage-range">
                      <?= htmlspecialchars($usage_range) ?>
                    </div>
                  </td>
                  <td><span class="amount">
                      <?php
                      // Display amount with remaining label for partial payments
                      if ($row['status'] === 'partial') {
                          echo $amount;
                      } else {
                          echo htmlspecialchars($amount);
                      }
                      ?>
                    </span></td>
                  <td>
                    <?= $status_chip ?>
                  </td>
                  </td>
                </tr>
                <?php
              endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>



    </div>
  </div>




  <!-- ============================================================
     MODAL: Edit Record (step 1 — form)
============================================================ -->


  <script>
    /* ============================================================
       NAVBAR — active links, dropdowns, hamburger, breadcrumb
    ============================================================ */

    /* Active nav link (desktop) */
    function setActive(btn) {
      if (!btn) return;
      document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
      btn.classList.add('active');
      updateBreadcrumb(btn.dataset.page);
      closeAllDropdowns();
      closeDrawer();
    }

    /* Active drawer link (mobile) */
    function setActiveDrawer(btn, page) {
      document.querySelectorAll('.drawer-link').forEach(l => l.classList.remove('active'));
      btn.classList.add('active');
      // Sync desktop link too
      const desktop = document.querySelector('.nav-link[data-page="' + page + '"]');
      if (desktop) {
        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
        desktop.classList.add('active');
      }
      updateBreadcrumb(page);
      closeDrawer();
    }

    /* Breadcrumb */
    const pageLabels = {
      dashboard: 'Dashboard',
      residents: 'Residents',
      billing: 'Billing',
      reports: 'Reports',
      settings: 'Settings',
    };
    function updateBreadcrumb(page) {
      const el = document.getElementById('bcCurrent');
      if (el) el.textContent = pageLabels[page] || 'Dashboard';
    }



    /* ============================================================
       HELPERS
    ============================================================ */
    function openModal(id) { document.getElementById(id).classList.add('is-open'); document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).classList.remove('is-open'); document.body.style.overflow = ''; }
    function closeAllModals() {
      document.querySelectorAll('.modal-backdrop.is-open').forEach(m => m.classList.remove('is-open'));
      document.body.style.overflow = '';
    }

    // Close modal on backdrop click
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
      backdrop.addEventListener('click', function (e) {
        if (e.target === this) closeModal(this.id);
      });
    });
    // Close on Escape
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllModals(); });







    /* ============================================================
       VISUAL FLASH — brief highlight after save
    ============================================================ */
    function flashRow(row, color) {
      row.style.transition = 'background .15s';
      row.style.background = color;
      setTimeout(() => { row.style.background = ''; }, 900);
    }

    /* ============================================================
       LIVE SEARCH + STATUS FILTER
    ============================================================ */
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');

    function applyFilters() {
      const query = searchInput.value.toLowerCase().trim();
      const status = statusFilter.value.toLowerCase();
      document.querySelectorAll('tbody tr').forEach(row => {
        const name = row.querySelector('.res-name')?.textContent.toLowerCase() || '';
        const hhId = row.querySelector('.hh-id-pill')?.textContent.toLowerCase() || '';
        const chipText = row.querySelector('.status-chip')?.textContent.toLowerCase() || '';
        const matchSearch = !query || name.includes(query) || hhId.includes(query);
        const matchStatus = !status || chipText.includes(status);
        row.style.display = (matchSearch && matchStatus) ? '' : 'none';
      });
    }



    searchInput.addEventListener('input', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
  </script>

</body>

</html>