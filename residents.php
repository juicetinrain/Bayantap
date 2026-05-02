<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
require_once 'db_connect.php';

$search_q = $_GET['q'] ?? '';
$search_status = $_GET['status'] ?? '';

// Build WHERE clauses safely
$where_clauses = ["1=1"];
$params = [];

if ($search_q !== '') {
  $where_clauses[] = "(full_name LIKE ? OR block_no LIKE ? OR lot_no LIKE ? OR household_id LIKE ?)";
  $params[] = "%$search_q%";
  $params[] = "%$search_q%";
  $params[] = "%$search_q%";
  $params[] = "%$search_q%";
}

if ($search_status !== '') {
  $where_clauses[] = "status = ?";
  $params[] = $search_status;
}

$where_sql = implode(' AND ', $where_clauses);

$resStatsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_res,
        AVG(monthly_rate) AS avg_rate,
        SUM(CASE WHEN status != 'unpaid' THEN 1 ELSE 0 END) AS active_res
    FROM residents
    WHERE $where_sql
");
$resStatsStmt->execute($params);
$resStats = $resStatsStmt->fetch(PDO::FETCH_ASSOC);
$raw_total_res = $resStats['total_res'] ?? 0;
$total_res = number_format($raw_total_res);
$avg_rate = "₱" . number_format($resStats['avg_rate'] ?? 0, 2);
$active_count = $resStats['active_res'] ?? 0;
$active_pct = $raw_total_res > 0 ? round(($active_count / $raw_total_res) * 100) : 0;

$limit = 10;
$total_pages = max(1, ceil($raw_total_res / $limit));
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;

// Fetch current system rate
$system_rate = get_setting('current_rate', '33.70');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resident Directory – BayanTap</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css?v=4">
  <link rel="stylesheet" href="CSS/residents.css">
</head>

<body>

  <?php $current_page = 'residents'; include 'navbar.php'; ?>

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div class="page-title-section">
      <h1 class="page-title">Resident Directory</h1>
      <p class="page-subtitle">View and manage household accounts and water connections</p>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main">

    <!-- Summary Cards -->
    <div class="summary-grid">
      <div class="summary-card">
        <div class="summary-label">Total Registered</div>
        <div class="summary-value">
          <?= $total_res ?>
        </div>
        <div class="summary-sub">Households</div>
      </div>
    </div>

    <!-- Controls -->
    <form method="GET" class="controls-bar" id="filterForm">
      <div class="search-wrap">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8" />
          <path d="m21 21-4.35-4.35" />
        </svg>
        <input type="text" name="q" id="resSearch" value="<?= htmlspecialchars($search_q) ?>"
          placeholder="Search by House ID, name, or address…" oninput="applyLiveSearch()">
      </div>
      <div class="filter-group">
        <button type="button" class="filter-btn active" style="background: var(--blue); color: var(--white);"
          onclick="openAddResident()">+ Add Resident</button>
      </div>
    </form>

    <!-- Transaction Table -->
    <div class="table-card">
      <div class="table-card-header">
        <div class="table-card-header-title">
          <h2>Household Directory</h2>
          <p>Showing registered accounts and their current baseline rates</p>
        </div>
        <button class="export-btn">📥 Export</button>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Household ID</th>
              <th>Resident Name</th>
              <th>Address</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php
            require_once 'db_connect.php';

            $stmtRes = $pdo->prepare("SELECT * FROM residents WHERE $where_sql ORDER BY full_name ASC LIMIT " . (int) $limit . " OFFSET " . (int) $offset);
            $stmtRes->execute($params);
            while ($tx = $stmtRes->fetch()):
              $amount = "₱" . number_format($tx['monthly_rate'], 2);
              $statusClass = $tx['status'] === 'paid' ? 'badge-completed' : ($tx['status'] === 'pending' ? 'badge-pending' : 'badge-failed');
              ?>
              <tr data-id="<?= $tx['id'] ?>"
                  data-hh-id="<?= htmlspecialchars($tx['household_id'] ?? '') ?>"
                  data-token="<?= htmlspecialchars($tx['access_token'] ?? '') ?>"
                  data-full-name="<?= htmlspecialchars($tx['full_name'] ?? '') ?>"
                  data-block="<?= htmlspecialchars($tx['block_no'] ?? '') ?>"
                  data-lot="<?= htmlspecialchars($tx['lot_no'] ?? '') ?>"
                  data-contact="<?= htmlspecialchars($tx['contact_number'] ?? '') ?>"
                  data-email="<?= htmlspecialchars($tx['email'] ?? '') ?>"
                  data-rate="<?= htmlspecialchars($tx['monthly_rate'] ?? 0) ?>"
                  data-status="<?= htmlspecialchars($tx['status'] ?? 'unpaid') ?>">
                <td>
                  <div class="hh-id-pill">
                    <?= htmlspecialchars($tx['household_id'] ?? 'N/A') ?>
                  </div>
                </td>
                <td>
                  <div class="tx-date">
                    <?= htmlspecialchars($tx['full_name']) ?>
                  </div>
                </td>
                <td><span class="tx-ref">
                    <?= htmlspecialchars($tx['block_no'] . ' ' . $tx['lot_no']) ?>
                  </span></td>
                <td class="action-cell">
                  <button class="action-btn" title="Actions" onclick="toggleMenu(this)"
                    data-id="<?= $tx['id'] ?>">⋯</button>
                  <div class="action-menu" role="menu">
                    <button onclick="openPortal(this)"><span class="menu-icon">🌍</span> View Portal</button>
                    <button onclick="openViewHistory(this)"><span class="menu-icon">📜</span> View History</button>
                    <button onclick="openEditResident(this)"><span class="menu-icon">✏️</span> Edit Record</button>
                    <button class="danger" onclick="openDeleteConfirm(this)"><span class="menu-icon">🗑️</span>
                      Delete</button>
                  </div>
                </td>
              </tr>
              <?php
            endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination">
        <span class="pagination-info">Page
          <?= $page ?> of
          <?= $total_pages ?> (
          <?= number_format($raw_total_res) ?>
          total residents)
        </span>

        <?php if ($page > 1): ?>
          <a href="?page=<?= $page - 1 ?>" class="pagination-btn"
            style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">«</a>
          <?php
        else: ?>
          <button class="pagination-btn" disabled>«</button>
          <?php
        endif; ?>

        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);

        if ($start_page > 1) {
          echo '<a href="?page=1" class="pagination-btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">1</a>';
          if ($start_page > 2) {
            echo '<span style="color: var(--gray-400); margin: 0 4px;">...</span>';
          }
        }

        for ($i = $start_page; $i <= $end_page; $i++) {
          if ($i == $page) {
            echo '<button class="pagination-btn active">' . $i . '</button>';
          } else {
            echo '<a href="?page=' . $i . '" class="pagination-btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">' . $i . '</a>';
          }
        }

        if ($end_page < $total_pages) {
          if ($end_page < $total_pages - 1) {
            echo '<span style="color: var(--gray-400); margin: 0 4px;">...</span>';
          }
          echo '<a href="?page=' . $total_pages . '" class="pagination-btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">' . $total_pages . '</a>';
        }
        ?>

        <?php if ($page < $total_pages): ?>
          <a href="?page=<?= $page + 1 ?>" class="pagination-btn"
            style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">»</a>
          <?php
        else: ?>
          <button class="pagination-btn" disabled>»</button>
          <?php
        endif; ?>
      </div>
    </div>

    <div class="modal-backdrop" id="modalEditResident" role="dialog" aria-modal="true">
      <div class="modal">
        <div class="modal-header">
          <div>
            <h3>✏️ Edit Resident</h3>
            <div class="modal-sub">Update household information</div>
          </div>
          <button class="modal-close" onclick="closeModal('modalEditResident')">✕</button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="er-id">
          <div class="form-group">
            <label>Household ID</label>
            <input type="text" id="er-hh-id" readonly style="background:var(--gray-50); color:var(--gray-500);">
          </div>
          <div class="form-group">
            <label>Resident Name</label>
            <input type="text" id="er-name" placeholder="Full name">
          </div>
          <div class="form-row">
            <div class="form-group" style="flex: 1;">
              <label>Address (Block & Lot)</label>
              <input type="text" id="er-address" placeholder="e.g. Blk 9 Lot 2" readonly
                style="background:var(--gray-50);color:var(--gray-500);">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group" style="flex: 1;">
              <label>Contact Number</label>
              <input type="text" id="er-contact" placeholder="e.g. 0917...">
            </div>
            <div class="form-group" style="flex: 1;">
              <label>Email Address</label>
              <input type="email" id="er-email" placeholder="e.g. name@example.com">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="modal-btn modal-btn-cancel" onclick="closeModal('modalEditResident')">Cancel</button>
          <button class="modal-btn modal-btn-primary" onclick="confirmEditResident()">✔ Save Changes</button>
        </div>
      </div>
    </div>

    <!-- MODAL: View History -->
    <div class="modal-backdrop" id="modalViewHistory" role="dialog" aria-modal="true">
      <div class="modal" style="max-width:800px; width:95%;">
        <div class="modal-header">
          <div>
            <h3>📜 Household Archive</h3>
            <div class="modal-sub">Historical readings & payments for <strong id="vh-name" style="color:var(--blue);">—</strong> (<span id="vh-hhid">—</span>)</div>
          </div>
          <button class="modal-close" onclick="closeModal('modalViewHistory')">✕</button>
        </div>
        <div class="modal-body" style="padding:0;">
          <div style="padding: 16px; border-bottom: 1px solid var(--gray-100); background: var(--gray-50); display: flex; gap: 12px; align-items: center;">
            <div class="search-wrap" style="flex: 1; background: white; margin: 0;">
              <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.35-4.35" />
              </svg>
              <input type="text" id="vh-search" placeholder="Search history..." oninput="filterHistory()" style="background:transparent;">
            </div>
            <select id="vh-month-filter" class="filter-select" onchange="filterHistory()" style="width: auto; min-width: 140px; margin: 0; background: white; border: 1.5px solid var(--gray-200); font-weight: 600;">
              <option value="">All Periods</option>
              <!-- Dynamic Months -->
            </select>
          </div>
          <div class="table-wrap" style="max-height:60vh; overflow-y:auto; border:none; border-radius:0;">
            <table class="data-table">
              <thead style="position:sticky; top:0; z-index:10;">
                <tr>
                  <th id="vh-th-month" onclick="sortHistory('month')" style="cursor:pointer; user-select:none; background:var(--gray-50);">Month <span class="sort-icon">↕</span></th>
                  <th id="vh-th-usage" onclick="sortHistory('usage')" style="cursor:pointer; user-select:none; background:var(--gray-50);">Reading & Usage <span class="sort-icon">↕</span></th>
                  <th id="vh-th-amount" onclick="sortHistory('amount')" style="cursor:pointer; user-select:none; background:var(--gray-50);">Amount <span class="sort-icon">↕</span></th>
                  <th style="background:var(--gray-50);">Status</th>
                  <th style="background:var(--gray-50);">Paid Date</th>
                </tr>
              </thead>
              <tbody id="vh-table-body">
                <!-- Dynamic Content -->
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button class="modal-btn modal-btn-cancel" onclick="closeModal('modalViewHistory')">Close Archive</button>
          <button class="modal-btn modal-btn-primary" onclick="window.print()"><span style="margin-right:8px;">📥</span> Print Summary</button>
        </div>
      </div>
    </div>

    <div class="modal-backdrop" id="modalDeleteConfirm" role="dialog" aria-modal="true">
      <div class="modal">
        <div class="modal-header">
          <div>
            <h3>🗑️ Delete Record</h3>
            <div class="modal-sub">This action cannot be undone</div>
          </div>
          <button class="modal-close" onclick="closeModal('modalDeleteConfirm')">✕</button>
        </div>
        <div class="modal-body">
          <div class="confirm-icon danger"
            style="width: 62px; height: 62px; border-radius: 50%; background: var(--red-bg); display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
            🗑️</div>
          <div class="confirm-text" style="text-align: center;">
            <h4 style="margin-bottom: 8px;">Delete this record?</h4>
            <p style="font-size: .85rem; color: var(--gray-500);">You're about to permanently delete <strong
                id="dc-name">—</strong>. This will also delete all associated billing and transaction history.</p>
          </div>
        </div>
        <div class="modal-footer">
          <button class="modal-btn modal-btn-cancel" onclick="closeModal('modalDeleteConfirm')">Cancel</button>
          <button class="modal-btn modal-btn-danger" onclick="confirmDelete()">🗑️ Yes, Delete</button>
        </div>
      </div>
    </div>

  </div>

  <!-- ============================================================
     MODAL: Add Resident — 3 steps
     Step 1: "Make Sure" checklist
     Step 2: Fill in resident details
     Step 3: Success confirmation
============================================================ -->
  <div class="modal-backdrop" id="modalAddResident" role="dialog" aria-modal="true" aria-labelledby="addResidentTitle">
    <div class="modal" style="max-width:500px;">

      <!-- Step dots -->
      <div style="padding:18px 20px 0;">
        <div class="step-indicator">
          <div class="step-dot active" id="sdot-1"></div>
          <div class="step-dot" id="sdot-2"></div>
          <div class="step-dot" id="sdot-3"></div>
        </div>
      </div>

      <!-- ── STEP 1: Make Sure Checklist ── -->
      <div id="ar-step1">
        <div class="modal-header" style="padding-top:6px;">
          <div>
            <h3 id="addResidentTitle">✅ Before You Add a Resident</h3>
            <div class="modal-sub">Make sure you have all the required information ready</div>
          </div>
          <button class="modal-close" onclick="closeModal('modalAddResident')" aria-label="Close">✕</button>
        </div>

        <div class="modal-body">
          <!-- Warning banner -->
          <div class="warning-banner">
            <span class="wb-icon">⚠️</span>
            <span><strong>Make sure</strong> all items below are confirmed before proceeding. Incomplete information may
              cause billing errors for this household.</span>
          </div>

          <!-- Checklist meta -->
          <div class="checklist-meta">
            <span class="checklist-label">Tick each item to confirm</span>
            <span class="checklist-progress" id="ar-progress">0 / 5 checked</span>
          </div>

          <ul class="checklist" id="ar-checklist">
            <li onclick="toggleCheck(this)" data-idx="0">
              <span class="check-box" id="cb-0"></span>
              <span class="check-text">
                <strong>🏠 Household address is verified</strong>
                Block and lot number matches the barangay records
              </span>
            </li>
            <li onclick="toggleCheck(this)" data-idx="1">
              <span class="check-box" id="cb-1"></span>
              <span class="check-text">
                <strong>👤 Resident identity is confirmed</strong>
                Full name matches a valid government-issued ID
              </span>
            </li>
            <li onclick="toggleCheck(this)" data-idx="2">
              <span class="check-box" id="cb-2"></span>
              <span class="check-text">
                <strong>💧 Water meter has been installed</strong>
                Meter serial number and initial reading are recorded
              </span>
            </li>
            <li onclick="toggleCheck(this)" data-idx="3">
              <span class="check-box" id="cb-3"></span>
              <span class="check-text">
                <strong>📋 No duplicate household record exists</strong>
                Searched the current roster and confirmed this is a new entry
              </span>
            </li>
            <li onclick="toggleCheck(this)" data-idx="4">
              <span class="check-box" id="cb-4"></span>
              <span class="check-text">
                <strong>📞 Contact information is available</strong>
                At least one valid contact number is on hand for notifications
              </span>
            </li>
          </ul>
        </div>

        <div class="modal-footer">
          <button class="modal-btn modal-btn-cancel" onclick="closeModal('modalAddResident')">Cancel</button>
          <button class="modal-btn modal-btn-primary" id="ar-next1-btn" onclick="arGoStep2()" disabled
            style="opacity:.5;cursor:not-allowed;">
            Continue →
          </button>
        </div>
      </div><!-- /step1 -->

      <!-- ── STEP 2: Resident Details Form ── -->
      <div id="ar-step2" style="display:none;">
        <div class="modal-header" style="padding-top:6px;">
          <div>
            <h3>🏠 New Resident Details</h3>
            <div class="modal-sub">Fill in all required fields</div>
          </div>
          <button class="modal-close" onclick="closeModal('modalAddResident')" aria-label="Close">✕</button>
        </div>

        <div class="modal-body">
          <!-- Household -->
          <div class="form-row">
             <div class="form-group">
               <label>Block No. <span style="color:var(--red);">*</span></label>
               <input type="text" id="ar-block" value="Blk " placeholder="e.g. Blk 9" autocomplete="off">
             </div>
             <div class="form-group">
               <label>Lot No. <span style="color:var(--red);">*</span></label>
               <input type="text" id="ar-lot" value="Lot " placeholder="e.g. Lot 2" autocomplete="off">
             </div>
          </div>

          <!-- Resident name -->
          <div class="form-group">
            <label>Full Name <span style="color:var(--red);">*</span></label>
            <input type="text" id="ar-name" placeholder="e.g. Maria Santos" autocomplete="off">
          </div>

          <!-- Meter readings -->
          <div class="form-row">
            <div class="form-group">
              <label>Meter Starting Point (m³)</label>
              <input type="number" id="ar-meter" placeholder="e.g. 1245 (Leave blank for 0)" min="0">
            </div>
            <div class="form-group">
              <label>Monthly Rate (₱) <span style="color:var(--red);">*</span></label>
              <input type="number" id="ar-rate" placeholder="e.g. <?= htmlspecialchars($system_rate) ?>" min="0" step="0.01" value="<?= htmlspecialchars($system_rate) ?>" readonly style="background: var(--gray-50); cursor: not-allowed; color: var(--gray-500); border-color: var(--gray-200);">
            </div>
          </div>

          <!-- Contact & Email -->
          <div class="form-row">
            <div class="form-group">
              <label>Contact Number</label>
              <input type="tel" id="ar-contact" placeholder="e.g. 09XX-XXX-XXXX" autocomplete="off">
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" id="ar-email" placeholder="e.g. name@example.com" autocomplete="off">
            </div>
          </div>

          <!-- Inline error -->
          <div id="ar-error"
            style="display:none;background:var(--red-bg);color:var(--red);border:1px solid #fca5a5;border-radius:var(--radius-sm);padding:9px 12px;font-size:.8rem;font-weight:600;margin-top:4px;">
            ⚠️ Please fill in all required fields marked with *.
          </div>
        </div>

        <div class="modal-footer">
          <button class="modal-btn modal-btn-cancel" onclick="arGoStep1()">← Back</button>
          <button class="modal-btn modal-btn-primary" onclick="arGoStep3()">Add Resident ✓</button>
        </div>
      </div><!-- /step2 -->

      <!-- ── STEP 3: Success ── -->
      <div id="ar-step3" style="display:none;">
        <div class="modal-header" style="padding-top:6px;">
          <div>
            <h3>Resident Added!</h3>
            <div class="modal-sub">Record created successfully</div>
          </div>
          <button class="modal-close" onclick="closeModal('modalAddResident')" aria-label="Close">✕</button>
        </div>

        <div class="modal-body">
          <div class="add-success">
            <div class="success-ring">🏠</div>
            <h4>Resident successfully added</h4>
            <p>The new household record has been created and added to the directory.</p>
            <div class="new-row-badge">✅ <span id="ar-success-label">—</span></div>
          </div>
        </div>

        <div class="modal-footer" style="justify-content:center;">
          <button class="modal-btn modal-btn-cancel"
            onclick="closeModal('modalAddResident'); location.reload();">Close</button>
          <button class="modal-btn modal-btn-primary" onclick="arAddAnother()">＋ Add Another</button>
        </div>
      </div><!-- /step3 -->

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
       MODAL HELPERS
    ============================================================ */
    function openModal(id) { document.getElementById(id).classList.add('is-open'); document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).classList.remove('is-open'); document.body.style.overflow = ''; }
    function closeAllModals() {
      document.querySelectorAll('.modal-backdrop.is-open').forEach(m => m.classList.remove('is-open'));
      document.body.style.overflow = '';
    }
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
      backdrop.addEventListener('click', function (e) {
        if (e.target === this) closeModal(this.id);
      });
    });
    document.addEventListener('keydown', e => { 
      if (e.key === 'Escape') { closeAllDropdowns(); closeDrawer(); closeAllModals(); closeAllMenus(); } 
    });

    /* ============================================================
       ADD RESIDENT — 3-step modal logic
    ============================================================ */
    const AR_TOTAL = 5;
    let arChecked = new Set();

    function openAddResident() {
      arChecked.clear();
      document.querySelectorAll('#ar-checklist li').forEach(li => {
        li.classList.remove('checked');
        li.querySelector('.check-box').textContent = '';
      });
      updateArProgress();
      arShowStep(1);
      openModal('modalAddResident');
    }

    function toggleCheck(li) {
      const idx = li.dataset.idx;
      if (arChecked.has(idx)) {
        arChecked.delete(idx);
        li.classList.remove('checked');
        li.querySelector('.check-box').textContent = '';
      } else {
        arChecked.add(idx);
        li.classList.add('checked');
        li.querySelector('.check-box').textContent = '✓';
      }
      updateArProgress();
    }

    function updateArProgress() {
      const n = arChecked.size;
      const btn = document.getElementById('ar-next1-btn');
      document.getElementById('ar-progress').textContent = n + ' / ' + AR_TOTAL + ' checked';
      if (n === AR_TOTAL) {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
      } else {
        btn.disabled = true;
        btn.style.opacity = '.5';
        btn.style.cursor = 'not-allowed';
      }
    }

    function arShowStep(n) {
      [1, 2, 3].forEach(i => {
        document.getElementById('ar-step' + i).style.display = (i === n) ? 'block' : 'none';
        const dot = document.getElementById('sdot-' + i);
        dot.className = 'step-dot' + (i < n ? ' done' : i === n ? ' active' : '');
      });
    }

    function arGoStep1() { arShowStep(1); }

    function arGoStep2() {
      // Clear fields except for pre-filled labels
      document.getElementById('ar-name').value = '';
      document.getElementById('ar-meter').value = '';
      document.getElementById('ar-contact').value = '';
      document.getElementById('ar-email').value = '';
      
      document.getElementById('ar-block').value = 'Blk ';
      document.getElementById('ar-lot').value = 'Lot ';

      document.getElementById('ar-error').style.display = 'none';
      arShowStep(2);
    }

    function arGoStep3() {
      const block = document.getElementById('ar-block').value.trim();
      const lot = document.getElementById('ar-lot').value.trim();
      const name = document.getElementById('ar-name').value.trim();
      const meter = document.getElementById('ar-meter').value.trim();
      const rate = document.getElementById('ar-rate').value.trim();
      const contact = document.getElementById('ar-contact').value.trim();
      const email = document.getElementById('ar-email').value.trim();
      if (!block || !lot || !name || !rate) {
        document.getElementById('ar-error').style.display = 'block';
        document.getElementById('ar-error').textContent = '⚠️ Please fill in all required fields marked with *.';
        return;
      }
      document.getElementById('ar-error').style.display = 'none';

      const initialMeter = meter === '' ? 0 : parseInt(meter);

      const hh = block + ' ' + lot;

      // Save to database via API
      fetch('api_add_resident.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          block_no: block,
          lot_no: lot,
          full_name: name,
          initial_meter: initialMeter,
          monthly_rate: parseFloat(rate),
          contact_number: contact,
          email: email,
          status: 'paid'
        })
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            document.getElementById('ar-success-label').textContent = hh + ' · ' + name;
            arShowStep(3);
          } else {
            document.getElementById('ar-error').textContent = '⚠️ ' + (data.error || 'Failed to save resident.');
            document.getElementById('ar-error').style.display = 'block';
          }
        })
        .catch(() => {
          document.getElementById('ar-error').textContent = '⚠️ Network error. Please try again.';
          document.getElementById('ar-error').style.display = 'block';
        });
    }

    function arAddAnother() {
      arChecked.clear();
      document.querySelectorAll('#ar-checklist li').forEach(li => {
        li.classList.remove('checked');
        li.querySelector('.check-box').textContent = '';
      });
      updateArProgress();
      arShowStep(1);
    }

    /* ============================================================
   ACTION CONTEXT MENU
============================================================ */
    function toggleMenu(btn) {
      const menu = btn.nextElementSibling;
      const isOpen = menu.classList.contains('is-open');
      closeAllMenus();
      if (!isOpen) {
        const rect = btn.getBoundingClientRect();
        if (window.innerHeight - rect.bottom < 120) menu.classList.add('drop-up');
        else menu.classList.remove('drop-up');

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
       ROW DATA EXTRACTOR
    ============================================================ */
    let _activeRow = null;
    let _activeId = null;

    function getRowData(actionBtn) {
      const row = actionBtn.closest('tr');
      _activeRow = row;
      _activeId = row.getAttribute('data-id');

      return {
        id: _activeId,
        hh_id: row.getAttribute('data-hh-id'),
        token: row.getAttribute('data-token'),
        name: row.getAttribute('data-full-name'),
        block: row.getAttribute('data-block'),
        lot: row.getAttribute('data-lot'),
        address: row.getAttribute('data-block') + ' ' + row.getAttribute('data-lot'),
        contact: row.getAttribute('data-contact'),
        email: row.getAttribute('data-email'),
        rate: row.getAttribute('data-rate'),
        status: row.getAttribute('data-status')
      };
    }

    function openPortal(btn) {
      const d = getRowData(btn);
      if (d.token) {
        window.open('portal.php?token=' + d.token, '_blank');
      } else {
        alert('This resident has no access token yet.');
      }
      closeAllMenus();
    }

    /* ============================================================
       EDIT RESIDENT
    ============================================================ */
    function openEditResident(btn) {
      closeAllMenus();
      const d = getRowData(btn);

      document.getElementById('er-id').value = _activeId;
      document.getElementById('er-hh-id').value = d.hh_id || 'N/A';
      document.getElementById('er-name').value = d.name;
      document.getElementById('er-address').value = d.address;
      document.getElementById('er-contact').value = d.contact;
      document.getElementById('er-email').value = d.email || '';

      openModal('modalEditResident');
    }

    function confirmEditResident() {
      const btn = document.querySelector('#modalEditResident .modal-btn-primary');
      const originalText = btn.textContent;
      btn.disabled = true; btn.textContent = 'Saving...';

      const id = document.getElementById('er-id').value;
      const name = document.getElementById('er-name').value;
      const address = document.getElementById('er-address').value;
      const contact = document.getElementById('er-contact').value;
      const email = document.getElementById('er-email').value;

      // Extract block/lot from address if space-separated
      const parts = address.split(' ').map(s => s.trim());
      const block = parts[0] || '';
      const lot = parts.slice(1).join(' ') || '';

      const payload = {
        id,
        full_name: name,
        block_no: block,
        lot_no: lot,
        contact_number: contact,
        email: email
      };

      fetch('api_edit_resident.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          // Update local UI
          if (_activeRow) {
            _activeRow.querySelector('.tx-date').textContent = name;
            _activeRow.querySelector('.tx-ref').textContent = address;
            
            // Update data attributes for next edit
            _activeRow.setAttribute('data-full-name', name);
            _activeRow.setAttribute('data-block', block);
            _activeRow.setAttribute('data-lot', lot);
            _activeRow.setAttribute('data-contact', contact);
            _activeRow.setAttribute('data-email', email);

            // Visual flash
            _activeRow.style.transition = 'background .15s';
            _activeRow.style.background = 'var(--blue-light)';
            setTimeout(() => { _activeRow.style.background = ''; }, 900);
          }
          closeModal('modalEditResident');
        } else {
          alert('Error: ' + (data.error || 'Failed to save changes.'));
        }
      })
      .catch(() => alert('Network error. Failed to save.'))
      .finally(() => {
        btn.disabled = false; btn.textContent = originalText;
      });
    }

    /* ============================================================
       DELETE RESIDENT
    ============================================================ */
    function openDeleteConfirm(btn) {
      closeAllMenus();
      const d = getRowData(btn);
      document.getElementById('dc-name').textContent = d.name;
      openModal('modalDeleteConfirm');
    }

    function confirmDelete() {
      if (!_activeId || !_activeRow) return;

      fetch('api_delete_record.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ resident_id: _activeId })
      })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            _activeRow.style.transition = 'opacity .3s, transform .3s';
            _activeRow.style.opacity = '0';
            _activeRow.style.transform = 'translateX(20px)';
            setTimeout(() => { _activeRow.remove(); _activeRow = null; _activeId = null; }, 310);
          } else {
            alert('Failed to delete: ' + data.error);
          }
        });

      closeModal('modalDeleteConfirm');
    }

    /* ============================================================
       HOUSEHOLD HISTORY ARCHIVE
    ============================================================ */
    let _historyData = [];

    function openViewHistory(btn) {
      closeAllMenus();
      document.getElementById('vh-search').value = ''; // Reset search
      const d = getRowData(btn);
      document.getElementById('vh-name').textContent = d.name;
      document.getElementById('vh-hhid').textContent = d.hh_id;
      
      const tbody = document.getElementById('vh-table-body');
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--gray-400);">⌛ Loading history...</td></tr>';
      
      openModal('modalViewHistory');

      fetch('api_get_history.php?resident_id=' + d.id)
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            _historyData = data.history;
            
            // Populate Month Filter
            const monthFilter = document.getElementById('vh-month-filter');
            const uniqueMonths = [...new Set(_historyData.map(r => r.billing_month))];
            monthFilter.innerHTML = '<option value="">All Periods</option>' + 
              uniqueMonths.map(m => `<option value="${m}">${m}</option>`).join('');

            renderHistoryTable(_historyData);
          } else {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--red);">❌ ' + data.error + '</td></tr>';
          }
        })
        .catch(() => {
          tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--red);">❌ Failed to load. Check connection.</td></tr>';
        });
    }

    function renderHistoryTable(data) {
      const tbody = document.getElementById('vh-table-body');
      if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--gray-400);">No history records found for this household.</td></tr>';
        return;
      }

      tbody.innerHTML = data.map(row => {
        const usage = row.usage_m3 + ' m³';
        const range = row.previous_reading + ' → ' + row.current_reading;
        const amount = '₱' + parseFloat(row.amount_due).toLocaleString(undefined, {minimumFractionDigits: 2});
        const statusClass = row.status === 'paid' ? 'badge-completed' : (row.status === 'pending' ? 'badge-pending' : 'badge-failed');
        const statusText = row.status.toUpperCase();
        
        return `
          <tr>
            <td style="font-weight:700;color:var(--gray-900);">${row.billing_month}</td>
            <td>
              <div style="font-weight:600;color:var(--blue);">${usage}</div>
              <div style="font-size:0.75rem;color:var(--gray-400);">${range}</div>
            </td>
            <td style="font-weight:700;">${amount}</td>
            <td><span class="badge ${statusClass}">${statusText}</span></td>
            <td style="font-size:0.8rem;color:var(--gray-500);">${row.paid_date ? new Date(row.paid_date).toLocaleDateString() : '—'}</td>
          </tr>
        `;
      }).join('');
    }

    function sortHistory(criteria) {
      const order = (event.currentTarget.getAttribute('data-order') === 'desc') ? 'asc' : 'desc';
      
      // Reset all headers
      ['month', 'usage', 'amount'].forEach(c => {
        const th = document.getElementById('vh-th-' + c);
        th.classList.remove('sort-active');
        th.setAttribute('data-order', '');
        th.querySelector('.sort-icon').textContent = '↕';
      });

      // Update active header
      const activeTh = event.currentTarget;
      activeTh.classList.add('sort-active');
      activeTh.setAttribute('data-order', order);
      activeTh.querySelector('.sort-icon').textContent = order === 'asc' ? '↑' : '↓';

      _historyData.sort((a, b) => {
        let valA, valB;
        if (criteria === 'month') {
          valA = new Date('01 ' + a.billing_month).getTime();
          valB = new Date('01 ' + b.billing_month).getTime();
        } else if (criteria === 'usage') {
          valA = parseFloat(a.usage_m3);
          valB = parseFloat(b.usage_m3);
        } else if (criteria === 'amount') {
          valA = parseFloat(a.amount_due);
          valB = parseFloat(b.amount_due);
        }
        
        return order === 'asc' ? (valA - valB) : (valB - valA);
      });

      renderHistoryTable(_historyData);
    }

    function filterHistory() {
      const query = document.getElementById('vh-search').value.toLowerCase().trim();
      const monthSelect = document.getElementById('vh-month-filter').value;

      const filtered = _historyData.filter(row => {
        const matchesSearch = !query || (
          row.billing_month.toLowerCase().includes(query) || 
          row.status.toLowerCase().includes(query) ||
          row.usage_m3.toString().includes(query) ||
          row.amount_due.toString().includes(query)
        );
        
        const matchesMonth = !monthSelect || row.billing_month === monthSelect;
        
        return matchesSearch && matchesMonth;
      });
      renderHistoryTable(filtered);
    }

    /* ============================================================
       LIVE SEARCH FILTER
    ============================================================ */
    function applyLiveSearch() {
      const query = document.getElementById('resSearch').value.toLowerCase().trim();
      const rows = document.querySelectorAll('tbody tr');
      
      rows.forEach(row => {
        const hhId = row.getAttribute('data-hh-id').toLowerCase();
        const name = row.getAttribute('data-full-name').toLowerCase();
        const block = row.getAttribute('data-block').toLowerCase();
        const lot = row.getAttribute('data-lot').toLowerCase();
        
        const matches = hhId.includes(query) || 
                        name.includes(query) || 
                        block.includes(query) || 
                        lot.includes(query);
                        
        row.style.display = matches ? '' : 'none';
      });
    }
  </script>
</body>

</html>