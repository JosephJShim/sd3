<?php
// Database configuration
$host = '199.250.207.194';
$db = 'boxgra6_sd3';
$user = 'boxgra6_sd3';
$pass = 'Real_estate123$';
$port = 3306;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

?>