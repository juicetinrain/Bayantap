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
// Compute Stats
$current_month = date('M Y');

if ($selected_month === 'all') {
  // Current month's pending bills (excluding started setup records)
  $pendingStmt = $pdo->prepare("SELECT COUNT(*) FROM billings WHERE billing_month = ? AND status NOT IN ('paid', 'started')");
  $pendingStmt->execute([$current_month]);
  $pending = $pendingStmt->fetchColumn();

  // Past month's unpaid/pending bills (Overdue) - strictly excluding started
  $overdueStmt = $pdo->prepare("SELECT COUNT(*) FROM billings WHERE billing_month != ? AND status NOT IN ('paid', 'started') 
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

// Fetch current system rate
$system_rate = get_setting('current_rate', '33.70');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BayanTap – Billings</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css?v=5">
  <link rel="stylesheet" href="CSS/billings.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>

<body>

  <!-- ══════════════════════════════════════════
     NAVIGATION BAR
  ══════════════════════════════════════════ -->
  <?php include 'navbar.php'; ?>

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div class="page-title-section">
      <h1 class="page-title">Billing & Collections</h1>
      <p class="page-subtitle">Manage monthly billing statements, receipts, and water usage calculations.</p>
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
          <option value="overdue">Overdue</option>
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
    <div class="content-grid">

      <!-- Bills Table -->
      <div class="table-card">
        <div class="table-card-header">
          <div class="header-left">
            <h2>Billing & Receipts</h2>
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
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if ($selected_month === 'all') {
                $stmt = $pdo->prepare("SELECT b.*, r.block_no, r.lot_no, r.full_name, r.access_token, r.household_id, r.email, r.contact_number FROM billings b JOIN residents r ON b.resident_id = r.id ORDER BY b.id ASC");
                $stmt->execute();
              } else {
                $stmt = $pdo->prepare("SELECT b.*, r.block_no, r.lot_no, r.full_name, r.access_token, r.household_id, r.email, r.contact_number FROM billings b JOIN residents r ON b.resident_id = r.id WHERE b.billing_month = ? ORDER BY b.id ASC");
                $stmt->execute([$selected_month]);
              }
              while ($row = $stmt->fetch()):
                $usage_main = $row['usage_m3'] . " m³";
                $usage_range = $row['previous_reading'] . " → " . $row['current_reading'];
                $amount = "₱" . number_format($row['amount_due'], 2);

                $current_month_comp = date('M Y');
                $is_past_month = ($row['billing_month'] !== $current_month_comp &&
                  strtotime("01 " . $row['billing_month']) < strtotime("01 " . $current_month_comp));

                if ($row['status'] === 'paid') {
                  $status_chip = '<span class="status-chip chip-paid">PAID</span>';
                  $date_text = "Paid: " . date("M j, Y", strtotime($row['paid_date'] ?? 'now'));
                } elseif ($row['status'] === 'started') {
                  $status_chip = '<span class="status-chip chip-started">STARTED</span>';
                  $date_text = "New Account";
                } elseif ($is_past_month) {
                  // If it's a past month and not paid, it's OVERDUE
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
                <tr onclick="selectRow(this)" style="cursor: pointer;"
                  data-token="<?= htmlspecialchars($row['access_token'] ?? '') ?>"
                  data-status="<?= htmlspecialchars($row['status'] ?? 'unpaid') ?>"
                  data-email="<?= htmlspecialchars($row['email'] ?? '') ?>"
                  data-contact="<?= htmlspecialchars($row['contact_number'] ?? '') ?>"
                  data-id="<?= htmlspecialchars($row['id']) ?>"
                  data-hh-id="<?= htmlspecialchars($row['household_id'] ?? 'N/A') ?>"
                  data-remarks="<?= htmlspecialchars($row['remarks'] ?? '') ?>"
                  data-prev-img="<?= htmlspecialchars($row['previous_reading_image'] ?? '') ?>"
                  data-curr-img="<?= htmlspecialchars($row['current_reading_image'] ?? '') ?>"
                  data-resident-id="<?= htmlspecialchars($row['resident_id']) ?>">
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
                      <?= htmlspecialchars($amount) ?>
                    </span></td>
                  <td>
                    <?= $status_chip ?>
                  </td>
                  <td class="action-cell">
                    <button class="action-btn" title="Actions" onclick="toggleMenu(this)">⋯</button>
                    <div class="action-menu" role="menu">
                      <button onclick="openEditRecord(this)"><span class="menu-icon">✏️</span> Input Reading</button>
                      <button onclick="openRemark(this)"><span class="menu-icon">📝</span> Edit Remark</button>
                      <button onclick="openViewReceipt(this)"><span class="menu-icon">👁️</span> View Receipt</button>
                    </div>
                  </td>
                </tr>
                <?php
              endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Receipt Card -->
      <div class="receipt-card">
        <div class="receipt-header">
          <div>
            <div class="title">Receipt Review</div>
            <div class="sub">Official payment receipt</div>
          </div>
          <button class="btn-print" onclick="printReceiptFromCard()">🖨️ PRINT</button>
        </div>
        <div class="receipt-body">
          <div class="receipt-logo">
            <div class="r-title">BAYANTAP</div>
            <div class="r-sub">Marcos Village Water District</div>
            <div class="r-off">Official Payment Receipt</div>
          </div>
          <hr class="receipt-divider">
          <div class="receipt-meta">
            <div class="receipt-row"><span class="key">Receipt No:</span><span class="val bold"
                id="rc-receipt-no">MV-2026-0XXX</span></div>
            <div class="receipt-row"><span class="key">Date:</span><span class="val" id="rc-date">January 20,
                2026</span></div>
            <div class="receipt-row"><span class="key">Household No:</span><span class="val" id="rc-hh">---</span></div>
            <div class="receipt-row"><span class="key">Name:</span><span class="val bold" id="rc-name">---</span></div>
            <div class="receipt-row"><span class="key">Email:</span><span class="val" id="rc-email">---</span></div>
            <div class="receipt-row"><span class="key">Contact No:</span><span class="val" id="rc-contact">---</span></div>
          </div>
          <div class="consumption-box">
            <div class="c-title">Charges Summary</div>
            <div class="c-row"><span>Reading Range:</span><span id="rc-range">--- m³</span></div>
            <div class="c-row"><span>Total Water Usage:</span><span id="rc-usage">--- m³</span></div>
            <div class="c-row" style="margin-top: 4px; border-top: 1px dashed var(--gray-100); padding-top: 4px;">
              <span>Calculation:</span>
              <span id="rc-calc">--- m³ × ₱33.70</span>
            </div>
          </div>
          <div class="total-row">
            <span class="label">Total Amount Due:</span>
            <span class="amount" id="rc-amount">₱0.00</span>
          </div>
          <div id="rc-status-badge" class="paid-badge"></div>
          <hr class="receipt-divider">
          <div class="sig-row">
            <div class="sig-box">
              <div class="sig-label">Resident Signature</div>
              <div class="sig-name" id="rc-sig-res">---</div>
            </div>
            <div class="sig-box">
              <div class="sig-label">Treasurer Signature</div>
              <div class="sig-name"><?= htmlspecialchars(ucfirst($_SESSION['username'] ?? 'User')) ?></div>
            </div>
          </div>
          <div class="qr-container" id="rc-qr-parent" style="display:none;">
            <div id="rc-qr"></div>
            <div class="qr-label">Scan to view balance & history</div>
          </div>
          <div class="receipt-footer">
            Official Receipt · BayanTap Water District<br>
            For inquiries: Barangay Hall, Marcos Village
          </div>
        </div>
      </div>

    </div>
  </div>


  <!-- ============================================================
     MODAL: View Receipt
============================================================ -->
  <div class="modal-backdrop" id="modalViewReceipt" role="dialog" aria-modal="true" aria-labelledby="viewReceiptTitle">
    <div class="modal">
      <div class="modal-header">
        <div>
          <h3 id="viewReceiptTitle">👁️ View Receipt</h3>
          <div class="modal-sub">Official payment receipt</div>
        </div>
        <button class="modal-close" onclick="closeModal('modalViewReceipt')" aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <div class="modal-receipt-logo">
          <div class="r-title">BAYANTAP</div>
          <div class="r-sub">Marcos Village Water District</div>
          <div class="r-off">Official Payment Receipt</div>
        </div>
        <hr class="receipt-divider">
        <div class="receipt-meta">
          <div class="receipt-row"><span class="key">Receipt No:</span><span class="val bold"
              id="vr-receipt-no">—</span></div>
          <div class="receipt-row"><span class="key">Date:</span><span class="val" id="vr-date">—</span></div>
          <div class="receipt-row"><span class="key">Household No:</span><span class="val" id="vr-hh">—</span></div>
          <div class="receipt-row"><span class="key">Name:</span><span class="val bold" id="vr-name">—</span></div>
          <div class="receipt-row"><span class="key">Email:</span><span class="val" id="vr-email">—</span></div>
          <div class="receipt-row"><span class="key">Contact No:</span><span class="val" id="vr-contact">—</span></div>
        </div>
        <div class="consumption-box">
          <div class="c-title">Billing Details</div>
          <div class="c-row"><span>Usage:</span><span id="vr-usage">—</span></div>
          <div class="c-row"><span>Reading Range:</span><span id="vr-range">—</span></div>
          <div class="c-row" style="margin-top: 4px; border-top: 1px dashed var(--gray-100); padding-top: 4px;">
            <span>Rate Calculation:</span>
            <span id="vr-calc">— × ₱33.70</span>
          </div>
        </div>
        <div class="total-row">
          <span class="label">Total Amount Due:</span>
          <span class="amount" id="vr-amount">—</span>
        </div>
        <div id="vr-status-badge"></div>
        <div class="qr-container" id="vr-qr-parent" style="display:none;">
          <div id="vr-qr"></div>
          <div class="qr-label">Scan to view balance & history</div>
        </div>

        <!-- Official Signatures -->
        <div class="sig-row">
          <div class="sig-box">
            <div class="sig-label">Resident Signature</div>
            <div class="sig-name" id="vr-sig-res">---</div>
          </div>
          <div class="sig-box">
            <div class="sig-label">Treasurer Signature</div>
            <div class="sig-name"><?= htmlspecialchars(ucfirst($_SESSION['username'] ?? 'User')) ?></div>
          </div>
        </div>

        <hr class="receipt-divider">
        <div class="receipt-footer">
          Official Receipt · BayanTap Water District<br>
          For inquiries: Barangay Hall, Marcos Village
        </div>
      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('modalViewReceipt')">Close</button>
        <button class="modal-btn modal-btn-primary" onclick="sendEmailReceipt()" id="emailReceiptBtn" style="background:var(--blue-light); color:var(--blue); border:1px solid var(--blue-200);">📧 Send Email</button>
        <button class="modal-btn modal-btn-primary" onclick="window.print()">🖨️ Print</button>
      </div>
    </div>
  </div>

  <!-- ============================================================
     MODAL: Edit Record (step 1 — form)
============================================================ -->
  <div class="modal-backdrop" id="modalEditForm" role="dialog" aria-modal="true" aria-labelledby="editFormTitle">
    <div class="modal">
      <div class="modal-header">
        <div>
          <h3 id="editFormTitle">✏️ Edit Record</h3>
          <div class="modal-sub" id="edit-modal-sub">Editing household record</div>
        </div>
        <button class="modal-close" onclick="closeModal('modalEditForm')" aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="edit-id">
        <input type="hidden" id="edit-res-id">
        <div class="form-row">
          <div class="form-group">
            <label>Household No.</label>
            <input type="text" id="edit-hh" placeholder="e.g. Blk 9 Lot 2" readonly
              style="background:var(--gray-50);color:var(--gray-500);">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select id="edit-status">
              <optgroup label="Current Status">
                <option value="paid">Paid</option>
                <option value="pending">Pending</option>
              </optgroup>
              <optgroup label="Past Status">
                <option value="unpaid">Overdue</option>
              </optgroup>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Resident Name</label>
          <input type="text" id="edit-name" placeholder="Full name">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Usage (m³)</label>
            <input type="number" id="edit-usage" placeholder="Auto-calculated" min="0" readonly style="background: var(--gray-50); cursor: not-allowed; color: var(--gray-500); border-color: var(--gray-200);">
          </div>
          <div class="form-group">
            <label>Rate (₱)</label>
            <input type="number" id="edit-rate" placeholder="e.g. <?= htmlspecialchars($system_rate) ?>" min="0" step="0.01" value="<?= htmlspecialchars($system_rate) ?>" readonly style="background: var(--gray-50); cursor: not-allowed; color: var(--gray-500); border-color: var(--gray-200);">
          </div>
          <div class="form-group">
            <label>Amount Due (₱)</label>
            <input type="number" id="edit-amount" placeholder="e.g. 575.00" min="0" step="0.01" readonly style ="background: var(--gray-50); cursor: not-allowed; color: var(--gray-500); border-color: var(--gray-200);">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Previous Reading</label>
            <input type="number" id="edit-prev" placeholder="e.g. 1245" min="0" readonly style ="background: var(--gray-50); cursor: not-allowed; color: var(--gray-500); border-color: var(--gray-200);">
          </div>
          <div class="form-group">
            <label>Current Reading</label>
            <input type="number" id="edit-curr" placeholder="e.g. 1268" min="0">
          </div>
        </div>

        <!-- Meter Photo Row -->
        <div class="form-row" style="margin-top: 8px;">
          <div class="form-group">
            <label>Previous Photo (Evidence)</label>
            <div class="img-preview-box" id="prev-img-preview" onclick="openFullImage(this)">
                <span class="img-preview-label">No previous photo</span>
            </div>
            <input type="hidden" id="edit-prev-img-path">
          </div>
          <div class="form-group">
            <label>Current Photo (Attach)</label>
            <div class="img-preview-box" id="curr-img-preview" onclick="document.getElementById('edit-curr-img-input').click()">
                <span style="font-size: 1.2rem; margin-bottom: 2px;">📸</span>
                <span class="img-preview-label">Click to Upload</span>
            </div>
            <input type="file" id="edit-curr-img-input" style="display:none;" accept="image/*" onchange="handleImagePreview(this, 'curr-img-preview')">
          </div>
        </div>
      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('modalEditForm')">Cancel</button>
        <button class="modal-btn modal-btn-warning" onclick="requestEditConfirm()">✏️ Review Changes</button>
      </div>
    </div>
  </div>

  <!-- ============================================================
     MODAL: Edit Confirm (step 2 — confirm)
============================================================ -->
  <div class="modal-backdrop" id="modalEditConfirm" role="dialog" aria-modal="true" aria-labelledby="editConfirmTitle">
    <div class="modal">
      <div class="modal-header">
        <div>
          <h3 id="editConfirmTitle">⚠️ Confirm Changes</h3>
          <div class="modal-sub">Please review before saving</div>
        </div>
        <button class="modal-close" onclick="closeModal('modalEditConfirm')" aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <div class="confirm-icon warning">⚠️</div>
        <div class="confirm-text">
          <h4>Save these changes?</h4>
          <p>You're about to update the record for <strong id="ec-name">—</strong>. This action will overwrite the
            existing data.</p>
        </div>
        <div class="confirm-detail">
          <div class="cd-row"><span>Household</span><span id="ec-hh">—</span></div>
          <div class="cd-row"><span>Status → New</span><span id="ec-status">—</span></div>
          <div class="cd-row"><span>Usage</span><span id="ec-usage">—</span></div>
          <div class="cd-row"><span>Amount</span><span id="ec-amount">—</span></div>
          <div class="cd-row"><span>Reading</span><span id="ec-range">—</span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-cancel" onclick="backToEditForm()">← Go Back</button>
        <button class="modal-btn modal-btn-primary" onclick="confirmEdit()">✔ Save Changes</button>
      </div>
    </div>
  </div>

  <!-- ============================================================
     MODAL: Delete Confirm
============================================================ -->
  <div class="modal-backdrop" id="modalDeleteConfirm" role="dialog" aria-modal="true"
    aria-labelledby="deleteConfirmTitle">
    <div class="modal">
      <div class="modal-header">
        <div>
          <h3 id="deleteConfirmTitle">🗑️ Delete Record</h3>
          <div class="modal-sub">This action cannot be undone</div>
        </div>
        <button class="modal-close" onclick="closeModal('modalDeleteConfirm')" aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <div class="confirm-icon danger">🗑️</div>
        <div class="confirm-text">
          <h4>Delete this record?</h4>
          <p>You're about to permanently delete the record for <strong id="dc-name">—</strong>. This cannot be undone.
          </p>
        </div>
        <div class="confirm-detail">
          <div class="cd-row"><span>Household</span><span id="dc-hh">—</span></div>
          <div class="cd-row"><span>Name</span><span id="dc-name2">—</span></div>
          <div class="cd-row"><span>Amount</span><span id="dc-amount">—</span></div>
          <div class="cd-row"><span>Status</span><span id="dc-status">—</span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('modalDeleteConfirm')">Cancel</button>
        <button class="modal-btn modal-btn-danger" onclick="confirmDelete()">🗑️ Yes, Delete</button>
      </div>
    </div>
  </div>

  <!-- ============================================================
     MODAL: Add/Edit Remark
  ============================================================ -->
  <div class="modal-backdrop" id="modalRemark" role="dialog" aria-modal="true">
    <div class="modal" style="max-width:400px;">
      <div class="modal-header">
        <div>
          <h3>📝 Collection Remark</h3>
          <div class="modal-sub">Add a note to describe why this is overdue</div>
        </div>
        <button class="modal-close" onclick="closeModal('modalRemark')">✕</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="remark-billing-id">
        <div style="background: var(--gray-50); padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 0.85rem;">
          <div style="font-weight: 700; color: var(--gray-900);" id="remark-hh-info">—</div>
          <div style="color: var(--gray-500); margin-top: 2px;" id="remark-month-info">—</div>
        </div>
        <textarea id="remark-input" style="width:100%; height:120px; padding:12px; border-radius:8px; border:1.5px solid var(--gray-200); font-family:inherit; font-size: 0.9rem;" placeholder="e.g. Promised to pay by next week, disconnected, etc..."></textarea>
      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('modalRemark')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="saveRemark()">Save Remark</button>
      </div>
    </div>
  </div>

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

    /* Close action menus on outside click */
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.action-cell')) closeAllMenus();
    });

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
       ACTION CONTEXT MENU
    ============================================================ */
    function toggleMenu(btn) {
      const menu = btn.nextElementSibling;
      const isOpen = menu.classList.contains('is-open');
      closeAllMenus();
      if (!isOpen) {
        // Detect if row is near bottom of viewport to flip menu up
        const rect = btn.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        if (spaceBelow < 180) {
          menu.classList.add('drop-up');
        } else {
          menu.classList.remove('drop-up');
        }
        menu.classList.add('is-open');
        btn.classList.add('open');
      }
    }
    function closeAllMenus() {
      document.querySelectorAll('.action-menu.is-open').forEach(m => {
        m.classList.remove('is-open');
        m.previousElementSibling.classList.remove('open');
      });
    }
    document.addEventListener('click', e => { if (!e.target.closest('.action-cell')) closeAllMenus(); });

    /* ============================================================
       ROW DATA EXTRACTOR — reads data straight from the table row
    ============================================================ */
    let _activeRow = null;   // reference to the currently acting row
    let _activeData = null;  // current receipt data

    function getRowData(element) {
      const row = element.tagName === 'TR' ? element : element.closest('tr');
      _activeRow = row;
      return {
        id: row.getAttribute('data-id') || '',
        resident_id: row.getAttribute('data-resident-id') || '',
        hh: row.querySelector('.hh-id-pill')?.textContent.trim() || '—',
        name: row.querySelector('.res-name')?.textContent.trim() || '—',
        date: row.querySelector('.res-date')?.textContent.trim() || '—',
        usage: row.querySelector('.usage-main')?.textContent.trim() || '—',
        range: row.querySelector('.usage-range')?.textContent.trim() || '—',
        amount: row.querySelector('.amount')?.textContent.trim() || '—',
        status: row.getAttribute('data-status') || '—',
        remarks: row.getAttribute('data-remarks') || '',
        prev_img: row.getAttribute('data-prev-img') || '',
        curr_img: row.getAttribute('data-curr-img') || '',
        email: row.getAttribute('data-email') || '',
        token: row.getAttribute('data-token') || '',
        contact: row.getAttribute('data-contact') || ''
      };
    }

    /* ============================================================
       1. VIEW RECEIPT & SHARED DATA UPDATER
    ============================================================ */
    function formatMoney(amount) {
      return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function updateReceiptData(d) {
      if (!d) return;
      _activeData = d;

      // Extract amount
      const rawAmount = parseFloat(d.amount.replace(/[^0-9.-]+/g, "")) || 0;

      // Standard PH calculation: 
      // Total = Basic + Env Fee(20%) => Total = Basic * 1.20
      const basicCharge = rawAmount / 1.20;
      const envFee = rawAmount - basicCharge;
      const rcNo = 'MV-2026-' + Math.floor(Math.random() * 9000 + 1000);
      const isPaid = d.status.toLowerCase() === 'paid';
      const badgeText = isPaid ? '✓ Payment Received' : 'Pending/Unpaid';
      const badgeClass = isPaid ? 'paid-badge' : '';
      const rDate = d.date.replace('Paid: ', '') || 'Pending';

      // Update Modal
      document.getElementById('vr-receipt-no').textContent = rcNo;
      document.getElementById('vr-date').textContent = rDate;
      document.getElementById('vr-hh').textContent = d.hh;
      document.getElementById('vr-name').textContent = d.name;
      document.getElementById('vr-email').textContent = d.email || 'N/A';
      document.getElementById('vr-contact').textContent = d.contact || 'N/A';
      document.getElementById('vr-usage').textContent = d.usage;
      document.getElementById('vr-range').textContent = d.range;
      document.getElementById('vr-amount').textContent = '₱' + formatMoney(rawAmount);
      const usageVal = parseFloat(d.usage) || 0;
      const activeRate = usageVal > 0 ? (rawAmount / usageVal) : 33.70;
      document.getElementById('vr-calc').textContent = d.usage + ' × ₱' + formatMoney(activeRate);

      const badge = document.getElementById('vr-status-badge');
      badge.className = badgeClass;
      badge.textContent = badgeClass ? badgeText : '';

      // Update Signatures
      document.getElementById('vr-sig-res').textContent = d.name;
      document.getElementById('rc-sig-res').textContent = d.name;


      // Update On-Page Receipt Card (for Printing layout)
      document.getElementById('rc-receipt-no').textContent = rcNo;
      document.getElementById('rc-date').textContent = rDate;
      document.getElementById('rc-hh').textContent = d.hh;
      document.getElementById('rc-name').textContent = d.name;
      document.getElementById('rc-email').textContent = d.email || 'N/A';
      document.getElementById('rc-contact').textContent = d.contact || 'N/A';
      document.getElementById('rc-usage').textContent = d.usage;
      document.getElementById('rc-range').textContent = d.range;
      document.getElementById('rc-amount').textContent = '₱' + formatMoney(rawAmount);
      document.getElementById('rc-calc').textContent = d.usage + ' × ₱' + formatMoney(activeRate);

      const rcBadge = document.getElementById('rc-status-badge');
      rcBadge.className = badgeClass;
      rcBadge.textContent = badgeClass ? badgeText : '';

      // QR Code Generation
      const qrData = 'http://IP-ADDRESS-HERE/BayanTap-debug/portal.php?token=' + d.token;

      // Clear previous QR
      document.getElementById('vr-qr').innerHTML = '';
      document.getElementById('rc-qr').innerHTML = '';

      if (d.token) {
        document.getElementById('vr-qr-parent').style.display = 'flex';
        document.getElementById('rc-qr-parent').style.display = 'flex';

        new QRCode(document.getElementById('vr-qr'), {
          text: qrData,
          width: 128,
          height: 128,
          colorDark: "#0f172a",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.H
        });

        new QRCode(document.getElementById('rc-qr'), {
          text: qrData,
          width: 100,
          height: 100,
          colorDark: "#0f172a",
          colorLight: "#ffffff",
          correctLevel: QRCode.CorrectLevel.H
        });
        document.getElementById('rc-qr-parent').style.display = 'none';
      }

      // Update Email Button
      const btnEmail = document.getElementById('emailReceiptBtn');
      if (btnEmail) {
        if (!d.email) {
          btnEmail.style.display = 'none';
        } else {
          btnEmail.style.display = 'inline-block';
          btnEmail.title = 'Send to ' + d.email;
        }
      }
    }

    function openViewReceipt(btn) {
      closeAllMenus();
      const d = getRowData(btn);
      updateReceiptData(d);
      openModal('modalViewReceipt');
    }

    function selectRow(row) {
      const d = getRowData(row);
      updateReceiptData(d);

      document.querySelectorAll('tbody tr').forEach(r => r.classList.remove('selected-row'));
      row.classList.add('selected-row');
    }

    /* ============================================================
       SEND EMAIL RECEIPT
    ============================================================ */
    function sendEmailReceipt() {
      const d = _activeData;
      if (!d || !d.email) {
        alert('This household has no registered email address.');
        return;
      }

      const btn = document.getElementById('emailReceiptBtn');
      const originalText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span>⏳</span> Sending...';

      fetch('api_send_receipt.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          billing_id: d.id,
          email: d.email
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert('Receipt successfully sent to ' + d.email);
        } else {
          alert('Failed to send email: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(() => alert('Network error. Failed to send email.'))
      .finally(() => {
        btn.disabled = false;
        btn.innerHTML = originalText;
      });
    }

    /* ============================================================
       2. EDIT RECORD — two-step: form → confirm → save
    ============================================================ */
    function openEditRecord(btn) {
      closeAllMenus();
      const d = getRowData(btn);
      document.getElementById('edit-modal-sub').textContent = d.hh + ' · ' + d.name;
      document.getElementById('edit-id').value = d.id;
      document.getElementById('edit-res-id').value = d.resident_id;
      document.getElementById('edit-hh').value = d.hh;
      document.getElementById('edit-name').value = d.name;
      document.getElementById('edit-usage').value = parseFloat(d.usage) || '';
      document.getElementById('edit-amount').value = parseFloat(d.amount.replace('₱', '').replace(',', '')) || '';
      const rangeParts = d.range.split('→').map(s => s.trim());
      document.getElementById('edit-prev').value = rangeParts[0] || '';
      document.getElementById('edit-curr').value = rangeParts[1] || '';
      const statusSel = document.getElementById('edit-status');
      statusSel.value = d.status.toLowerCase().includes('paid') ? 'paid' : d.status.toLowerCase().includes('unpaid') ? 'unpaid' : 'pending';
      
      // Image Previews
      const prevBox = document.getElementById('prev-img-preview');
      const currBox = document.getElementById('curr-img-preview');
      document.getElementById('edit-prev-img-path').value = d.prev_img;
      
      if (d.prev_img) {
          prevBox.innerHTML = `<img src="uploads/meters/${d.prev_img}" alt="Previous">`;
      } else {
          prevBox.innerHTML = `<span class="img-preview-label">No previous photo</span>`;
      }

      if (d.curr_img) {
          currBox.innerHTML = `<img src="uploads/meters/${d.curr_img}" alt="Current">`;
      } else {
          currBox.innerHTML = `<span style="font-size: 1.2rem; margin-bottom: 2px;">📸</span><span class="img-preview-label">Click to Upload</span>`;
      }
      
      document.getElementById('edit-curr-img-input').value = ''; // Reset file input

      openModal('modalEditForm');
    }

    function handleImagePreview(input, boxId) {
        const box = document.getElementById(boxId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                box.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function openFullImage(box) {
        const img = box.querySelector('img');
        if (img) {
            window.open(img.src, '_blank');
        }
    }

    function requestEditConfirm() {
      // Pull values from form
      const name = document.getElementById('edit-name').value.trim();
      const hh = document.getElementById('edit-hh').value.trim();
      const usage = document.getElementById('edit-usage').value;
      const amount = document.getElementById('edit-amount').value;
      const prev = document.getElementById('edit-prev').value;
      const curr = document.getElementById('edit-curr').value;
      const status = document.getElementById('edit-status').value;

      if (!name) { document.getElementById('edit-name').focus(); return; }

      // Populate confirm modal
      document.getElementById('ec-name').textContent = name;
      document.getElementById('ec-hh').textContent = hh;
      document.getElementById('ec-status').textContent = status.charAt(0).toUpperCase() + status.slice(1);
      document.getElementById('ec-usage').textContent = usage + ' m³';
      document.getElementById('ec-amount').textContent = '₱' + parseFloat(amount || 0).toFixed(2);
      document.getElementById('ec-range').textContent = prev + ' → ' + curr;

      closeModal('modalEditForm');
      openModal('modalEditConfirm');
    }

    function backToEditForm() {
      closeModal('modalEditConfirm');
      openModal('modalEditForm');
    }

    function confirmEdit(mode = 'save') {
      if (!_activeRow) { closeAllModals(); return; }

      // Button state
      const btn = mode === 'print' ? document.getElementById('btnSavePrint') : null;
      if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }

      // Data gathering
      const billing_id = document.getElementById('edit-id').value;
      const res_id = document.getElementById('edit-res-id').value;
      const name = document.getElementById('edit-name').value.trim();
      const usage = document.getElementById('edit-usage').value;
      const amount = document.getElementById('edit-amount').value;
      const prev = document.getElementById('edit-prev').value;
      const curr = document.getElementById('edit-curr').value;
      const status = document.getElementById('edit-status').value;

      const formData = new FormData();
      formData.append('billing_id', billing_id);
      formData.append('resident_id', res_id);
      formData.append('full_name', name);
      formData.append('usage_m3', parseFloat(usage) || 0);
      formData.append('amount_due', parseFloat(amount) || 0);
      formData.append('previous_reading', parseFloat(prev) || 0);
      formData.append('current_reading', parseFloat(curr) || 0);
      formData.append('status', status);
      
      const imgInput = document.getElementById('edit-curr-img-input');
      if (imgInput.files[0]) {
          formData.append('current_reading_image', imgInput.files[0]);
      }

      fetch('api_edit_record.php', {
        method: 'POST',
        body: formData
      })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            // 1. Update UI Row
            _activeRow.querySelector('.res-name').textContent = name;
            _activeRow.querySelector('.usage-main').textContent = usage + ' m³';
            _activeRow.querySelector('.usage-range').textContent = prev + ' → ' + curr;
            _activeRow.querySelector('.amount').textContent = '₱' + parseFloat(amount || 0).toFixed(2);

            const chip = _activeRow.querySelector('.status-chip');
            chip.textContent = status.toUpperCase();
            // Reset style and class for dynamic updates
            chip.removeAttribute('style');
            if (status === 'paid') {
              chip.className = 'status-chip chip-paid';
            } else if (status === 'unpaid') {
              chip.className = 'status-chip chip-unpaid';
            } else {
              // For pending, re-apply the amber style
              chip.className = 'status-chip';
              chip.style.background = 'var(--amber-bg)';
              chip.style.color = 'var(--amber)';
            }

            // 2. Prepare receipt data for print (if requested)
            const rowData = getRowData(_activeRow);
            updateReceiptData(rowData);

            if (mode === 'print') {
              closeAllModals();
              setTimeout(() => { window.print(); }, 150);
            } else {
              closeAllModals();
            }

            flashRow(_activeRow, 'var(--blue-light)');
            _activeRow = null;

          } else {
            alert('Error saving: ' + (res.error || 'Unknown error'));
          }
        })
        .catch(err => {
          console.error(err);
          alert('Failed to connect to server.');
        })
        .finally(() => {
          if (btn) { btn.disabled = false; btn.textContent = '💾 Save & Print'; }
        });
    }

    /* ============================================================
       3. DELETE — confirm → remove row
    ============================================================ */
    function openDeleteConfirm(btn) {
      closeAllMenus();
      const d = getRowData(btn);
      document.getElementById('dc-name').textContent = d.name;
      document.getElementById('dc-name2').textContent = d.name;
      document.getElementById('dc-hh').textContent = d.hh;
      document.getElementById('dc-amount').textContent = d.amount;
      document.getElementById('dc-status').textContent = d.status;
      openModal('modalDeleteConfirm');
    }

    function confirmDelete() {
      if (_activeRow) {
        _activeRow.style.transition = 'opacity .3s, transform .3s';
        _activeRow.style.opacity = '0';
        _activeRow.style.transform = 'translateX(20px)';
        setTimeout(() => { _activeRow.remove(); _activeRow = null; }, 310);
      }
      closeAllModals();
    }

    /* ============================================================
       REMARK — Add/Edit notes on delinquency
    ============================================================ */
    function openRemark(btn) {
      closeAllMenus();
      const d = getRowData(btn);
      document.getElementById('remark-billing-id').value = d.id;
      document.getElementById('remark-hh-info').textContent = d.hh + ' · ' + d.name;
      document.getElementById('remark-month-info').textContent = 'Period: ' + d.date.split('(')[1]?.split(')')[0] || d.date;
      document.getElementById('remark-input').value = d.remarks;
      openModal('modalRemark');
    }

    function saveRemark() {
      const id = document.getElementById('remark-billing-id').value;
      const remarks = document.getElementById('remark-input').value.trim();
      const btn = document.querySelector('#modalRemark .modal-btn-primary');
      const originalText = btn.textContent;

      btn.disabled = true;
      btn.textContent = 'Saving...';

      fetch('api_update_remark.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ billing_id: id, remarks: remarks })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          if (_activeRow) {
            _activeRow.setAttribute('data-remarks', remarks);
            // Optionally update a visual indicator if needed
            flashRow(_activeRow, 'var(--amber-light)');
          }
          closeModal('modalRemark');
        } else {
          alert('Failed to save: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(() => alert('Network error. Check your connection.'))
      .finally(() => {
        btn.disabled = false;
        btn.textContent = originalText;
      });
    }

    /* ============================================================
       4. PRINT — updates data first then prints
    ============================================================ */
    function doPrint(btn) {
      closeAllMenus();
      const d = getRowData(btn);
      updateReceiptData(d);
      // Short delay to ensure DOM is flush before printing
      setTimeout(() => {
        window.print();
      }, 50);
    }

    function printReceiptFromCard() {
      // Data is already synchronized. Just open the official viewing popup.
      openModal('modalViewReceipt');
    }

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

    /* ============================================================
       AUTO-CALCULATION FOR BILLING
    ============================================================ */
    const editUsage = document.getElementById('edit-usage');
    const editRate = document.getElementById('edit-rate');
    const editAmount = document.getElementById('edit-amount');
    const editPrev = document.getElementById('edit-prev');
    const editCurr = document.getElementById('edit-curr');

    function recalcAmount() {
      const usage = parseFloat(editUsage.value) || 0;
      const rate = parseFloat(editRate.value) || <?= json_encode($system_rate) ?>;
      editAmount.value = (usage * rate).toFixed(2);
    }

    function recalcUsage() {
      const prev = parseFloat(editPrev.value) || 0;
      const curr = parseFloat(editCurr.value) || 0;
      if (curr >= prev) {
        editUsage.value = (curr - prev).toFixed(2);
        recalcAmount();
      }
    }

    editUsage.addEventListener('input', recalcAmount);
    editRate.addEventListener('input', recalcAmount);
    editPrev.addEventListener('input', recalcUsage);
    editCurr.addEventListener('input', recalcUsage);
  </script>

</body>

</html>