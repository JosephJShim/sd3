<?php
session_start();
include('api/db.php');
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $conn->prepare("SELECT first_name, last_name, phone_number, email, minprice, maxprice, minbeds, minbaths, preferredcity, created_at FROM users WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode(['success' => true, 'profile' => $data]);