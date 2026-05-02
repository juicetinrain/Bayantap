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

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$billing_id = $data['billing_id'] ?? null;
$email = $data['email'] ?? null;

if (!$billing_id || !$email) {
    echo json_encode(['success' => false, 'error' => 'Missing billing ID or email address']);
    exit;
}

try {
    $billing = null;
    if ($billing_id === 'test') {
        // Dummy data for test email
        $billing = [
            'full_name' => 'System Test Resident',
            'household_id' => 'BT-TEST',
            'billing_month' => date('M Y'),
            'usage_m3' => 10,
            'amount_due' => 100.00
        ];
    } else {
        // Fetch real billing details
        $stmt = $pdo->prepare("
            SELECT b.*, r.full_name, r.household_id, r.contact_number 
            FROM billings b 
            JOIN residents r ON b.resident_id = r.id 
            WHERE b.id = ?
        ");
        $stmt->execute([$billing_id]);
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$billing) {
        echo json_encode(['success' => false, 'error' => 'Billing record not found']);
        exit;
    }

    // Fetch SMTP settings from DB
    $smtp_host = get_setting('smtp_host', 'smtp.gmail.com');
    $smtp_port = get_setting('smtp_port', '587');
    $smtp_user = get_setting('smtp_user', '');
    $smtp_pass = get_setting('smtp_pass', '');
    $smtp_from_name = get_setting('smtp_from_name', 'BayanTap Water District');

    error_log("API SEND RECEIPT - User: '$smtp_user', Pass: '$smtp_pass', Host: '$smtp_host'\n", 3, __DIR__ . "/debug.log");

    if (empty(trim($smtp_user)) || empty(trim($smtp_pass))) {
        echo json_encode(['success' => false, 'error' => "SMTP credentials not configured in Settings. Found User: '$smtp_user'"]);
        exit;
    }

    // --- REAL EMAIL SENDING ---
    $mail = new PHPMailer(true);
    
    $mail->isSMTP();
    $mail->Host = $smtp_host;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_user;
    $mail->Password = $smtp_pass;
    $mail->SMTPSecure = ($smtp_port == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtp_port;

    // Sender & Recipient
    $mail->setFrom($smtp_user, $smtp_from_name);
    $mail->addAddress($email, $billing['full_name']);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Water Bill Receipt - ' . $billing['billing_month'] . ' (' . $billing['household_id'] . ')';
    
    $amount_fmt = "₱" . number_format($billing['amount_due'], 2);
    $usage_fmt = $billing['usage_m3'] . " m³";

    $mail->Body = "
        <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; padding: 30px;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <span style='font-size: 40px;'>💧</span>
                <h2 style='color: #2563eb; margin: 10px 0;'>BayanTap Water District</h2>
                <p style='color: #64748b;'>Official Billing Receipt</p>
            </div>
            
            <div style='background: #f8fafc; border-radius: 8px; padding: 20px; margin-bottom: 20px;'>
                <p style='margin: 5px 0;'><strong>Resident:</strong> " . htmlspecialchars($billing['full_name']) . "</p>
                <p style='margin: 5px 0;'><strong>Household ID:</strong> " . htmlspecialchars($billing['household_id']) . "</p>
                <p style='margin: 5px 0;'><strong>Contact No:</strong> " . htmlspecialchars($billing['contact_number'] ?? 'N/A') . "</p>
                <p style='margin: 5px 0;'><strong>Billing Month:</strong> " . htmlspecialchars($billing['billing_month']) . "</p>
            </div>

            <table style='width: 100%; border-collapse: collapse;'>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; color: #64748b;'>Water Usage</td>
                    <td style='padding: 10px 0; text-align: right; font-weight: 700;'>" . $usage_fmt . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px 0; color: #64748b;'>Total Amount Paid</td>
                    <td style='padding: 10px 0; text-align: right; font-weight: 700; font-size: 1.2rem; color: #16a34a;'>" . $amount_fmt . "</td>
                </tr>
            </table>

            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #f1f5f9; text-align: center; color: #94a3b8; font-size: 0.85rem;'>
                <p>This is an automated receipt from the BayanTap Treasurer Portal.</p>
                <p>&copy; 2026 Marcos Village Water District</p>
            </div>
        </div>
    ";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Receipt successfully sent to ' . $email]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Email failed: ' . $mail->ErrorInfo]);
}
