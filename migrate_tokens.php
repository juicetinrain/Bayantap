<?php
require_once 'db_connect.php';

try {
    $stmt = $pdo->query("SELECT id FROM residents WHERE access_token IS NULL");
    $residents = $stmt->fetchAll();

    foreach ($residents as $res) {
        $token = bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE residents SET access_token = ? WHERE id = ?")->execute([$token, $res['id']]);
        echo "Updated resident ID {$res['id']} with token: $token\n";
    }
    echo "Migration complete.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>