<?php
// Local Database Configuration for Docker MySQL
$servername = '127.0.0.1'; // Use IP instead of localhost for Docker MySQL
$username = 'sd3_user'; // MySQL user
$password = 'sd3_password_123'; // MySQL password
$dbname = 'sd3'; // Database name
$port = 3307; // Docker MySQL port mapping

$conn = new mysqli($servername, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to handle special characters
$conn->set_charset("utf8mb4");

echo "<!-- Docker MySQL database connected successfully -->";
?> 