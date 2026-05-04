<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

// Search and Overdue logic
$search_q = $_GET['q'] ?? '';
$current_month = date('M Y');

// An account is only overdue if it is not paid, owes money (> 0), AND the billing month is strictly in the past
$where_sql = "b.status != 'paid' AND b.amount_due > 0 AND STR_TO_DATE(CONCAT('01 ', b.billing_month), '%d %b %Y') < STR_TO_DATE(CONCAT('01 ', ?), '%d %b %Y')";
$params = [$current_month];

if ($search_q) {
    $where_sql .= " AND (r.full_name LIKE ? OR r.household_id LIKE ?)";
    $params[] = "%$search_q%";
    $params[] = "%$search_q%";
}

// Fetch grouped overdue residents with the most recent remark
$stmt = $pdo->prepare("
    SELECT 
        r.id, r.household_id, r.full_name, r.block_no, r.lot_no, r.contact_number,
        SUM(CASE 
              WHEN b.status = 'partial' THEN b.amount_due - COALESCE((SELECT SUM(t.amount_paid) FROM transactions t WHERE t.receipt_no = b.receipt_no), 0)
              ELSE b.amount_due
            END) as total_overdue,
        COUNT(b.id) as months_overdue,
        (SELECT remarks FROM billings WHERE resident_id = r.id AND status != 'paid' AND (remarks IS NOT NULL AND remarks != '') ORDER BY id DESC LIMIT 1) as latest_remark
    FROM residents r
    JOIN billings b ON r.id = b.resident_id
    WHERE $where_sql
    GROUP BY r.id
    ORDER BY total_overdue DESC
");
$stmt->execute($params);
$overdue_residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_delinquent_count = count($overdue_residents);
$total_delinquent_amount = array_sum(array_column($overdue_residents, 'total_overdue'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BayanTap – Overdue Accounts</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/dashboard.css?v=5">
    <link rel="stylesheet" href="CSS/overdue.css">
</head>
<body>
  <?php $current_page = 'overdue'; include 'navbar.php'; ?>



  <div class="page-header" style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);">
    <div class="page-title-section">
      <h1 class="page-title">Overdue Management</h1>
      <p class="page-subtitle">Track delinquent households and record collection efforts.</p>
    </div>
  </div>

  <div class="main">
    <div class="summary-grid">
      <div class="summary-card overdue-card">
        <div class="summary-label">Total Delinquent</div>
        <div class="summary-value"><?= $total_delinquent_count ?></div>
        <div class="summary-sub">Households with unpaid bills</div>
      </div>
      <div class="summary-card overdue-card">
        <div class="summary-label">Total Outstanding</div>
        <div class="summary-value" style="color:var(--red);">₱<?= number_format($total_delinquent_amount, 2) ?></div>
        <div class="summary-sub">Accumulated across all periods</div>
      </div>
    </div>

    <form method="GET" class="controls-bar">
      <div class="search-wrap">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg>
        <input type="text" name="q" value="<?= htmlspecialchars($search_q) ?>" placeholder="Search by name or House ID...">
      </div>
      <button type="submit" class="filter-btn" style="background:var(--blue); color:white;">Search</button>
    </form>

    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width: 25%;">Resident / Household</th>
              <th style="width: 15%;">Address</th>
              <th style="width: 15%;">Overdue Months</th>
              <th style="width: 25%;">Latest Remark</th>
              <th style="width: 12%; text-align: right;">Balance</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($overdue_residents)): ?>
              <tr><td colspan="6" style="text-align:center; padding:40px; color:var(--gray-400);">No overdue accounts found.</td></tr>
            <?php endif; ?>
            <?php foreach ($overdue_residents as $r): ?>
              <tr>
                <td>
                  <div style="font-weight:700; color:var(--gray-900); font-size: 0.95rem;"><?= htmlspecialchars($r['full_name']) ?></div>
                  <div style="font-size:0.75rem; color:var(--gray-400); margin-top: 2px;"><?= htmlspecialchars($r['household_id']) ?></div>
                </td>
                <td style="color: var(--gray-600); font-weight: 500;"><?= htmlspecialchars($r['block_no'] . ' ' . $r['lot_no']) ?></td>
                <td><span class="months-pill"><?= $r['months_overdue'] ?> Months</span></td>
                <td>
                  <div style="font-size: 0.8rem; color: var(--gray-500); font-style: italic; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($r['latest_remark'] ?? 'No remarks yet') ?>">
                    <?= htmlspecialchars($r['latest_remark'] ?? '—') ?>
                  </div>
                </td>
                <td style="text-align:right;">
                  <div style="font-weight:800; color:var(--red); font-size: 1.05rem;">₱<?= number_format($r['total_overdue'] ?? 0, 2) ?></div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Details Modal -->
  <div class="modal-backdrop" id="modalDetails" role="dialog" aria-modal="true">
    <div class="modal" style="max-width:800px; width:95%;">
      <div class="modal-header">
        <div>
          <h3 id="modalTitle">Account Breakdown</h3>
          <div class="modal-sub">Detailed unpaid months and remarks</div>
        </div>
        <button class="modal-close" onclick="closeModal('modalDetails')">✕</button>
      </div>
      <div class="modal-body" style="padding:0;">
        <div class="table-wrap" style="border:none; border-radius:0;">
          <table class="details-table">
            <thead>
              <tr>
                <th>Month</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody id="detailsBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Remark Modal -->
  <div class="modal-backdrop" id="modalRemark" role="dialog" aria-modal="true">
    <div class="modal" style="max-width:400px;">
      <div class="modal-header">
        <h3>📝 Update Remark</h3>
        <button class="modal-close" onclick="closeModal('modalRemark')">✕</button>
      </div>
      <div class="modal-body">
        <p id="remarkMonth" style="font-weight:700; margin-bottom:12px;"></p>
        <textarea id="remarkInput" style="width:100%; height:120px; padding:12px; border-radius:8px; border:1.5px solid var(--gray-200); font-family:inherit;" placeholder="Add a note on why this is overdue..."></textarea>
      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-cancel" onclick="closeModal('modalRemark')">Cancel</button>
        <button class="modal-btn modal-btn-primary" onclick="saveRemark()">Save Remark</button>
      </div>
    </div>
  </div>

  <script>
    let _activeBillingId = null;


    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    function viewDetails(resId, name) {
      document.getElementById('modalTitle').textContent = name;
      const body = document.getElementById('detailsBody');
      body.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px;">⌛ Loading...</td></tr>';
      openModal('modalDetails');
      fetch('api_get_history.php?resident_id=' + resId)
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            const unpaid = data.history.filter(b => b.status !== 'paid');
            body.innerHTML = unpaid.map(b => `
              <tr>
                <td style="font-weight:700;">${b.billing_month}</td>
                <td style="font-weight:700; color:var(--red);">₱${parseFloat(b.amount_due).toLocaleString()}</td>
                <td><span class="months-pill" style="background:var(--amber-bg); color:var(--amber);">${b.status.toUpperCase()}</span></td>
                <td class="remark-text" id="rem-${b.id}">${b.remarks || 'No remarks yet'}</td>
              </tr>
            `).join('');
          }
        });
    }

    function openRemark(id, month, currentRemark) {
      _activeBillingId = id;
      document.getElementById('remarkMonth').textContent = 'Period: ' + month;
      document.getElementById('remarkInput').value = currentRemark;
      openModal('modalRemark');
    }

    function saveRemark() {
      const remarks = document.getElementById('remarkInput').value;
      fetch('api_update_remark.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ billing_id: _activeBillingId, remarks: remarks })
      }).then(res => res.json()).then(data => {
        if (data.success) {
          document.getElementById('rem-' + _activeBillingId).textContent = remarks || 'No remarks yet';
          closeModal('modalRemark');
        }
      });
    }

    // Close menus on click outside
    window.onclick = function(event) {
      if (!event.target.closest('.user-menu-wrap')) {
        const dd = document.getElementById('userDropdown');
        if (dd) dd.classList.remove('is-open');
      }
      if (!event.target.closest('.nav-icon-btn')) {
        const dd = document.getElementById('notifDropdown');
        if (dd) dd.classList.remove('is-open');
      }
    }
  </script>
</body>
</html>
