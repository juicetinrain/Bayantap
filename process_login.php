<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
$user = trim($data['username'] ?? '');
$pass = $data['password'] ?? '';

if (empty($user) || empty($pass)) {
    echo json_encode(['success' => false, 'message' => 'Please provide both username and password.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, password_hash, role, display_name FROM users WHERE username = :username");
$stmt->execute(['username' => $user]);
$account = $stmt->fetch();

if ($account && password_verify($pass, $account['password_hash'])) {
    $_SESSION['user_id'] = $account['id'];
    $_SESSION['username'] = $account['username'];
    $_SESSION['display_name'] = $account['display_name'] ?? $account['username'];
    $_SESSION['role'] = $account['role'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Incorrect username or password. Please check your credentials.']);
}
?>
