<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

$search_q = $_GET['q'] ?? '';
$search_date = $_GET['date'] ?? '';
$search_status = $_GET['status'] ?? '';

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

if ($search_date !== '') {
    $where_clauses[] = "DATE(t.payment_date) = ?";
    $params[] = $search_date;
}

if ($search_status !== '') {
    if ($search_status === 'partial') {
        $where_clauses[] = "EXISTS (SELECT 1 FROM billings b WHERE b.receipt_no = t.receipt_no AND b.amount_due > t.amount_paid)";
    } elseif ($search_status === 'completed') {
        $where_clauses[] = "NOT EXISTS (SELECT 1 FROM billings b WHERE b.receipt_no = t.receipt_no AND b.amount_due > t.amount_paid)";
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

if ($search_date !== '') {
    $collStmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid
        FROM billings WHERE DATE(paid_date) = ?");
    $collStmt->execute([$search_date]);
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
$query_params = $_GET;
unset($query_params['page']);
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
                <div class="summary-sub"><?= $search_date ? date('F j, Y', strtotime($search_date)) : 'Overall' ?></div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Total Collected</div>
                <div class="summary-value"><?= $total_collected ?></div>
                <div class="summary-sub"><?= $search_date ? date('F j, Y', strtotime($search_date)) : 'All time' ?></div>
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
            <div class="filter-group">
                <button type="button" class="add-transaction-btn" onclick="openAddTransactionModal()">Add Transaction</button>
                <div class="filter-wrap">
                    <input type="date" name="date" class="filter-select" value="<?= htmlspecialchars($search_date) ?>" onchange="this.form.submit()">
                </div>
                <div class="filter-wrap">
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="" <?= $search_status === '' ? 'selected' : '' ?>>All Status</option>
                        <option value="completed" <?= $search_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="partial" <?= $search_status === 'partial' ? 'selected' : '' ?>>Partial Payment</option>
                    </select>
                </div>
            </div>
        </form>

        <!-- Transaction Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-header-title">
                    <h2>All Transactions</h2>
                    <p>Showing all payments and account adjustments · <?= $search_date ? date('F j, Y', strtotime($search_date)) : 'All time' ?></p>
                </div>
                <button class="export-btn">📥 Export</button>
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
                            ?>
                            <tr>
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
                                <td><span class="tx-status-badge badge-completed">COMPLETED</span></td>
                                <td><button class="action-btn">⋯</button></td>
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
                    <a href="?<?= http_build_query(array_merge($query_params, ['page' => $page - 1])) ?>" class="pagination-btn"
                        style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">«</a>
                <?php else: ?>
                    <button class="pagination-btn" disabled>«</button>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);

                if ($start_page > 1) {
                    echo '<a href="?' . http_build_query(array_merge($query_params, ['page' => 1])) . '" class="pagination-btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">1</a>';
                    if ($start_page > 2) {
                        echo '<span style="color: var(--gray-400); margin: 0 4px;">...</span>';
                    }
                }

                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $page) {
                        echo '<button class="pagination-btn active">' . $i . '</button>';
                    } else {
                        echo '<a href="?' . http_build_query(array_merge($query_params, ['page' => $i])) . '" class="pagination-btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">' . $i . '</a>';
                    }
                }

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span style="color: var(--gray-400); margin: 0 4px;">...</span>';
                    }
                    echo '<a href="?' . http_build_query(array_merge($query_params, ['page' => $total_pages])) . '" class="pagination-btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($query_params, ['page' => $page + 1])) ?>" class="pagination-btn"
                        style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">»</a>
                <?php else: ?>
                    <button class="pagination-btn" disabled>»</button>
                <?php endif; ?>
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

        /* Add Transaction Modal */
        function openAddTransactionModal() {
            document.getElementById('addTransactionModal').style.display = 'block';
        }

        function closeAddTransactionModal() {
            document.getElementById('addTransactionModal').style.display = 'none';
            document.getElementById('addTransactionForm').reset();
        }

        function fetchBillingDetails() {
            const receipt_no = document.getElementById('receipt_no').value.trim();
            if (!receipt_no) return;
            fetch('api_get_billing_details.php?receipt_no=' + encodeURIComponent(receipt_no))
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        // Clear fields
                        document.getElementById('resident_name').value = '';
                        document.getElementById('address').value = '';
                        document.getElementById('usage').value = '';
                        document.getElementById('rate').value = '';
                        document.getElementById('amount_due').value = '';
                        document.getElementById('change').value = '';
                        return;
                    }
                    document.getElementById('resident_name').value = data.resident_name;
                    document.getElementById('address').value = data.address;
                    document.getElementById('usage').value = data.usage;
                    document.getElementById('rate').value = data.rate;
                    document.getElementById('amount_due').value = data.amount_due;
                    calculateChange();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while fetching details.');
                });
        }

        function calculateChange() {
            const payment = parseFloat(document.getElementById('payment_amount').value) || 0;
            const due = parseFloat(document.getElementById('amount_due').value) || 0;
            const change = payment - due;
            document.getElementById('change').value = change.toFixed(2);
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('addTransactionForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('api_add_transaction.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Transaction added successfully');
                        closeAddTransactionModal();
                        location.reload();
                    } else {
                        alert(data.error || 'An error occurred');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while adding the transaction.');
                });
            });
        });

    </script>

    <!-- Add Transaction Modal -->
    <div id="addTransactionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddTransactionModal()">&times;</span>
            <h2>Add New Transaction</h2>
            <form id="addTransactionForm" enctype="multipart/form-data">
                <label for="receipt_no">Receipt Number:</label>
                <input type="text" id="receipt_no" name="receipt_no" required onchange="fetchBillingDetails()">

                <label for="resident_name">Resident Name:</label>
                <input type="text" id="resident_name" readonly>

                <label for="address">Address:</label>
                <input type="text" id="address" readonly>

                <label for="usage">Usage (m³):</label>
                <input type="text" id="usage" readonly>

                <label for="rate">Rate (₱/m³):</label>
                <input type="text" id="rate" readonly>

                <label for="amount_due">Amount Due:</label>
                <input type="text" id="amount_due" readonly>

                <label for="payment_amount">Payment Amount:</label>
                <input type="number" id="payment_amount" name="payment_amount" step="0.01" required onchange="calculateChange()">

                <label for="change">Change:</label>
                <input type="text" id="change" readonly>

                <label for="receipt_image">Receipt Image:</label>
                <input type="file" id="receipt_image" name="receipt_image" accept="image/*">

                <button type="submit">Confirm</button>
            </form>
        </div>
    </div>

</body>

</html>