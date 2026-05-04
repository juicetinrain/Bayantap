<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$display_name = trim($_POST['display_name'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$current_password = $_POST['current_password'] ?? '';

if (empty($display_name)) {
    echo json_encode(['success' => false, 'error' => 'Display name cannot be empty.']);
    exit;
}

if (empty($current_password)) {
    echo json_encode(['success' => false, 'error' => 'Current password is required.']);
    exit;
}

try {
    // 1. Verify current password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($current_password, $user['password_hash'])) {
        echo json_encode(['success' => false, 'error' => 'Incorrect current password.']);
        exit;
    }

    // 2. Check if new display name is already taken by someone else
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE display_name = ? AND id != ?");
    $checkStmt->execute([$display_name, $user_id]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Display name is already taken.']);
        exit;
    }

    // 3. Update Profile
    if (!empty($new_password)) {
        // Update both display name and password
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE users SET display_name = ?, password_hash = ? WHERE id = ?");
        $updateStmt->execute([$display_name, $new_hash, $user_id]);
    } else {
        // Update display name only
        $updateStmt = $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?");
        $updateStmt->execute([$display_name, $user_id]);
    }

    // 4. Update Session
    $_SESSION['display_name'] = $display_name;

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
