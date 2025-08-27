<?php
session_start();
include('api/db.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include('includes/PHPMailer/Exception.php');
include('includes/PHPMailer/PHPMailer.php');
include('includes/PHPMailer/SMTP.php');
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        $token = bin2hex(random_bytes(32));
        $expires = date("Y-m-d H:i:s", time() + 3600); 

        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
        $stmt->bind_param("sss", $token, $expires, $email);
        $stmt->execute();

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'mail.califorsale.org';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'alerts@califorsale.org';
            $mail->Password   = 'Real_estate123$';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('alerts@califorsale.org', 'Real Estate Alerts');
            $mail->addAddress($email, $user['first_name']);

            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password';

            $resetLink = "https://califorsale.org/reset_password.php?token=$token";

            $mail->Body = "
                Hi {$user['first_name']},<br><br>
                Click the link below to reset your password:<br>
                <a href='$resetLink'>$resetLink</a><br><br>
                Thanks,<br>
                Real Estate Team
            ";

            $mail->send();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log("PHPMailer error: " . $mail->ErrorInfo);
            echo json_encode(['success' => false, 'message' => 'Failed to send email.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No account found for this email.']);
    }
    exit();
}
?>