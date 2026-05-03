<?php
require_once 'db_connect.php';

$receipt_no = $_GET['receipt_no'] ?? '';

if (!$receipt_no) {
    echo json_encode(['error' => 'Receipt number required']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT b.*, r.full_name, r.block_no, r.lot_no, s.setting_value as rate
    FROM billings b
    JOIN residents r ON b.resident_id = r.id
    JOIN settings s ON s.setting_key = 'current_rate'
    WHERE b.receipt_no = ?
");
$stmt->execute([$receipt_no]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($data) {
    echo json_encode([
        'resident_name' => $data['full_name'],
        'address' => $data['block_no'] . ' ' . $data['lot_no'],
        'usage' => $data['usage_m3'],
        'rate' => $data['rate'],
        'amount_due' => $data['amount_due']
    ]);
} else {
    echo json_encode(['error' => 'Billing not found']);
}
?>