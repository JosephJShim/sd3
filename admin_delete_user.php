<?php
session_start();
include('api/db.php');

if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@califorsale.org') {
    http_response_code(403);
    echo "Unauthorized.";
    exit;
}

$email = $_POST['email'] ?? '';
$response = ['success' => false];

if ($email === 'admin@califorsale.org') {
    $response['message'] = "Admin account cannot be deleted.";
    echo json_encode($response); exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $response['message'] = "User does not exist.";
    echo json_encode($response); exit;
}
$stmt->close();

$stmt = $conn->prepare("DELETE FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

$response['success'] = true;
$response['message'] = "User with email $email deleted.";
echo json_encode($response);