<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();

    // Generate next household_id (Serial Number)
    $maxStmt = $pdo->query("SELECT household_id FROM residents WHERE household_id LIKE 'BT-%' ORDER BY household_id DESC LIMIT 1");
    $lastId = $maxStmt->fetchColumn();
    $nextNum = 1;
    if ($lastId) {
        $nextNum = (int)str_replace('BT-', '', $lastId) + 1;
    }
    $household_id = 'BT-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

    // Insert resident
    $access_token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO residents (household_id, block_no, lot_no, full_name, initial_meter, monthly_rate, contact_number, email, status, access_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $household_id,
        $data['block_no'] ?? '',
        $data['lot_no'] ?? '',
        $data['full_name'] ?? '',
        $data['initial_meter'] ?? 0,
        $data['monthly_rate'] ?? 0,
        $data['contact_number'] ?? '',
        $data['email'] ?? '',
        $data['status'] ?? 'unpaid',
        $access_token
    ]);
    $resident_id = $pdo->lastInsertId();

    // Insert initial billing record so they show up on the dashboard
    $stmt2 = $pdo->prepare("INSERT INTO billings (resident_id, billing_month, previous_reading, current_reading, usage_m3, amount_due, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt2->execute([
        $resident_id,
        date('M Y'), // Current month
        $data['initial_meter'] ?? 0,
        $data['initial_meter'] ?? 0,
        0, // initially 0 usage
        0, // initially 0 amount due
        ($data['initial_meter'] ?? 0) > 0 ? 'pending' : 'started'
    ]);
    $init_bill_id = $pdo->lastInsertId();
    $init_receipt_no = 'MV-' . date('Y') . '-' . str_pad($init_bill_id, 4, '0', STR_PAD_LEFT);
    $pdo->query("UPDATE billings SET receipt_no = '$init_receipt_no' WHERE id = $init_bill_id");

    // AUTO-GENERATE NEXT MONTH FOR NEW RECORDS
    if (true) { // Always generate next month for new resident starting out 

        $current_month_str = date('M Y');
        $dateObj = DateTime::createFromFormat('M Y', $current_month_str);
        if ($dateObj) {
            $dateObj->modify('+1 month');
            $next_month_str = $dateObj->format('M Y');

            // Create new record for next month
            $genStmt = $pdo->prepare("INSERT INTO billings (resident_id, billing_month, previous_reading, current_reading, usage_m3, amount_due, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $genStmt->execute([
                $resident_id,
                $next_month_str,
                (float)($data['initial_meter'] ?? 0), // current meter becomes previous
                (float)($data['initial_meter'] ?? 0), // initial current reading
                0,
                0,
                'pending'
            ]);
            $next_bill_id = $pdo->lastInsertId();
            $next_receipt_no = 'MV-' . date('Y', strtotime($next_month_str)) . '-' . str_pad($next_bill_id, 4, '0', STR_PAD_LEFT);
            $pdo->query("UPDATE billings SET receipt_no = '$next_receipt_no' WHERE id = $next_bill_id");
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}