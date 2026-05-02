<?php
require_once 'db_connect.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    die("<h1>Invalid Access</h1><p>No access token provided.</p>");
}

// Fetch resident info
$stmt = $pdo->prepare("SELECT * FROM residents WHERE access_token = ?");
$stmt->execute([$token]);
$resident = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resident) {
    die("<h1>Access Denied</h1><p>Invalid or expired access token.</p>");
}

$resident_id = $resident['id'];

// Fetch billing history
$stmt2 = $pdo->prepare("SELECT * FROM billings WHERE resident_id = ? ORDER BY id DESC");
$stmt2->execute([$resident_id]);
$billings = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Calculate total unpaid balance
$unpaid_balance = 0;
foreach ($billings as $b) {
    if ($b['status'] !== 'paid') {
        $unpaid_balance += $b['amount_due'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Portal – BayanTap</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/portal.css">
</head>

<body>
    <div class="container">
        <header>
            <div class="logo">💧 Bayan<span>Tap</span></div>
            <p>Marcos Village Water District</p>
        </header>

        <div class="card balance-card">
            <div class="b-label">Total Outstanding Balance</div>
            <div class="b-amount">₱<?= number_format($unpaid_balance, 2) ?></div>
            <div class="b-status"><?= $unpaid_balance > 0 ? 'Payment Required' : 'Account Current' ?></div>
        </div>

        <div class="card">
            <div class="profile-header">
                <div class="avatar"><?= mb_substr($resident['full_name'], 0, 1) ?></div>
                <div class="p-info">
                    <h1><?= htmlspecialchars($resident['full_name']) ?></h1>
                    <p><?= htmlspecialchars($resident['block_no'] . ' ' . $resident['lot_no']) ?> · Resident Account</p>
                </div>
            </div>
            <div style="font-size: 0.875rem; color: var(--gray-500); line-height: 1.6;">
                <div style="display:flex; justify-content:space-between; margin-bottom: 8px;">
                    <span>Water Rate (per m³):</span>
                    <span style="color: var(--gray-900); font-weight: 600;">₱33.70</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Member Since:</span>
                    <span
                        style="color: var(--gray-900); font-weight: 600;"><?= date('M Y', strtotime($resident['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <div class="section-title">
            <span>Billing History</span>
            <span style="font-size: 0.75rem; font-weight: 500; opacity: 0.7;"><?= count($billings) ?> records</span>
        </div>

        <div class="portal-controls">
            <div class="p-search-wrap">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                </svg>
                <input type="text" id="pSearch" class="p-search-input" placeholder="Search bills..." oninput="filterPortalHistory()">
            </div>
            <select id="pMonthFilter" class="p-filter-select" onchange="filterPortalHistory()">
                <option value="">All</option>
                <?php 
                $unique_months = array_unique(array_column($billings, 'billing_month'));
                foreach ($unique_months as $m): 
                ?>
                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="card" style="padding: 0; overflow: hidden;">
            <div id="portalHistoryList">
            <?php foreach ($billings as $b): ?>
                <div class="bill-item" 
                     data-month="<?= htmlspecialchars($b['billing_month']) ?>"
                     data-status="<?= htmlspecialchars($b['status']) ?>"
                     data-usage="<?= $b['usage_m3'] ?>"
                     data-amount="<?= $b['amount_due'] ?>">
                    <div class="bill-info">
                        <h4><?= htmlspecialchars($b['billing_month']) ?></h4>
                        <p>Usage: <?= $b['usage_m3'] ?> m³ (<?= $b['previous_reading'] ?> → <?= $b['current_reading'] ?>)
                        </p>
                    </div>
                    <div class="bill-meta">
                        <div class="bill-amount">₱<?= number_format($b['amount_due'], 2) ?></div>
                        <span class="status-badge status-<?= $b['status'] ?>"><?= strtoupper($b['status']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <div id="noResults" class="no-results">
                <div style="font-size: 32px; margin-bottom: 12px;">🔍</div>
                <p>No billing records match your search.</p>
                <button onclick="resetPortalFilter()" style="margin-top: 12px; background: none; border: none; color: var(--blue); font-weight: 700; cursor: pointer;">Show all records</button>
            </div>
        </div>

        <footer>
            <p>© 2026 BayanTap Marcos Village Water District.</p>
            <p>This is a secure lookup page. Do not share your QR code or link with others.</p>
        </footer>
    </div>

    <script>
        function filterPortalHistory() {
            const query = document.getElementById('pSearch').value.toLowerCase().trim();
            const monthFilter = document.getElementById('pMonthFilter').value;
            const items = document.querySelectorAll('.bill-item');
            const noResults = document.getElementById('noResults');
            let visibleCount = 0;

            items.forEach(item => {
                const month = item.getAttribute('data-month');
                const status = item.getAttribute('data-status').toLowerCase();
                const usage = item.getAttribute('data-usage');
                const amount = item.getAttribute('data-amount');
                
                const matchesSearch = !query || (
                    month.toLowerCase().includes(query) || 
                    status.includes(query) ||
                    usage.includes(query) ||
                    amount.includes(query)
                );
                
                const matchesMonth = !monthFilter || month === monthFilter;
                
                if (matchesSearch && matchesMonth) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            noResults.style.display = visibleCount === 0 ? 'block' : 'none';
        }

        function resetPortalFilter() {
            document.getElementById('pSearch').value = '';
            document.getElementById('pMonthFilter').value = '';
            filterPortalHistory();
        }
    </script>
</body>

</html>