<?php
session_start();
include('api/db.php');

if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'admin@califorsale.org') {
    die("Unauthorized");
}

$listing_id = $_POST['listing_id'] ?? '';

if ($listing_id) {
    $stmt = $conn->prepare("DELETE FROM rets_property_yu WHERE L_ListingID = ?");
    $stmt->bind_param("s", $listing_id);
    if ($stmt->execute()) {
        header("Location: index.php");
    } else {
        die("Failed to delete listing: " . $stmt->error);
    }
    $stmt->close();
} else {
    die("No listing ID provided.");
}