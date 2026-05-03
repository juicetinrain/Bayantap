<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receipt_no = $_POST['receipt_no'];
    $payment_amount = (float)$_POST['payment_amount'];

    // Get resident_id from billings
    $stmt = $pdo->prepare("SELECT resident_id, amount_due FROM billings WHERE receipt_no = ?");
    $stmt->execute([$receipt_no]);
    $billing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$billing) {
        echo json_encode(['error' => 'Invalid receipt number']);
        exit;
    }
    $resident_id = $billing['resident_id'];

    // Insert transaction
    $stmt = $pdo->prepare("INSERT INTO transactions (receipt_no, resident_id, amount_paid, treasurer_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$receipt_no, $resident_id, $payment_amount, $_SESSION['user_id']]);

    // Update billing if fully paid
    if ($payment_amount >= $billing['amount_due']) {
        $pdo->prepare("UPDATE billings SET status = 'paid', paid_date = NOW() WHERE receipt_no = ?")->execute([$receipt_no]);
    }

    // Handle image if uploaded
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $target_file = $target_dir . time() . '_' . basename($_FILES["receipt_image"]["name"]);
        if (move_uploaded_file($_FILES["receipt_image"]["tmp_name"], $target_file)) {
            // Optionally store path in db, but for now just save
        }
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>