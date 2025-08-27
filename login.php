<?php
session_start();
include('api/db.php');
header('Content-Type: application/json');

$response = ["success" => false, "message" => "Login failed."];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $response['message'] = "Please fill in all fields.";
    } else {
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query); 
        if (!$stmt) {
            $response['message'] = "Prepare failed: " . mysqli_error($conn);
            echo json_encode($response); exit;
        }

        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['first_name'] = $row['first_name'];
                if ($row['email'] === 'admin@califorsale.org') {
                    $_SESSION['is_admin'] = 1;
                } else {
                    $_SESSION['is_admin'] = 0;
                }
                $response = ["success" => true, "message" => "Login successful."];
            } else {
                $response['message'] = "Incorrect password.";
            }
        } else {
            $response['message'] = "User not found.";
        }
    }
}

echo json_encode($response);