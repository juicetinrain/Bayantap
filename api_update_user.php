<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'message' => 'No data received.']);
    exit;
}

$userId = isset($data['id']) ? (int)$data['id'] : 0;
$username = trim($data['username'] ?? '');
$newPassword = $data['new_password'] ?? '';

if ($userId <= 0 || empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Invalid user data.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $checkStmt->execute([$username, $userId]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username is already taken.']);
        exit;
    }

    if (!empty($newPassword)) {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = ?");
        $updateStmt->execute([$username, $passwordHash, $userId]);
    } else {
        $updateStmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
        $updateStmt->execute([$username, $userId]);
    }

    if ($userId === $_SESSION['user_id']) {
        $_SESSION['username'] = $username;
    }

    echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
