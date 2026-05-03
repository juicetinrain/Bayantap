<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$receipt_no = trim($_GET['receipt_no'] ?? '');

if (empty($receipt_no)) {
    echo json_encode(['success' => false, 'error' => 'Receipt number is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT b.id AS billing_id, b.resident_id, b.billing_month, b.amount_due, b.status, b.receipt_no,
               b.usage_m3, b.previous_reading, b.current_reading,
               r.household_id, r.full_name, r.block_no, r.lot_no, r.contact_number, r.email, r.monthly_rate as rate
        FROM billings b
        JOIN residents r ON b.resident_id = r.id
        WHERE b.receipt_no = ?
        LIMIT 1
    ");
    $stmt->execute([$receipt_no]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Receipt number not found']);
        exit;
    }

    // Check if already paid
    $already_paid = ($row['status'] === 'paid');

    // Check if a transaction already exists for this receipt
    $txCheck = $pdo->prepare("SELECT id FROM transactions WHERE receipt_no = ? LIMIT 1");
    $txCheck->execute([$receipt_no]);
    $tx_exists = (bool)$txCheck->fetch();

    echo json_encode([
        'success' => true,
        'data' => [
            'billing_id' => $row['billing_id'],
            'resident_id' => $row['resident_id'],
            'receipt_no' => $row['receipt_no'],
            'household_id' => $row['household_id'],
            'full_name' => $row['full_name'],
            'block_no' => $row['block_no'],
            'lot_no' => $row['lot_no'],
            'billing_month' => $row['billing_month'],
            'amount_due' => number_format((float)$row['amount_due'], 2, '.', ''),
            'usage_m3' => $row['usage_m3'],
            'rate' => number_format((float)$row['rate'], 2, '.', ''),
            'previous_reading' => $row['previous_reading'],
            'current_reading' => $row['current_reading'],
            'status' => $row['status'],
            'contact_number' => $row['contact_number'],
            'email' => $row['email'],
            'already_paid' => $already_paid,
            'transaction_exists' => $tx_exists
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
