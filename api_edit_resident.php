<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing resident ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE residents SET full_name = ?, block_no = ?, lot_no = ?, contact_number = ?, email = ?, monthly_rate = ?, status = ? WHERE id = ?");
    $stmt->execute([
        $data['full_name'] ?? '',
        $data['block_no'] ?? '',
        $data['lot_no'] ?? '',
        $data['contact_number'] ?? '',
        $data['email'] ?? '',
        (float)($data['monthly_rate'] ?? 0),
        $data['status'] ?? 'unpaid',
        $data['id']
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
