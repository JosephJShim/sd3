<?php
session_start();
include('api/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

function sanitize($value) {
    return $value === '' ? null : $value;
}

$user_id = $_SESSION['user_id'];
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$phone = sanitize($_POST['phone_number'] ?? '');
$minprice = sanitize($_POST['minprice'] ?? '');
$maxprice = sanitize($_POST['maxprice'] ?? '');
$minbeds = sanitize($_POST['minbeds'] ?? '');
$minbaths = sanitize($_POST['minbaths'] ?? '');
$preferredcity = sanitize($_POST['preferredcity'] ?? '');

$query = "UPDATE users SET first_name = ?, last_name = ?, phone_number = ?, minprice = ?, maxprice = ?, minbeds = ?, minbaths = ?, preferredcity = ? WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('sssiiissi', $first_name, $last_name, $phone, $minprice, $maxprice, $minbeds, $minbaths, $preferredcity, $user_id);

if ($stmt->execute()) {
    $_SESSION['first_name'] = $first_name;
    header("Location: profile.php");
    exit;
} else {
    echo "Update failed.";
}