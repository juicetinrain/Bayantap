<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

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
    $where_clauses[] = "DATE_FORMAT(t.payment_date, '%b %Y') = ?";
    $params[] = $search_month;
}

if ($search_type !== '') {
    // Current database doesn't have a specific type, so we just dummy filter if 'payment' is selected
    // or expand this if you have a `type` column in `transactions`
}

if ($search_status !== '') {
    // Transactions imply completed status historically, but you can expand this if needed
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
    $collStmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid
        FROM billings WHERE billing_month = ?");
    $collStmt->execute([$search_month]);
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
    <link rel="stylesheet" href="dashboard.css?v=4">
    <style>
        :root {
            --blue: #2563eb;
            --blue-light: #eff6ff;
            --teal: #0ea5e9;
            --green: #16a34a;
            --green-bg: #dcfce7;
            --red: #dc2626;
            --red-bg: #fee2e2;
            --amber: #d97706;
            --amber-bg: #fef3c7;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
            --white: #ffffff;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .08);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, .10);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, .13);
            --radius: 14px;
            --radius-sm: 8px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            min-height: 100vh;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 55%, #1e3a8a 100%);
            padding: clamp(24px, 4vw, 40px) clamp(16px, 4vw, 48px);
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .page-title-section {
            position: relative;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: .95rem;
            color: rgba(255, 255, 255, 0.9);
        }


        /* Filters & Controls */
        .controls-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
            align-items: center;
        }

        .search-wrap {
            flex: 1 1 260px;
            position: relative;
        }

        .search-wrap svg {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            pointer-events: none;
        }

        .search-wrap input {
            width: 100%;
            padding: 11px 16px 11px 42px;
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: .87rem;
            color: var(--gray-900);
            background: var(--white);
            box-shadow: var(--shadow-sm);
            transition: border-color .2s;
            outline: none;
        }

        .search-wrap input:focus {
            border-color: var(--blue);
        }

        .filter-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 11px 18px;
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-sm);
            background: var(--white);
            font-family: inherit;
            font-size: .85rem;
            font-weight: 600;
            color: var(--gray-700);
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: border-color .2s, background .2s;
            white-space: nowrap;
        }

        .filter-btn:hover {
            border-color: var(--blue);
            background: var(--blue-light);
        }

        .filter-btn.active {
            background: var(--blue);
            color: var(--white);
            border-color: var(--blue);
        }

        /* ── TRANSACTION TABLE ── */
        .table-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .table-card-header {
            padding: 18px 22px 14px;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-card-header h2 {
            font-size: 1rem;
            font-weight: 700;
        }

        .table-card-header p {
            font-size: .76rem;
            color: var(--gray-500);
            margin-top: 2px;
        }

        .table-card-header-title {
            flex: 1;
        }

        .export-btn {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-weight: 600;
            font-size: .8rem;
            cursor: pointer;
            transition: background .2s;
        }

        .export-btn:hover {
            background: var(--gray-200);
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        thead th {
            padding: 12px 14px;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--gray-500);
            background: var(--gray-50);
            text-align: left;
            white-space: nowrap;
        }

        tbody tr {
            border-top: 1px solid var(--gray-100);
            transition: background .15s;
        }

        tbody tr:hover {
            background: var(--gray-50);
        }

        tbody td {
            padding: 14px;
            vertical-align: middle;
            font-size: .85rem;
        }

        /* Transaction cells */
        .tx-date {
            font-weight: 600;
            color: var(--gray-900);
        }

        .tx-date-sub {
            font-size: .72rem;
            color: var(--gray-400);
            margin-top: 2px;
        }

        .tx-ref {
            font-family: 'DM Mono', monospace;
            font-size: .8rem;
            font-weight: 600;
            color: var(--blue);
        }

        .tx-type {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: .78rem;
            font-weight: 600;
        }

        .tx-type.payment {
            background: var(--green-bg);
            color: var(--green);
        }

        .tx-type.collection {
            background: #dbeafe;
            color: var(--blue);
        }

        .tx-type.adjustment {
            background: var(--amber-bg);
            color: var(--amber);
        }

        .tx-type.refund {
            background: #f3e8ff;
            color: #7c3aed;
        }

        .tx-description {
            font-size: .85rem;
            color: var(--gray-700);
            font-weight: 500;
        }

        .tx-description-sub {
            font-size: .72rem;
            color: var(--gray-400);
            margin-top: 2px;
        }

        .tx-amount {
            font-size: .9rem;
            font-weight: 700;
            color: var(--gray-900);
            text-align: right;
        }

        .tx-amount.positive {
            color: var(--green);
        }

        .tx-amount.negative {
            color: var(--red);
        }

        .tx-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .04em;
        }

        .badge-completed {
            background: var(--green-bg);
            color: var(--green);
        }

        .badge-pending {
            background: var(--amber-bg);
            color: var(--amber);
        }

        .badge-failed {
            background: var(--red-bg);
            color: var(--red);
        }

        .action-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--gray-400);
            font-size: 18px;
            padding: 4px;
            border-radius: 6px;
            transition: background .15s, color .15s;
        }

        .action-btn:hover {
            background: var(--gray-100);
            color: var(--blue);
        }

        /* ── PAGINATION ── */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 20px;
            border-top: 1px solid var(--gray-100);
            font-size: .85rem;
        }

        .pagination-info {
            color: var(--gray-500);
            margin-right: 16px;
        }

        .pagination-btn {
            width: 36px;
            height: 36px;
            border: 1.5px solid var(--gray-200);
            background: var(--white);
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-family: inherit;
            font-weight: 600;
            color: var(--gray-700);
            transition: all .2s;
        }

        .pagination-btn:hover:not(:disabled) {
            border-color: var(--blue);
            background: var(--blue-light);
            color: var(--blue);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-btn.active {
            background: var(--blue);
            color: var(--white);
            border-color: var(--blue);
        }

        /* ── SUMMARY CARD ── */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            padding: 18px 20px;
        }

        .summary-label {
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--gray-500);
            letter-spacing: .04em;
            margin-bottom: 8px;
        }

        .summary-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--gray-900);
        }

        .summary-sub {
            font-size: .72rem;
            color: var(--gray-400);
            margin-top: 6px;
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .controls-bar {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .filter-btn {
                flex: 1;
            }

            .table-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .export-btn {
                width: 100%;
            }
        }

        @media (max-width: 640px) {
            nav {
                height: auto;
                padding: 10px 16px;
                flex-wrap: wrap;
                gap: 10px;
            }

            .nav-breadcrumb {
                display: none;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }

            .table-wrap {
                font-size: .8rem;
            }

            tbody td {
                padding: 10px;
            }

            .controls-bar {
                flex-direction: column;
            }

            .search-wrap {
                width: 100%;
            }
        }

        @media (max-width: 420px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .summary-value {
                font-size: 1.4rem;
            }

            .pagination-btn {
                width: 32px;
                height: 32px;
                font-size: .75rem;
            }
        }

        @media print {

            nav,
            .page-header,
            .controls-bar,
            .pagination,
            .export-btn {
                display: none !important;
            }

            .table-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
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
            <div class="filter-group">
                <div class="filter-wrap">
                    <select name="type" class="filter-select" onchange="this.form.submit()">
                        <option value="" <?= $search_type === '' ? 'selected' : '' ?>>All Types</option>
                        <option value="payment" <?= $search_type === 'payment' ? 'selected' : '' ?>>Payment</option>
                    </select>
                </div>
                <div class="filter-wrap">
                    <select name="month" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Months</option>
                        <?php foreach ($available_months as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $m === $search_month ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-wrap">
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="" <?= $search_status === '' ? 'selected' : '' ?>>All Status</option>
                        <option value="completed" <?= $search_status === 'completed' ? 'selected' : '' ?>>Completed
                        </option>
                    </select>
                </div>
            </div>
        </form>

        <!-- Transaction Table -->
        <div class="table-card">
            <div class="table-card-header">
                <div class="table-card-header-title">
                    <h2>All Transactions</h2>
                    <p>Showing all payments and account adjustments · January 2026</p>
                </div>
                <button class="export-btn">📥 Export</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Type</th>
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
                                <td><span class="tx-type payment">💳 Payment</span></td>
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


    </script>
</body>

</html>