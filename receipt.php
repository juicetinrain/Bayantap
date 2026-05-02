<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt History – BayanTap Treasurer Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="CSS/receipt.css">
</head>
<body>
 
<!-- NAV -->
<nav>
  <div class="nav-brand">
    <div class="nav-logo">💧</div>
    <div class="nav-text">
      <h1>BayanTap</h1>
      <p>Marcos Village Water District</p>
    </div>
  </div>
  <div class="nav-right">
    <div class="portal-info">
      <div class="label">Treasurer Portal</div>
      <div class="month">April 2026</div>
    </div>
    <div class="nav-badge">🏅</div>
    <button class="btn-logout">Log Out</button>
  </div>
</nav>
 
<!-- HERO -->
<div class="hero">
  <div class="hero-content">
    <h1>Receipt History</h1>
    <p>View and manage all payment receipts issued by the water district</p>
  </div>
</div>
 
<!-- MAIN -->
<div class="main">
 
  <!-- Toolbar -->
  <div class="toolbar">
    <div class="search-wrap">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" placeholder="Search by receipt number, name, or household…">
    </div>
    <button class="filter-btn">All Status ▾</button>
    <button class="filter-btn">All Months ▾</button>
    <button class="filter-btn">Export ▾</button>
  </div>
 
  <!-- Receipt History Table Card -->
  <div class="table-card">
    <div class="table-card-header">
      <h2>Receipt History</h2>
      <p>All issued receipts · Marcos Village</p>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Receipt No</th>
            <th>Date</th>
            <th>Household</th>
            <th>Name</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><span class="receipt-chip">MV-2026-0156</span></td>
            <td><div class="receipt-date">January 20, 2026<span class="time">2:45 PM</span></div></td>
            <td><div class="hh-cell"><span class="hh-id">Blk 15 Lot 2</span></div></td>
            <td><span class="resident-name">Janella Ashley Gomez</span></td>
            <td><span class="amount-cell">₱800.00</span></td>
            <td><span class="status-chip chip-paid">PAID</span></td>
            <td><button class="action-btn" title="View">⋯</button></td>
          </tr>
          <tr>
            <td><span class="receipt-chip">MV-2026-0155</span></td>
            <td><div class="receipt-date">January 19, 2026<span class="time">10:30 AM</span></div></td>
            <td><div class="hh-cell"><span class="hh-id">Blk 9 Lot 2</span></div></td>
            <td><span class="resident-name">Justin Basco</span></td>
            <td><span class="amount-cell">₱575.00</span></td>
            <td><span class="status-chip chip-paid">PAID</span></td>
            <td><button class="action-btn" title="View">⋯</button></td>
          </tr>
          <tr>
            <td><span class="receipt-chip">MV-2026-0154</span></td>
            <td><div class="receipt-date">January 15, 2026<span class="time">1:20 PM</span></div></td>
            <td><div class="hh-cell"><span class="hh-id">Blk 15 Lot 7</span></div></td>
            <td><span class="resident-name">Ronald Barangay</span></td>
            <td><span class="amount-cell">₱650.00</span></td>
            <td><span class="status-chip chip-paid">PAID</span></td>
            <td><button class="action-btn" title="View">⋯</button></td>
          </tr>
          <tr>
            <td><span class="receipt-chip">MV-2026-0153</span></td>
            <td><div class="receipt-date">January 15, 2026<span class="time">11:15 AM</span></div></td>
            <td><div class="hh-cell"><span class="hh-id">Blk 4 Lot 1</span></div></td>
            <td><span class="resident-name">Aliyah Macapagal</span></td>
            <td><span class="amount-cell">₱320.00</span></td>
            <td><span class="status-chip chip-paid">PAID</span></td>
            <td><button class="action-btn" title="View">⋯</button></td>
          </tr>
          <tr>
            <td><span class="receipt-chip">MV-2026-0152</span></td>
            <td><div class="receipt-date">January 11, 2026<span class="time">3:50 PM</span></div></td>
            <td><div class="hh-cell"><span class="hh-id">Blk 08 Lot 3</span></div></td>
            <td><span class="resident-name">James Tanglao</span></td>
            <td><span class="amount-cell">₱885.00</span></td>
            <td><span class="status-chip chip-paid">PAID</span></td>
            <td><button class="action-btn" title="View">⋯</button></td>
          </tr>
          <tr>
            <td><span class="receipt-chip">MV-2025-0151</span></td>
            <td><div class="receipt-date">December 28, 2025<span class="time">9:45 AM</span></div></td>
            <td><div class="hh-cell"><span class="hh-id">Blk 5 Lot 4</span></div></td>
            <td><span class="resident-name">Ian Reyes</span></td>
            <td><span class="amount-cell">₱745.00</span></td>
            <td><span class="status-chip chip-paid">PAID</span></td>
            <td><button class="action-btn" title="View">⋯</button></td>
          </tr>
          <tr>
            <td><span class="receipt-chip">MV-2025-0150</span></td>
            <td><div class="receipt-date">December 22, 2025<span class="time">2:15 PM</span></div></td>
            <td><div class="hh-cell"><span class="hh-id">Blk 12 Lot 5</span></div></td>
            <td><span class="resident-name">Rafael Laquian</span></td>
            <td><span class="amount-cell">₱520.00</span></td>
            <td><span class="status-chip chip-paid">PAID</span></td>
            <td><button class="action-btn" title="View">⋯</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
 
</div>

</body>
</html>