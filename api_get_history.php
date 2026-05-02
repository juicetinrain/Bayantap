<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$resident_id = $_GET['resident_id'] ?? null;

if (!$resident_id) {
    echo json_encode(['success' => false, 'error' => 'Missing resident ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM billings
        WHERE resident_id = ?
        ORDER BY STR_TO_DATE(CONCAT('01 ', billing_month), '%d %b %Y') DESC
    ");
    $stmt->execute([$resident_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'history' => $history]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
