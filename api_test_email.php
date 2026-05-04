<?php
session_start();
require_once 'db_connect.php';
header('Content-Type: application/json');

// PHPMailer Manual Loading
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

try {
    // Fetch SMTP settings from DB
    $smtp_host = get_setting('smtp_host', 'smtp.gmail.com');
    $smtp_port = get_setting('smtp_port', '587');
    $smtp_user = get_setting('smtp_user', '');
    $smtp_pass = get_setting('smtp_pass', '');
    $smtp_from_name = get_setting('smtp_from_name', 'BayanTap Water District');

    if (empty(trim($smtp_user)) || empty(trim($smtp_pass))) {
        echo json_encode(['success' => false, 'message' => 'SMTP credentials not configured in Settings.']);
        exit;
    }

    // Create PHPMailer instance
    $mail = new PHPMailer(true);
    
    $mail->isSMTP();
    $mail->Host = $smtp_host;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_user;
    $mail->Password = $smtp_pass;
    $mail->SMTPSecure = ($smtp_port == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtp_port;
    $mail->SMTPDebug = 0; // Set to 2 for detailed debug output
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // Sender & Recipient
    $mail->setFrom($smtp_user, $smtp_from_name);
    $mail->addAddress($email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'BayanTap SMTP Test Email';
    
    $mail->Body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <span style='font-size: 40px;'>💧</span>
                <h2 style='color: #2563eb; margin: 10px 0;'>BayanTap Water District</h2>
                <p style='color: #64748b;'>SMTP Configuration Test</p>
            </div>
            
            <div style='background: #f0fdf4; border-left: 4px solid #16a34a; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
                <p style='margin: 0; color: #166534;'><strong>✓ Success!</strong></p>
                <p style='margin: 10px 0 0; color: #4b5563;'>Your SMTP configuration is working correctly. This is a test email from BayanTap Water District.</p>
            </div>

            <div style='background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                <p style='margin: 5px 0; color: #475569;'><strong>Email Sent From:</strong> " . htmlspecialchars($smtp_from_name) . " (" . htmlspecialchars($smtp_user) . ")</p>
                <p style='margin: 5px 0; color: #475569;'><strong>Sent At:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p style='margin: 5px 0; color: #475569;'><strong>Server:</strong> " . htmlspecialchars($smtp_host) . ":" . htmlspecialchars($smtp_port) . "</p>
            </div>

            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; text-align: center; color: #94a3b8; font-size: 0.85rem;'>
                <p>&copy; 2026 Marcos Village Water District - BayanTap Portal</p>
            </div>
        </div>
    ";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Test email sent successfully to ' . $email]);

} catch (Exception $e) {
    $error = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
    echo json_encode(['success' => false, 'message' => 'Email failed: ' . $error]);
}
