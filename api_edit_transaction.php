<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $tx_id = $_POST['tx_id'] ?? '';
    $amount_paid = (float)($_POST['amount_paid'] ?? 0);
    $existing_proof = $_POST['existing_proof'] ?? '';

    if (empty($tx_id)) {
        throw new Exception('Transaction ID is required.');
    }

    $pdo->beginTransaction();

    // 1. Verify transaction exists and get receipt_no
    $txStmt = $pdo->prepare("SELECT receipt_no FROM transactions WHERE id = ?");
    $txStmt->execute([$tx_id]);
    $tx = $txStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tx) {
        throw new Exception('Transaction not found.');
    }
    $receipt_no = $tx['receipt_no'];

    // 2. Fetch billing details
    $billingStmt = $pdo->prepare("SELECT id, resident_id, amount_due FROM billings WHERE receipt_no = ?");
    $billingStmt->execute([$receipt_no]);
    $billing = $billingStmt->fetch(PDO::FETCH_ASSOC);

    if (!$billing) {
        throw new Exception('Associated billing record not found.');
    }

    // 3. Process File Upload (if any)
    $proof_img_name = $existing_proof;
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/payments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $tmpName = $_FILES['payment_proof']['tmp_name'];
        $fileName = basename($_FILES['payment_proof']['name']);
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            throw new Exception("Invalid image type.");
        }

        $newName = uniqid('proof_') . '.' . $ext;
        $destPath = $uploadDir . $newName;

        if (move_uploaded_file($tmpName, $destPath)) {
            $proof_img_name = $newName;
            // Note: intentionally not deleting old file to keep backup/history, 
            // but it will be overwritten in the DB.
        } else {
            throw new Exception("Failed to save uploaded image.");
        }
    }

    // 4. Update the transaction
    $updateTx = $pdo->prepare("UPDATE transactions SET amount_paid = ?, payment_proof = ? WHERE id = ?");
    $updateTx->execute([$amount_paid, $proof_img_name, $tx_id]);

    // 5. Recalculate status
    $amount_due = (float)$billing['amount_due'];
    
    // Sum total paid so far for this receipt
    $sumStmt = $pdo->prepare("SELECT SUM(amount_paid) as total_paid FROM transactions WHERE receipt_no = ?");
    $sumStmt->execute([$receipt_no]);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
    $total_paid = (float)($sumRow['total_paid'] ?? 0);

    $new_status = 'unpaid';
    if ($total_paid >= $amount_due) {
        $new_status = 'paid';
    } else if ($total_paid > 0) {
        $new_status = 'partial';
    }

    // 6. Update billing status
    $updateStmt = $pdo->prepare("UPDATE billings SET status = ? WHERE id = ?");
    $updateStmt->execute([$new_status, $billing['id']]);

    // 7. Update resident status to match the latest billing status
    $resUpdate = $pdo->prepare("UPDATE residents SET status = ? WHERE id = ?");
    $resUpdate->execute([$new_status, $billing['resident_id']]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Transaction updated successfully.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
