<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

// Handlers for month/year dropdowns
$stmtAllMonths = $pdo->query("SELECT DISTINCT billing_month FROM billings ORDER BY STR_TO_DATE(CONCAT('01 ', billing_month), '%d %b %Y') ASC");
$db_months = $stmtAllMonths->fetchAll(PDO::FETCH_COLUMN);

// Extract available years
$available_years = [];
foreach ($db_months as $m) {
    $parts = explode(' ', $m);
    if (isset($parts[1]) && !in_array($parts[1], $available_years)) {
        $available_years[] = $parts[1];
    }
}
rsort($available_years);
if (empty($available_years)) $available_years = [date('Y')];

$selected_year = $_GET['year'] ?? ($available_years[0] ?? date('Y'));
$available_months_for_year = array_filter($db_months, function($m) use ($selected_year) {
    return strpos($m, $selected_year) !== false;
});

$selected_period = $_GET['month'] ?? (reset($available_months_for_year) ?: 'yearly');
$is_yearly = ($selected_period === 'yearly');

// KPI: Total Revenue (from transactions)
if ($is_yearly) {
    $stmtRev = $pdo->prepare("SELECT SUM(amount_paid) FROM transactions WHERE DATE_FORMAT(payment_date, '%Y') = ?");
    $stmtRev->execute([$selected_year]);
} else {
    $stmtRev = $pdo->prepare("SELECT SUM(amount_paid) FROM transactions WHERE DATE_FORMAT(payment_date, '%b %Y') = ?");
    $stmtRev->execute([$selected_period]);
}
$total_revenue = $stmtRev->fetchColumn() ?: 0.00;

// KPI: Collection Rate & Outstanding Logic
if ($is_yearly) {
    $stmtColl = $pdo->prepare("
      SELECT 
        COUNT(*) as total_bills,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bills,
        SUM(CASE WHEN status != 'paid' THEN amount_due ELSE 0 END) as outstanding_amount
      FROM billings 
      WHERE billing_month LIKE ?
    ");
    $stmtColl->execute(['% ' . $selected_year]);
} else {
    $stmtColl = $pdo->prepare("
      SELECT 
        COUNT(*) as total_bills,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_bills,
        SUM(CASE WHEN status != 'paid' THEN amount_due ELSE 0 END) as outstanding_amount
      FROM billings 
      WHERE billing_month = ?
    ");
    $stmtColl->execute([$selected_period]);
}
$collData = $stmtColl->fetch(PDO::FETCH_ASSOC);

$total_bills = (int)($collData['total_bills'] ?? 0);
$paid_bills = (int)($collData['paid_bills'] ?? 0);
$outstanding_amount = (float)($collData['outstanding_amount'] ?? 0);
$collection_rate = $total_bills > 0 ? round(($paid_bills / $total_bills) * 100, 1) : 0;


// KPI: Active Connections
$active_connections = $pdo->query("SELECT COUNT(*) FROM residents")->fetchColumn();

// Data Tables: Top Consumers
if ($is_yearly) {
    $stmtTop = $pdo->prepare("
      SELECT SUM(b.usage_m3) as usage_m3, SUM(b.amount_due) as amount_due, r.full_name, r.block_no, r.lot_no, r.household_id 
      FROM billings b
      JOIN residents r ON b.resident_id = r.id
      WHERE b.billing_month LIKE ? 
      GROUP BY r.id
      ORDER BY usage_m3 DESC LIMIT 5
    ");
    $stmtTop->execute(['% ' . $selected_year]);
} else {
    $stmtTop = $pdo->prepare("
      SELECT b.usage_m3, b.amount_due, r.full_name, r.block_no, r.lot_no, r.household_id 
      FROM billings b
      JOIN residents r ON b.resident_id = r.id
      WHERE b.billing_month = ? 
      ORDER BY b.usage_m3 DESC LIMIT 5
    ");
    $stmtTop->execute([$selected_period]);
}
$top_consumers = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

// Data Tables: Delinquent Accounts
if ($is_yearly) {
    $stmtUnpaid = $pdo->prepare("
      SELECT SUM(b.amount_due) as amount_due, MAX(b.billing_month) as billing_month, r.full_name, r.block_no, r.lot_no, r.contact_number, r.household_id
      FROM billings b
      JOIN residents r ON b.resident_id = r.id
      WHERE b.billing_month LIKE ? AND b.status IN ('unpaid', 'pending')
      GROUP BY r.id
      ORDER BY amount_due DESC LIMIT 5
    ");
    $stmtUnpaid->execute(['% ' . $selected_year]);
} else {
    $stmtUnpaid = $pdo->prepare("
      SELECT b.amount_due, b.billing_month, r.full_name, r.block_no, r.lot_no, r.contact_number, r.household_id
      FROM billings b
      JOIN residents r ON b.resident_id = r.id
      WHERE b.billing_month = ? AND b.status IN ('unpaid', 'pending')
      ORDER BY b.amount_due DESC LIMIT 5
    ");
    $stmtUnpaid->execute([$selected_period]);
}
$delinquent_accounts = $stmtUnpaid->fetchAll(PDO::FETCH_ASSOC);

// Chart Data: Payment Status
if ($is_yearly) {
    $stmtStatusPie = $pdo->prepare("SELECT status, COUNT(*) as count FROM billings WHERE billing_month LIKE ? GROUP BY status");
    $stmtStatusPie->execute(['% ' . $selected_year]);
} else {
    $stmtStatusPie = $pdo->prepare("SELECT status, COUNT(*) as count FROM billings WHERE billing_month = ? GROUP BY status");
    $stmtStatusPie->execute([$selected_period]);
}
$pie_data_raw = $stmtStatusPie->fetchAll(PDO::FETCH_ASSOC);
$pie_data = ['paid' => 0, 'unpaid' => 0, 'pending' => 0];
foreach($pie_data_raw as $row) {
    if(isset($pie_data[$row['status']])) {
        $pie_data[$row['status']] = $row['count'];
    }
}


// Chart Data: Revenue Trend
if ($is_yearly) {
    // Show all months of the selected year
    $stmtTrend = $pdo->prepare("
      SELECT DATE_FORMAT(payment_date, '%b %Y') as month, SUM(amount_paid) as revenue 
      FROM transactions 
      WHERE DATE_FORMAT(payment_date, '%Y') = ?
      GROUP BY DATE_FORMAT(payment_date, '%b %Y'), DATE_FORMAT(payment_date, '%Y-%m')
      ORDER BY DATE_FORMAT(payment_date, '%Y-%m') ASC
    ");
    $stmtTrend->execute([$selected_year]);
} else {
    // Original last 6 months trend
    $stmtTrend = $pdo->query("
      SELECT DATE_FORMAT(payment_date, '%b %Y') as month, SUM(amount_paid) as revenue 
      FROM transactions 
      GROUP BY DATE_FORMAT(payment_date, '%b %Y'), DATE_FORMAT(payment_date, '%Y-%m')
      ORDER BY DATE_FORMAT(payment_date, '%Y-%m') ASC
      LIMIT 6
    ");
}
$trend_data_raw = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);
$trend_labels = [];
$trend_values = [];
foreach($trend_data_raw as $row) {
    $trend_labels[] = $row['month'];
    $trend_values[] = (float)$row['revenue'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BayanTap – Reports & Analytics</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css?v=4">
  <link rel="stylesheet" href="CSS/reports.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

  <?php $current_page = 'reports'; include 'navbar.php'; ?>

  <div class="page-header">
    <div class="page-title-section">
      <h1 class="page-title">Reports & Analytics</h1>
      <p class="page-subtitle">Track collections, revenue trends, and operational metrics.</p>
    </div>
  </div>

  <div class="main">
    
    <div class="controls-wrapper">
        <form method="GET" class="filter-form" style="display: flex; gap: 16px; align-items: center;">
            <div class="form-group-inline">
                <label style="font-weight: 700; color: var(--gray-700); font-size: 0.9rem;">Year:</label>
                <select name="year" class="filter-select" onchange="this.form.submit()">
                    <?php foreach($available_years as $y): ?>
                        <option value="<?= htmlspecialchars($y) ?>" <?= $y === $selected_year ? 'selected' : '' ?>>
                            <?= htmlspecialchars($y) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group-inline">
                <label style="font-weight: 700; color: var(--gray-700); font-size: 0.9rem;">Period:</label>
                <select name="month" class="filter-select large" onchange="this.form.submit()">
                    <option value="yearly" <?= $is_yearly ? 'selected' : '' ?>>Full Year Summary (Archive)</option>
                    <optgroup label="Monthly Details">
                        <?php foreach($available_months_for_year as $m): ?>
                            <option value="<?= htmlspecialchars($m) ?>" <?= $m === $selected_period ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
        </form>
        <button class="export-action-btn" onclick="window.print()">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2"></path><polyline points="12 15 12 21 16 19"></polyline><rect x="5" y="3" width="14" height="8"></rect></svg>
            Export PDF
        </button>
    </div>

    <!-- KPI Summary Grid -->
    <div class="summary-grid">
      <div class="summary-card">
        <div class="summary-label"><span>💰</span> Total Revenue</div>
        <div class="summary-value" style="color:var(--green);">₱<?= number_format($total_revenue, 2) ?></div>
        <div class="summary-sub"><?= $is_yearly ? "Total for year " . $selected_year : "Collected in " . htmlspecialchars($selected_period) ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-label"><span>📈</span> Collection Rate</div>
        <div class="summary-value"><?= $collection_rate ?>%</div>
        <div class="summary-sub"><?= $paid_bills ?> out of <?= $total_bills ?> bills paid</div>
      </div>
      <div class="summary-card">
        <div class="summary-label"><span>⚠️</span> Outstanding Balance</div>
        <div class="summary-value" style="color:var(--red);">₱<?= number_format($outstanding_amount, 2) ?></div>
        <div class="summary-sub"><?= $is_yearly ? "Yearly unpaid total" : "Unpaid dues pending" ?></div>
      </div>
      <div class="summary-card">
        <div class="summary-label"><span>🔗</span> Active Connections</div>
        <div class="summary-value"><?= number_format($active_connections) ?></div>
        <div class="summary-sub">Registered households</div>
      </div>
    </div>

    <!-- Charts Grid -->
    <div class="dashboard-grid">
      <!-- Revenue Trend Chart -->
      <div class="chart-card">
        <div class="chart-card-header">
            <h3>Revenue Trend</h3>
            <p>Collections across the last 6 months</p>
        </div>
        <div class="canvas-container">
            <canvas id="trendChart"></canvas>
        </div>
      </div>

      <!-- Payment Status Doughnut Chart -->
      <div class="chart-card">
        <div class="chart-card-header">
            <h3>Payment Status</h3>
            <p><?= htmlspecialchars($selected_month) ?> Billing Cycle</p>
        </div>
        <div class="canvas-container canvas-container-pie">
            <canvas id="statusChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Data Tables Grid -->
    <div class="dashboard-grid halves">
        <!-- Top Consumers -->
        <div class="table-section">
            <div class="table-section-header">
                <h3>💧 Top Water Consumers</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Resident</th>
                        <th style="width: 25%;">Household</th>
                        <th style="width: 15%; text-align: right;">Usage (m³)</th>
                        <th style="width: 20%; text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($top_consumers)): ?>
                        <tr><td colspan="4" style="text-align:center;">No data found.</td></tr>
                    <?php else: ?>
                        <?php foreach($top_consumers as $row): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($row['full_name']) ?></div>
                                <div style="font-size:0.72rem; color:var(--blue); font-weight:700;"><?= htmlspecialchars($row['household_id'] ?? 'N/A') ?></div>
                            </td>
                            <td style="color:var(--gray-500);"><?= htmlspecialchars($row['block_no'] . ' ' . $row['lot_no']) ?></td>
                            <td style="text-align: right; color:var(--blue); font-weight:700;"><?= htmlspecialchars($row['usage_m3']) ?> m³</td>
                            <td style="text-align: right;">₱<?= number_format($row['amount_due'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Delinquent Accounts -->
        <div class="table-section">
            <div class="table-section-header">
                <h3 style="color:var(--red);">⚠️ Delinquent Accounts</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Resident</th>
                        <th style="width: 25%;">Contact</th>
                        <th style="width: 25%; text-align: right;">Total Owed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($delinquent_accounts)): ?>
                        <tr><td colspan="3" style="text-align:center;">All accounts paid!</td></tr>
                    <?php else: ?>
                        <?php foreach($delinquent_accounts as $row): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($row['full_name']) ?></div>
                                <div style="font-size:0.75rem; color:var(--gray-500);">
                                    <span style="color:var(--blue); font-weight:700;"><?= htmlspecialchars($row['household_id'] ?? 'N/A') ?></span> · 
                                    <?= htmlspecialchars($row['block_no'] . ' ' . $row['lot_no']) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($row['contact_number'] ?: 'N/A') ?></td>
                            <td style="text-align: right; color:var(--red); font-weight:700;">₱<?= number_format($row['amount_due'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
  </div>

  <script>


    // Theme colors
    const colors = {
        blue: '#2563eb',
        blueLight: '#dbeafe',
        green: '#16a34a',
        greenLight: '#dcfce7',
        red: '#dc2626',
        amber: '#d97706',
        gray: '#f1f5f9'
    };

    // Initialize Revenue Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($trend_labels) ?>,
            datasets: [{
                label: 'Revenue (₱)',
                data: <?= json_encode($trend_values) ?>,
                backgroundColor: colors.blue,
                borderRadius: 4,
                barThickness: 24
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '₱ ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: colors.gray },
                    ticks: {
                        callback: function(value) { return '₱' + value; }
                    }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });

    // Initialize Status Doughnut Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Paid', 'Unpaid', 'Pending'],
            datasets: [{
                data: [
                    <?= $pie_data['paid'] ?>, 
                    <?= $pie_data['unpaid'] ?>, 
                    <?= $pie_data['pending'] ?>
                ],
                backgroundColor: [colors.green, colors.red, colors.amber],
                borderWidth: 0,
                cutout: '70%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, usePointStyle: true, font: { family: "'Plus Jakarta Sans', sans-serif" } }
                }
            }
        }
    });
  </script>
</body>
</html>
