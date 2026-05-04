<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

// Fetch role if not already in session
if (!isset($_SESSION['role'])) {
    $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $roleStmt->execute([$_SESSION['user_id']]);
    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
    $_SESSION['role'] = $roleRow['role'] ?? 'treasurer';
}

$is_admin = $_SESSION['role'] === 'admin';

$search_q = $_GET['q'] ?? '';
$search_month = $_GET['month'] ?? '';
$search_type = $_GET['type'] ?? '';
$search_status = $_GET['status'] ?? '';

// Fetch available months dynamically from transactions
$mStmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(payment_date, '%b %Y') AS m FROM transactions ORDER BY payment_date ASC");
$available_months = $mStmt->fetchAll(PDO::FETCH_COLUMN);

// Build WHERE clauses safely
$where_clauses = ["1=1"];
$params = [];

if ($search_q !== '') {
    $where_clauses[] = "(t.receipt_no LIKE ? OR r.full_name LIKE ? OR r.block_no LIKE ? OR r.household_id LIKE ?)";
    $params[] = "%$search_q%";
    $params[] = "%$search_q%";
    $params[] = "%$search_q%";
    $params[] = "%$search_q%";
}

if ($search_month !== '') {
    $where_clauses[] = "DATE(t.payment_date) = ?";
    $params[] = $search_month;
}

if ($search_type !== '') {
    // Current database doesn't have a specific type, so we just dummy filter if 'payment' is selected
    // or expand this if you have a `type` column in `transactions`
}

if ($search_status !== '') {
    if ($search_status === 'completed') {
        $where_clauses[] = "(SELECT status FROM billings WHERE receipt_no = t.receipt_no LIMIT 1) = 'paid'";
    } elseif ($search_status === 'partial') {
        $where_clauses[] = "(SELECT status FROM billings WHERE receipt_no = t.receipt_no LIMIT 1) = 'partial'";
    }
}

$where_sql = implode(' AND ', $where_clauses);

$txStatsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_tx,
        SUM(amount_paid) AS total_collected,
        AVG(amount_paid) AS avg_tx
    FROM transactions t
    JOIN residents r ON t.resident_id = r.id
    WHERE $where_sql
");
$txStatsStmt->execute($params);
$txStats = $txStatsStmt->fetch(PDO::FETCH_ASSOC);
$total_tx = number_format($txStats['total_tx'] ?? 0);
$total_collected = "₱" . number_format($txStats['total_collected'] ?? 0, 2);
$avg_tx = "₱" . number_format($txStats['avg_tx'] ?? 0, 2);

// Calculate real success rate (Collection Rate)
// EXCLUDE FUTURE MONTHS from the total, so we only count cycles up to the current date
$current_month_str = date('M Y');

if ($search_month !== '') {
    $billing_month = date('M Y', strtotime($search_month));
    $collStmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid
        FROM billings WHERE billing_month = ?");
    $collStmt->execute([$billing_month]);
} else {
    $collStmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid
        FROM billings
        WHERE STR_TO_DATE(CONCAT('01 ', billing_month), '%d %b %Y') <= STR_TO_DATE(CONCAT('01 ', ?), '%d %b %Y')");
    $collStmt->execute([$current_month_str]);
}
$collData = $collStmt->fetch(PDO::FETCH_ASSOC);
$total_bills = (int)($collData['total'] ?? 0);
$paid_bills = (int)($collData['paid'] ?? 0);
$success_rate = ($total_bills > 0) ? round(($paid_bills / $total_bills) * 100) : 0;

$raw_total_tx = $txStats['total_tx'] ?? 0;
$limit = 10;
$total_pages = max(1, ceil($raw_total_tx / $limit));
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $limit;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History – BayanTap</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="CSS/dashboard.css?v=4">
    <link rel="stylesheet" href="CSS/transaction.css">
</head>

<body>

  <?php $current_page = 'transactions'; include 'navbar.php'; ?>

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="page-title-section">
            <h1 class="page-title">Transaction History</h1>
            <p class="page-subtitle">View all payments, collections, and account adjustments</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main">

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Total Transactions</div>
                <div class="summary-value"><?= $total_tx ?></div>
                <div class="summary-sub"><?= $search_month ?: 'Overall' ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Total Collected</div>
                <div class="summary-value"><?= $total_collected ?></div>
                <div class="summary-sub"><?= $search_month ?: 'All time' ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Avg. Transaction</div>
                <div class="summary-value"><?= $avg_tx ?></div>
                <div class="summary-sub">Across cycles</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Success Rate</div>
                <div class="summary-value"><?= $success_rate ?>%</div>
                <div class="summary-sub">Compared to bills</div>
            </div>
        </div>

        <!-- Controls -->
        <form method="GET" class="controls-bar" id="filterForm">
            <div class="search-wrap">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                </svg>
                <input type="text" name="q" id="txSearch" value="<?= htmlspecialchars($search_q) ?>"
                    placeholder="Search by ID, name, or receipt…" onchange="this.form.submit()">
            </div>
            <div class="filter-group" style="display:flex; gap:8px; align-items:center; justify-content:space-between;">
                <div style="display:flex; gap:8px;">
                <div class="filter-wrap">
                    <input type="date" name="month" class="filter-select" value="<?= htmlspecialchars($search_month) ?>" onchange="this.form.submit()">
                </div>
                <div class="filter-wrap">
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="" <?= $search_status === '' ? 'selected' : '' ?>>All Status</option>
                        <option value="completed" <?= $search_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="partial" <?= $search_status === 'partial' ? 'selected' : '' ?>>Partial Payment</option>
                    </select>
                </div>
                </div>
                <button type="button" class="export-btn" onclick="event.preventDefault(); openModal('modalAddTransaction')" style="background:var(--blue); color:var(--white); border-color:var(--blue);"> Add Transaction</button>
            </div>
        </form>

        <!-- Transaction Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-header-title">
                    <h2>All Transactions</h2>
                    <p>Showing all payments and account adjustments</p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        require_once 'db_connect.php';

                        $stmtTx = $pdo->prepare("SELECT t.*, r.block_no, r.lot_no, r.full_name, r.household_id FROM transactions t JOIN residents r ON t.resident_id = r.id WHERE $where_sql ORDER BY t.payment_date DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset);
                        $stmtTx->execute($params);
                        while ($tx = $stmtTx->fetch()):
                            $dateText = date("M j, Y", strtotime($tx['payment_date']));
                            $timeText = date("g:i A", strtotime($tx['payment_date']));
                            $amount = "₱" . number_format($tx['amount_paid'], 2);
                            $displayDate = $dateText . ' ' . $timeText;
                            ?>
                            <tr
                              data-id="<?= htmlspecialchars($tx['id']) ?>"
                              data-receipt-no="<?= htmlspecialchars($tx['receipt_no']) ?>"
                              data-amount="<?= htmlspecialchars($tx['amount_paid']) ?>"
                              data-proof="<?= htmlspecialchars($tx['payment_proof'] ?? '') ?>"
                              data-name="<?= htmlspecialchars($tx['full_name']) ?>"
                              data-date="<?= htmlspecialchars($displayDate) ?>">
                                <td>
                                    <div class="tx-date"><?= htmlspecialchars($dateText) ?></div>
                                    <div class="tx-date-sub"><?= htmlspecialchars($timeText) ?></div>
                                </td>
                                <td><span class="tx-ref"><?= htmlspecialchars($tx['receipt_no']) ?></span></td>
                                <td>
                                    <div class="tx-description"><?= htmlspecialchars($tx['full_name']) ?></div>
                                    <div class="tx-description-sub">
                                        <span style="color:var(--blue); font-weight:700;"><?= htmlspecialchars($tx['household_id'] ?? 'N/A') ?></span> · 
                                        <?= htmlspecialchars($tx['block_no'] . ' ' . $tx['lot_no']) ?></div>
                                </td>
                                <td><span class="tx-amount positive">+<?= htmlspecialchars($amount) ?></span></td>
                                <?php
                                // Get billing status to determine this transaction row's badge state
                                $statusCheckStmt = $pdo->prepare("SELECT status FROM billings WHERE receipt_no = ? LIMIT 1");
                                $statusCheckStmt->execute([$tx['receipt_no']]);
                                $statusRow = $statusCheckStmt->fetch(PDO::FETCH_ASSOC);
                                $billing_status = $statusRow['status'] ?? 'paid';
                                $status_label = strtoupper($billing_status);
                                $status_class = 'badge-pending';

                                if ($billing_status === 'paid') {
                                    $status_label = 'COMPLETED';
                                    $status_class = 'badge-completed';
                                } elseif ($billing_status === 'partial') {
                                    $status_label = 'PARTIAL PAYMENT';
                                    $status_class = 'badge-partial';
                                } elseif ($billing_status === 'started') {
                                    $status_label = 'STARTED';
                                    $status_class = 'badge-pending';
                                }
                                ?>
                                <td><span class="tx-status-badge <?= $status_class ?>"><?= $status_label ?></span></td>
                                <input type="hidden" class="billing-status" value="<?= htmlspecialchars($billing_status) ?>">
                                <td class="action-cell">
                                    <button class="action-btn" title="Actions" onclick="toggleMenu(this)">⋯</button>
                                    <div class="action-menu" role="menu">
                                        <?php if ($is_admin): ?>
                                            <button onclick="openEditTx(this)"><span class="menu-icon">✏️</span> Edit</button>
                                        <?php else: ?>
                                            <button disabled style="opacity:0.4; cursor:not-allowed;"><span class="menu-icon">✏️</span> Edit</button>
                                        <?php endif; ?>
                                        <?php if ($tx['payment_proof']): ?>
                                            <button onclick="viewProofImage(this)" data-proof-file="<?= htmlspecialchars($tx['payment_proof']) ?>"><span class="menu-icon">👁️</span> View Proof</button>
                                        <?php endif; ?>
                                    </div>                         
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <span class="pagination-info">Page <?= $page ?> of <?= $total_pages ?>
                    (<?= number_format($raw_total_tx) ?> total transactions)</span>

                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="pagination-btn"
                        style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">«</a>
                <?php else: ?>
                    <button class="pagination-btn" disabled>«</button>
                <?php endif; ?>

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
                <?php else: ?>
                    <button class="pagination-btn" disabled>»</button>
                <?php endif; ?>
            </div>
        </div>

    </div>

  <!-- ============================================================
     MODAL: Add Transaction
  ============================================================ -->
  <div class="modal-backdrop" id="modalAddTransaction" role="dialog" aria-modal="true" aria-labelledby="addTxTitle">
    <div class="modal" style="max-width:480px;">
      <div class="modal-header">
        <div>
          <h3 id="addTxTitle">💳 Add Transaction</h3>
          <div class="modal-sub">Record a new payment</div>
        </div>
        <button class="modal-close" onclick="closeModal('modalAddTransaction')" aria-label="Close">✕</button>
      </div>
      <div class="modal-body">
        <!-- Receipt Number Lookup -->
        <div class="form-group">
          <label>Receipt Number</label>
          <div style="display:flex; gap:8px;">
            <input type="text" id="tx-receipt-no" placeholder="e.g. MV-2026-0001" style="flex:1;">
            <button type="button" onclick="lookupReceipt()" style="padding:10px 16px; border-radius:8px; background:var(--blue); color:var(--white); border:none; font-family:inherit; font-weight:600; font-size:0.85rem; cursor:pointer; white-space:nowrap;">🔍 Lookup</button>
          </div>
          <div id="tx-lookup-status" style="font-size:0.75rem; margin-top:6px; color:var(--gray-400);">Enter a receipt number and click Lookup</div>
        </div>

        <!-- Auto-filled Info -->
        <div id="tx-autofill-section">
          <div style="background:var(--gray-50); border:1.5px solid var(--gray-200); border-radius:10px; padding:14px; margin:16px 0 12px;">
            <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:var(--gray-400); margin-bottom:10px;">📋 Billing Details (Auto-filled)</div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:0.85rem;">
              <div>
                <span style="color:var(--gray-500); font-size:0.75rem;">Household</span>
                <div style="font-weight:700; color:var(--gray-900);" id="tx-hh">—</div>
              </div>
              <div>
                <span style="color:var(--gray-500); font-size:0.75rem;">Resident</span>
                <div style="font-weight:700; color:var(--gray-900);" id="tx-name">—</div>
              </div>
              <div>
                <span style="color:var(--gray-500); font-size:0.75rem;">Billing Period</span>
                <div style="font-weight:600; color:var(--gray-700);" id="tx-period">—</div>
              </div>
              <div>
                <span style="color:var(--gray-500); font-size:0.75rem;">Usage</span>
                <div style="font-weight:600; color:var(--gray-700);" id="tx-usage">—</div>
              </div>
              <div>
                <span style="color:var(--gray-500); font-size:0.75rem;">Rate</span>
                <div style="font-weight:600; color:var(--gray-700);" id="tx-rate">—</div>
              </div>
            </div>
            <div style="margin-top:12px; padding-top:10px; border-top:1.5px dashed var(--gray-200); display:flex; justify-content:space-between; align-items:center;">
              <span style="font-size:0.85rem; font-weight:600; color:var(--gray-700);">Amount Due</span>
              <span style="font-size:1.15rem; font-weight:800; color:var(--blue);" id="tx-amount-due">—</span>
            </div>
          </div>

          <!-- Amount Paid & Change -->
          <div class="form-row" style="margin-top:12px;">
            <div class="form-group" style="flex:1;">
              <label id="tx-amount-label">Amount Paid (₱)</label>
              <input type="number" id="tx-amount-paid" placeholder="e.g. 775.10" min="0" step="0.01" style="font-size:1.1rem; font-weight:700;" oninput="calculateChange()">
              <div id="tx-payment-info" style="font-size:0.75rem; color:var(--gray-400); margin-top:4px;"></div>
            </div>
            <div class="form-group" style="flex:1;">
              <label>Change (₱)</label>
              <input type="text" id="tx-change" placeholder="0.00" readonly style="font-size:1.1rem; font-weight:700; background:var(--gray-50); color:var(--gray-500);">
            </div>
          </div>

          <!-- Proof of Payment -->
          <div class="form-group" style="margin-top:8px;">
            <label>Proof of Payment (Optional)</label>
            <div id="tx-proof-preview" style="width:100%; height:120px; background:var(--gray-50); border:1.5px dashed var(--gray-200); border-radius:8px; display:flex; flex-direction:column; align-items:center; justify-content:center; overflow:hidden; position:relative; cursor:pointer; transition:all 0.2s;"
              onclick="document.getElementById('tx-proof-input').click()">
              <span style="font-size:1.2rem; margin-bottom:2px;">📸</span>
              <span style="font-size:0.7rem; font-weight:700; color:var(--gray-400); text-transform:uppercase;">Click to Upload Proof</span>
            </div>
            <input type="file" id="tx-proof-input" style="display:none;" accept="image/*" onchange="handleTxProofPreview(this)">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-cancel" onclick="closeAddTxModal()">Cancel</button>
        <button class="modal-btn modal-btn-primary" id="btnRecordTx" onclick="submitTransaction()" disabled style="opacity:0.5;">💳 Record Payment</button>
      </div>
    </div>
  </div>

  <!-- ============================================================
     MODAL: View Proof
  ============================================================ -->
  <div class="modal-backdrop" id="modalViewProof" role="dialog" aria-modal="true" aria-labelledby="viewProofTitle">
    <div class="modal" style="max-width: 600px;">
      <div class="modal-header">
        <div>
          <h3 id="viewProofTitle">👁️ Payment Proof</h3>
          <div class="modal-sub">Uploaded proof of payment</div>
        </div>
        <button class="modal-close" onclick="closeModal('modalViewProof')" aria-label="Close">✕</button>
      </div>
      <div class="modal-body" style="padding: 24px; display: flex; justify-content: center; align-items: center;">
        <img id="proof-img-display" src="" alt="Payment Proof" style="max-width: 100%; max-height: 500px; border-radius: 8px; object-fit: contain;">
      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-primary" onclick="downloadProof()" style="background:var(--blue); color:var(--white);">⬇️ Download</button>
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('modalViewProof')">Close</button>
      </div>
    </div>
  </div>

  <!-- ============================================================
     MODAL: Edit Transaction
  ============================================================ -->
  <div class="modal-backdrop" id="modalEditTransaction" role="dialog" aria-modal="true">
    <div class="modal" style="max-width: 450px;">
      <div class="modal-header">
        <div>
          <h3>✏️ Edit Transaction</h3>
          <div class="modal-sub">Modify amount or upload new proof</div>
        </div>
        <button class="modal-close" onclick="closeEditTxModal()" aria-label="Close">✕</button>
      </div>
      <div class="modal-body" style="padding: 24px;">
        <input type="hidden" id="edit-tx-id">
        <input type="hidden" id="edit-tx-receipt">
        
        <!-- Read-only Details -->
        <div style="background:var(--gray-50); border:1px solid var(--gray-200); border-radius:8px; padding:12px; margin-bottom:16px;">
          <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
            <span style="font-size:0.8rem; color:var(--gray-500);">Receipt No</span>
            <span id="edit-tx-display-receipt" style="font-size:0.8rem; font-weight:700; color:var(--gray-900);">—</span>
          </div>
          <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
            <span style="font-size:0.8rem; color:var(--gray-500);">Name</span>
            <span id="edit-tx-display-name" style="font-size:0.8rem; font-weight:600; color:var(--gray-700);">—</span>
          </div>
          <div style="display:flex; justify-content:space-between;">
            <span style="font-size:0.8rem; color:var(--gray-500);">Date</span>
            <span id="edit-tx-display-date" style="font-size:0.8rem; font-weight:600; color:var(--gray-700);">—</span>
          </div>
        </div>

        <!-- Editable Amount -->
        <div class="form-group">
          <label>Amount Paid (₱)</label>
          <input type="number" id="edit-tx-amount" min="0" step="0.01" style="font-size:1.1rem; font-weight:700;">
        </div>

        <!-- Editable Proof -->
        <div class="form-group" style="margin-top:16px;">
          <label>Proof of Payment</label>
          <div id="edit-tx-proof-preview" style="width:100%; height:120px; background:var(--gray-50); border:1.5px dashed var(--gray-200); border-radius:8px; display:flex; flex-direction:column; align-items:center; justify-content:center; overflow:hidden; position:relative; cursor:pointer; transition:all 0.2s;"
            onclick="document.getElementById('edit-tx-proof-input').click()">
          </div>
          <input type="file" id="edit-tx-proof-input" style="display:none;" accept="image/*" onchange="handleEditTxProofPreview(this)">
          <input type="hidden" id="edit-tx-existing-proof">
        </div>

      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-cancel" onclick="closeEditTxModal()">Cancel</button>
        <button class="modal-btn modal-btn-primary" id="btnSaveEditTx" onclick="submitEditTransaction()">💾 Save Changes</button>
      </div>
    </div>
  </div>

    <script>
        // Update status badges on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tx-status-badge').forEach(badge => {
                const row = badge.closest('tr');
                if (row) {
                    const billingStatusInput = row.querySelector('.billing-status');
                    if (billingStatusInput) {
                        const status = billingStatusInput.value;
                        badge.classList.remove('badge-completed', 'badge-partial', 'badge-pending');
                        
                        if (status === 'paid') {
                            badge.classList.add('badge-completed');
                            badge.textContent = 'COMPLETED';
                        } else if (status === 'partial') {
                            badge.classList.add('badge-partial');
                            badge.textContent = 'PARTIAL PAYMENT';
                        } else if (status === 'started') {
                            badge.classList.add('badge-pending');
                            badge.textContent = 'STARTED';
                        } else {
                            badge.classList.add('badge-pending');
                            badge.textContent = status.toUpperCase();
                        }
                    }
                }
            });
        });

        /* ============================================================
           NAVBAR — active links, dropdowns, hamburger, breadcrumb
        ============================================================ */

        /* ============================================================
           ACTION CONTEXT MENU
        ============================================================ */
        function toggleMenu(btn) {
            const menu = btn.nextElementSibling;
            const isOpen = menu.classList.contains('is-open');
            closeAllMenus();
            if (!isOpen) {
                const rect = btn.getBoundingClientRect();
                const spaceBelow = window.innerHeight - rect.bottom;
                if (spaceBelow < 120) {
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
        
        document.addEventListener('click', e => { 
            if (!e.target.closest('.action-cell')) closeAllMenus(); 
        });

        function viewProofImage(btn) {
            closeAllMenus();
            const filename = btn.getAttribute('data-proof-file');
            if (!filename) {
                alert('No proof image available.');
                return;
            }
            const imageSrc = 'uploads/payments/' + filename;
            const imgEl = document.getElementById('proof-img-display');
            imgEl.src = imageSrc;
            imgEl.onerror = function() {
                imgEl.alt = 'Failed to load image';
                console.error('Image failed to load:', imageSrc);
            };
            openModal('modalViewProof');
        }

        function viewProof(filename) {
            document.getElementById('proof-img-display').src = 'uploads/payments/' + filename;
            openModal('modalViewProof');
        }

        function downloadProof() {
            const src = document.getElementById('proof-img-display').src;
            if (src) {
                const a = document.createElement('a');
                a.href = src;
                a.download = src.split('/').pop() || 'proof';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }
        }

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
        function openModal(id) {
            const backdrop = document.getElementById(id);
            if (!backdrop) return;
            backdrop.classList.add('is-open');
            const modal = backdrop.querySelector('.modal');
            if (modal) modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            const backdrop = document.getElementById(id);
            if (!backdrop) return;
            backdrop.classList.remove('is-open');
            const modal = backdrop.querySelector('.modal');
            if (modal) modal.style.display = '';
            document.body.style.overflow = '';
        }

        // Close modal on backdrop click
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', function(e) {
                if (e.target === this) closeModal(this.id);
            });
        });
        // Close on Escape
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { document.querySelectorAll('.modal-backdrop.is-open').forEach(m => m.classList.remove('is-open')); document.body.style.overflow = ''; } });

        /* ============================================================
           ADD TRANSACTION — Receipt Lookup + Form
        ============================================================ */
        let _txBillingData = null; // Stores the looked-up billing data

        function lookupReceipt() {
            const receiptNo = document.getElementById('tx-receipt-no').value.trim();
            const statusEl = document.getElementById('tx-lookup-status');
            const autofillSection = document.getElementById('tx-autofill-section');
            const submitBtn = document.getElementById('btnRecordTx');

            if (!receiptNo) {
                statusEl.textContent = '⚠️ Please enter a receipt number';
                statusEl.style.color = 'var(--amber)';
                return;
            }

            statusEl.textContent = '🔍 Looking up...';
            statusEl.style.color = 'var(--blue)';

            fetch('api_lookup_receipt.php?receipt_no=' + encodeURIComponent(receiptNo))
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        statusEl.textContent = '❌ ' + (res.error || 'Not found');
                        statusEl.style.color = 'var(--red)';
                        autofillSection.style.display = 'none';
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.5';
                        _txBillingData = null;
                        return;
                    }

                    const d = res.data;

                    if (d.already_paid) {
                        statusEl.textContent = '✅ This billing is already marked as fully paid';
                        statusEl.style.color = 'var(--green)';
                        autofillSection.style.display = 'none';
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.5';
                        _txBillingData = null;
                        return;
                    }

                    // Allow both first transaction and additional payments for partial payments
                    if (d.transaction_exists && !d.is_partial) {
                        statusEl.textContent = '⚠️ A transaction already exists for this receipt';
                        statusEl.style.color = 'var(--amber)';
                        autofillSection.style.display = 'none';
                        submitBtn.disabled = true;
                        submitBtn.style.opacity = '0.5';
                        _txBillingData = null;
                        return;
                    }

                    // Success — fill in the data
                    _txBillingData = d;
                    
                    // Different message for partial payments
                    if (d.is_partial) {
                        statusEl.textContent = '📝 Partial payment found — add additional payment to complete';
                        statusEl.style.color = 'var(--blue)';
                    } else {
                        statusEl.textContent = '✅ Receipt found — billing details loaded';
                        statusEl.style.color = 'var(--green)';
                    }

                    document.getElementById('tx-hh').textContent = d.household_id || 'N/A';
                    document.getElementById('tx-name').textContent = d.full_name || 'N/A';
                    document.getElementById('tx-period').textContent = d.billing_month || 'N/A';
                    document.getElementById('tx-usage').textContent = (d.usage_m3 || 0) + ' m³';
                    document.getElementById('tx-rate').textContent = '₱' + (d.rate || 0);

                    // Show remaining balance for partial payments, or full amount for new payments
                    const displayAmount = d.is_partial ? parseFloat(d.remaining_balance || 0) : parseFloat(d.amount_due || 0);
                    document.getElementById('tx-amount-due').textContent = '₱' + displayAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    
                    // Update label and payment info based on payment type
                    const amountLabel = document.getElementById('tx-amount-label');
                    const paymentInfo = document.getElementById('tx-payment-info');
                    
                    if (d.is_partial) {
                        amountLabel.textContent = 'Amount to Pay (₱)';
                        paymentInfo.innerHTML = `📝 Previous payment: ₱${parseFloat(d.total_paid).toFixed(2)} | Remaining: ₱${displayAmount.toFixed(2)}`;
                        paymentInfo.style.color = 'var(--blue)';
                    } else {
                        amountLabel.textContent = 'Amount Paid (₱)';
                        paymentInfo.innerHTML = '';
                    }
                    
                    // Pre-fill amount paid with remaining balance for partial payments
                    document.getElementById('tx-amount-paid').value = displayAmount.toFixed(2);
                    calculateChange(); // Initialize change

                    autofillSection.style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                })
                .catch(() => {
                    statusEl.textContent = '❌ Network error — check your connection';
                    statusEl.style.color = 'var(--red)';
                    _txBillingData = null;
                });
        }

        // Allow Enter key to trigger lookup
        document.getElementById('tx-receipt-no').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); lookupReceipt(); }
        });

        function calculateChange() {
            if (!_txBillingData) return;
            // Use remaining_balance for partial payments, otherwise use amount_due
            const due = _txBillingData.is_partial ? 
                parseFloat(_txBillingData.remaining_balance) || 0 : 
                parseFloat(_txBillingData.amount_due) || 0;
            const paid = parseFloat(document.getElementById('tx-amount-paid').value) || 0;
            const changeInput = document.getElementById('tx-change');
            
            if (paid > due) {
                changeInput.value = (paid - due).toFixed(2);
                changeInput.style.color = 'var(--green)';
            } else {
                changeInput.value = '0.00';
                changeInput.style.color = 'var(--gray-500)';
            }
        }

        function handleTxProofPreview(input) {
            const box = document.getElementById('tx-proof-preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    box.innerHTML = `<img src="${e.target.result}" alt="Proof" style="width:100%;height:100%;object-fit:cover;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function submitTransaction() {
            if (!_txBillingData) {
                alert('Please look up a valid receipt number first.');
                return;
            }

            const amountPaid = parseFloat(document.getElementById('tx-amount-paid').value) || 0;
            if (amountPaid <= 0) {
                alert('Please enter a valid amount paid.');
                document.getElementById('tx-amount-paid').focus();
                return;
            }

            const btn = document.getElementById('btnRecordTx');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '⏳ Processing...';

            const formData = new FormData();
            formData.append('receipt_no', _txBillingData.receipt_no);
            formData.append('amount_paid', amountPaid);

            const proofInput = document.getElementById('tx-proof-input');
            if (proofInput.files[0]) {
                formData.append('payment_proof', proofInput.files[0]);
            }

            fetch('api_add_transaction.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('✅ Transaction recorded successfully!');
                    window.location.reload();
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

        function closeAddTxModal() {
            closeModal('modalAddTransaction');
            // Reset the form
            document.getElementById('tx-receipt-no').value = '';
            document.getElementById('tx-lookup-status').textContent = 'Enter a receipt number and click Lookup';
            document.getElementById('tx-lookup-status').style.color = 'var(--gray-400)';
            document.getElementById('tx-autofill-section').style.display = 'none';
            document.getElementById('tx-amount-paid').value = '';
            document.getElementById('tx-change').value = '';
            document.getElementById('tx-payment-info').innerHTML = '';
            document.getElementById('tx-amount-label').textContent = 'Amount Paid (₱)';
            document.getElementById('tx-proof-input').value = '';
            document.getElementById('tx-proof-preview').innerHTML = '<span style="font-size:1.2rem; margin-bottom:2px;">📸</span><span style="font-size:0.7rem; font-weight:700; color:var(--gray-400); text-transform:uppercase;">Click to Upload Proof</span>';
            document.getElementById('btnRecordTx').disabled = true;
            document.getElementById('btnRecordTx').style.opacity = '0.5';
            _txBillingData = null;
        }

        /* ============================================================
           EDIT TRANSACTION
        ============================================================ */
        function openEditTx(btn) {
            closeAllMenus();
            const row = btn.closest('tr');
            
            const id = row.getAttribute('data-id');
            const receipt = row.getAttribute('data-receipt-no');
            const amount = row.getAttribute('data-amount');
            const proof = row.getAttribute('data-proof');
            const name = row.getAttribute('data-name');
            const date = row.getAttribute('data-date');

            document.getElementById('edit-tx-id').value = id;
            document.getElementById('edit-tx-receipt').value = receipt;
            document.getElementById('edit-tx-display-receipt').textContent = receipt;
            document.getElementById('edit-tx-display-name').textContent = name;
            document.getElementById('edit-tx-display-date').textContent = date;
            
            document.getElementById('edit-tx-amount').value = parseFloat(amount).toFixed(2);
            document.getElementById('edit-tx-existing-proof').value = proof;

            const previewBox = document.getElementById('edit-tx-proof-preview');
            if (proof) {
                previewBox.innerHTML = `<img src="uploads/payments/${proof}" style="width:100%; height:100%; object-fit:cover; border-radius:8px;">`;
            } else {
                previewBox.innerHTML = `<span style="font-size:1.2rem; margin-bottom:2px;">📸</span><span style="font-size:0.7rem; font-weight:700; color:var(--gray-400); text-transform:uppercase;">Click to Upload Proof</span>`;
            }

            document.getElementById('modalEditTransaction').classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }

        function closeEditTxModal() {
            closeModal('modalEditTransaction');
            document.getElementById('edit-tx-proof-input').value = '';
        }

        function handleEditTxProofPreview(input) {
            const box = document.getElementById('edit-tx-proof-preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    box.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; border-radius:8px;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function submitEditTransaction() {
            const id = document.getElementById('edit-tx-id').value;
            const amountPaid = parseFloat(document.getElementById('edit-tx-amount').value) || 0;
            const existingProof = document.getElementById('edit-tx-existing-proof').value;
            
            if (!id) return;
            if (amountPaid < 0) {
                alert('Please enter a valid amount paid.');
                document.getElementById('edit-tx-amount').focus();
                return;
            }

            const btn = document.getElementById('btnSaveEditTx');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '⏳ Saving...';

            const formData = new FormData();
            formData.append('tx_id', id);
            formData.append('amount_paid', amountPaid);
            formData.append('existing_proof', existingProof);

            const proofInput = document.getElementById('edit-tx-proof-input');
            if (proofInput.files[0]) {
                formData.append('payment_proof', proofInput.files[0]);
            }

            fetch('api_edit_transaction.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert('✅ Transaction updated successfully!');
                    window.location.reload();
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
</body>

</html>