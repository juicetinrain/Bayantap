<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Find the latest resident
    $stmt = $pdo->query("SELECT id, full_name, initial_meter FROM residents ORDER BY id DESC LIMIT 1");
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resident) {
        throw new Exception("No residents found. Please add one first.");
    }

    $resident_id = $resident['id'];
    $meter = (float)($resident['initial_meter'] ?? 0);

    // 2. Clear existing billing for this resident to avoid confusion
    $pdo->prepare("DELETE FROM billings WHERE resident_id = ?")->execute([$resident_id]);

    // 3. Create March 2026 (Past month) as OVERDUE
    $stmtMar = $pdo->prepare("INSERT INTO billings (resident_id, billing_month, previous_reading, current_reading, usage_m3, amount_due, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtMar->execute([$resident_id, 'Mar 2026', $meter, $meter, 0, 0, 'unpaid']);

    // 4. Create April 2026 (Current month) as PENDING
    $stmtApr = $pdo->prepare("INSERT INTO billings (resident_id, billing_month, previous_reading, current_reading, usage_m3, amount_due, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtApr->execute([$resident_id, 'Apr 2026', $meter, $meter, 0, 0, 'pending']);

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => "Successfully simulated a dual-month scenario for " . $resident['full_name'] . ".",
        'details' => [
            'March 2026' => 'OVERDUE (Red)',
            'April 2026' => 'PENDING (Amber)'
        ],
        'next_step' => "Refresh your Dashboard or Billings page to see both records side-by-side!"
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}