<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Handle Multipart/Form-Data
$data = $_POST;

try {
    $pdo->beginTransaction();

    // 1. Handle Current Image Upload
    $current_img_name = null;
    if (isset($_FILES['current_reading_image']) && $_FILES['current_reading_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['current_reading_image']['name'], PATHINFO_EXTENSION);
        $current_img_name = 'meter_' . $data['billing_id'] . '_' . time() . '.' . $ext;
        $upload_dir = 'uploads/meters/';
        
        // Auto-create directory if it doesn't exist (common when migrating to a new PC)
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $upload_path = $upload_dir . $current_img_name;
        
        if (!move_uploaded_file($_FILES['current_reading_image']['tmp_name'], $upload_path)) {
            throw new Exception("Failed to upload current reading image.");
        }
    }

    // Update resident name
    $stmt = $pdo->prepare("UPDATE residents SET full_name = ? WHERE id = ?");
    $stmt->execute([
        $data['full_name'] ?? '',
        $data['resident_id']
    ]);

    // Always generate a receipt_no so it can be looked up from the transactions page
    $receipt_no_gen = 'MV-' . date('Y') . '-' . str_pad($data['billing_id'], 4, '0', STR_PAD_LEFT);

    // Build update query — status is NO LONGER editable from billings page
    // We preserve the existing status; only the transactions page can set it to 'paid'
    $sql = "UPDATE billings SET previous_reading = ?, current_reading = ?, usage_m3 = ?, amount_due = ?, receipt_no = COALESCE(receipt_no, ?)";
    $params = [
        (float)($data['previous_reading'] ?? 0),
        (float)($data['current_reading'] ?? 0),
        (float)($data['usage_m3'] ?? 0),
        (float)($data['amount_due'] ?? 0),
        $receipt_no_gen
    ];

    if ($current_img_name) {
        $sql .= ", current_reading_image = ?";
        $params[] = $current_img_name;
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $data['billing_id'];

    $stmt2 = $pdo->prepare($sql);
    $stmt2->execute($params);

    // AUTO-GENERATE NEXT MONTH billing record if readings were entered
    $usage = (float)($data['usage_m3'] ?? 0);
    if ($usage > 0) {
        $mStmt = $pdo->prepare("SELECT billing_month, current_reading_image, status FROM billings WHERE id = ?");
        $mStmt->execute([$data['billing_id']]);
        $row = $mStmt->fetch(PDO::FETCH_ASSOC);
        $current_month_str = $row['billing_month'] ?? null;
        $final_current_img = $current_img_name ?: $row['current_reading_image'];

        if ($current_month_str) {
            $dateObj = DateTime::createFromFormat('M Y', $current_month_str);
            if ($dateObj) {
                $dateObj->modify('+1 month');
                $next_month_str = $dateObj->format('M Y');

                $checkStmt = $pdo->prepare("SELECT id FROM billings WHERE resident_id = ? AND billing_month = ?");
                $checkStmt->execute([$data['resident_id'], $next_month_str]);
                
                if (!$checkStmt->fetch()) {
                    $genStmt = $pdo->prepare("INSERT INTO billings (resident_id, billing_month, previous_reading, current_reading, usage_m3, amount_due, status, previous_reading_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $genStmt->execute([
                        $data['resident_id'],
                        $next_month_str,
                        (float)$data['current_reading'],
                        (float)$data['current_reading'],
                        0,
                        0,
                        'pending',
                        $final_current_img // Carry over current image to next month's previous
                    ]);
                    $next_bill_id = $pdo->lastInsertId();
                    $next_receipt_no = 'MV-' . date('Y', strtotime($next_month_str)) . '-' . str_pad($next_bill_id, 4, '0', STR_PAD_LEFT);
                    $pdo->query("UPDATE billings SET receipt_no = '$next_receipt_no' WHERE id = $next_bill_id");
                } else {
                    // Update existing next month's previous reading and image
                    $updNext = $pdo->prepare("UPDATE billings SET previous_reading = ?, previous_reading_image = ? WHERE resident_id = ? AND billing_month = ? AND (status = 'pending' OR status = 'unpaid')");
                    $updNext->execute([
                        (float)$data['current_reading'],
                        $final_current_img,
                        $data['resident_id'],
                        $next_month_str
                    ]);
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}