<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$billing_id = $data['billing_id'] ?? null;
$remarks = $data['remarks'] ?? '';

if (!$billing_id) {
    echo json_encode(['success' => false, 'error' => 'Missing billing ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE billings SET remarks = ? WHERE id = ?");
    $success = $stmt->execute([$remarks, $billing_id]);

    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
