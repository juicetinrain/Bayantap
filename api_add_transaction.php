<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = $_POST;
$receipt_no = trim($data['receipt_no'] ?? '');
$amount_paid = (float)($data['amount_paid'] ?? 0);

if (empty($receipt_no)) {
    echo json_encode(['success' => false, 'error' => 'Receipt number is required']);
    exit;
}

if ($amount_paid <= 0) {
    echo json_encode(['success' => false, 'error' => 'Amount paid must be greater than zero']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Look up the billing record
    $stmt = $pdo->prepare("SELECT b.id, b.resident_id, b.amount_due, b.status FROM billings b WHERE b.receipt_no = ? LIMIT 1");
    $stmt->execute([$receipt_no]);
    $billing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$billing) {
        throw new Exception('Billing record not found for this receipt number.');
    }

    // 2. Prevent duplicate payments only if billing is already fully paid
    if ($billing['status'] === 'paid') {
        throw new Exception('This billing is already fully paid.');
    }

    // 3. Handle proof of payment image upload
    $proof_img_name = null;
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
        $proof_img_name = 'payment_' . $billing['id'] . '_' . time() . '.' . $ext;
        $upload_dir = 'uploads/payments/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $upload_path = $upload_dir . $proof_img_name;

        if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_path)) {
            throw new Exception('Failed to upload proof of payment.');
        }
    }

    // 4. Insert transaction record
    $txStmt = $pdo->prepare("INSERT INTO transactions (receipt_no, resident_id, amount_paid, treasurer_id, payment_proof) VALUES (?, ?, ?, ?, ?)");
    $txStmt->execute([
        $receipt_no,
        $billing['resident_id'],
        $amount_paid,
        $_SESSION['user_id'],
        $proof_img_name
    ]);

    // 5. Determine new status
    $amount_due = (float)$billing['amount_due'];
    $new_status = 'paid';
    
    // Calculate total paid so far for this receipt
    $sumStmt = $pdo->prepare("SELECT SUM(amount_paid) as total_paid FROM transactions WHERE receipt_no = ?");
    $sumStmt->execute([$receipt_no]);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
    $total_paid = (float)($sumRow['total_paid'] ?? 0);

    if ($total_paid < $amount_due) {
        $new_status = 'partial';
    }

    // 6. Update billing status
    $paid_date = date('Y-m-d H:i:s');
    $updateStmt = $pdo->prepare("UPDATE billings SET status = ?, paid_date = ? WHERE id = ?");
    $updateStmt->execute([$new_status, $paid_date, $billing['id']]);

    // 7. Update resident status to match the latest billing status
    $resUpdate = $pdo->prepare("UPDATE residents SET status = ? WHERE id = ?");
    $resUpdate->execute([$new_status, $billing['resident_id']]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Transaction recorded successfully.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}