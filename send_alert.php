<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

include('includes/PHPMailer/Exception.php');
include('includes/PHPMailer/PHPMailer.php');
include('includes/PHPMailer/SMTP.php');

$response = ['success' => false];
$log = "";

if (!isset($_SESSION['user_id'])) {
    $response['message'] = "User not logged in.";
    echo json_encode($response);
    exit;
}

include 'api/db.php';

$user_id = $_SESSION['user_id'];
$property_id = $_POST['listing_id'] ?? '';

if (empty($property_id)) {
    $response['message'] = "Listing ID missing.";
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare("SELECT first_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
if (!$user) {
    $response['message'] = "User not found.";
    echo json_encode($response);
    exit;
}

$toEmail = $user['email'];
$firstName = $user['first_name'];
$propertyLink = "https://califorsale.org/property.php?id=" . urlencode($property_id);

try {
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'mail.califorsale.org';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'alerts@califorsale.org';
    $mail->Password   = 'Real_estate123$';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    $mail->setFrom('alerts@califorsale.org', 'Real Estate Alerts');
    $mail->addAddress($toEmail, $firstName);

    $mail->isHTML(true);
    $mail->Subject = 'You saved a new listing!';
    $mail->Body = "
        Hi $firstName,<br><br>
        You've saved a new listing:<br>
        <a href='$propertyLink'>$propertyLink</a><br><br>
        Thanks,<br>
        Real Estate Team
    ";

    $mail->send();
    $response['success'] = true;
    $response['message'] = "Email sent to $toEmail";
} catch (Exception $e) {
    $response['message'] = "PHPMailer error: " . $mail->ErrorInfo;
}
echo json_encode($response);
// file_put_contents("alert_debug.log", "PHPMailer error: " . $mail->ErrorInfo . "\n", FILE_APPEND);
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// session_start();
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

// include('includes/PHPMailer/Exception.php');
// include('includes/PHPMailer/PHPMailer.php');
// include('includes/PHPMailer/SMTP.php');

// function sendSavedListingAlert($toEmail, $firstName, $propertyTitle, $propertyLink) {
//     $mail = new PHPMailer(true);

//     try {
//         $mail->SMTPDebug = 2;
//         $mail->Debugoutput = function($str, $level) {
//             file_put_contents('alert_debug.log', "SMTP DEBUG: $str\n", FILE_APPEND);
//         };
        
//         $mail->isSMTP();
//         $mail->Host       = 'mail.califorsale.org'; 
//         $mail->SMTPAuth   = true;
//         $mail->Username   = 'alerts@califorsale.org'; 
//         $mail->Password   = 'Real_estate123$'; 
//         $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
//         $mail->Port       = 465;

//         $mail->setFrom('alerts@califorsale.org', 'Real Estate Alerts');
//         $mail->addAddress($toEmail, $firstName);

//         $mail->isHTML(true);
//         $mail->Subject = 'You saved a new listing!';
//         $mail->Body    = "
//             Hi $firstName,<br><br>
//             You've saved a new listing:<br>
//             <a href='$propertyLink'>$propertyTitle</a><br><br>
//             Thanks,<br>
//             Real Estate Team
//         ";

//         $mail->send();
//         echo json_encode(["success" => true]);
//     } catch (Exception $e) {
//         file_put_contents("alert_debug.log", "PHPMailer error: " . $mail->ErrorInfo . "\n", FILE_APPEND);
//         echo json_encode(["success" => false, "message" => "Mail error"]);
//     }
// }

// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
//     include('api/db.php');

//     $listing_id = $_POST['listing_id'] ?? null;

//     if (!$listing_id) {
//         echo json_encode(['success' => false, 'message' => 'No listing ID provided']);
//         exit;
//     }

//     $stmt = $conn->prepare("SELECT first_name, email FROM users WHERE id = ?");
//     $stmt->bind_param('i', $_SESSION['user_id']);
//     $stmt->execute();
//     $user = $stmt->get_result()->fetch_assoc();

//     $stmt = $conn->prepare("SELECT address FROM listings WHERE id = ?");
//     $stmt->bind_param('i', $listing_id);
//     $stmt->execute();
//     $listing = $stmt->get_result()->fetch_assoc();

//     if ($user && $listing) {
//         $sent = sendSavedListingAlert(
//             $user['email'],
//             $user['first_name'],
//             $listing['address'],
//             "https://pavitra.califorsale.org/property.php?id=$listing_id"
//         );
//         echo json_encode(['success' => $sent]);
//     } else {
//         echo json_encode(['success' => false, 'message' => 'User or listing not found']);
//     }
// } else {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
// }