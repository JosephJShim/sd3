<?php
session_start();
include('api/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$query = "SELECT preferredcity, minprice, maxprice, minbeds, minbaths FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $params = http_build_query([
        'address' => $row['preferredcity'] ?? '',
        'min' => $row['minprice'] ?? '',
        'max' => $row['maxprice'] ?? '',
        'beds' => $row['minbeds'] ?? '',
        'baths' => $row['minbaths'] ?? ''
    ]);

    header("Location: index.php?$params");
    exit;
} else {
    header("Location: index.php");
    exit;
}