<?php
session_start();
include('api/db.php');

header('Content-Type: application/json'); 

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $response = ["success" => false, "message" => "Unknown error."];

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $minprice = trim($_POST['minprice'] ?? '');
    $maxprice = trim($_POST['maxprice'] ?? '');
    $minbeds = trim($_POST['minbeds'] ?? '');
    $minbaths = trim($_POST['minbaths'] ?? '');
    $preferredcity = trim($_POST['preferredcity'] ?? '');

    if (!$first_name || !$last_name || !$email || !$password || !$confirm) {
        $response['message'] = "Please fill in all required fields.";
    } elseif ($password !== $confirm) {
        $response['message'] = "Passwords do not match.";
    } else {
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_fetch_assoc($result)) {
            $response['message'] = "Email already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = "INSERT INTO users (first_name, last_name, phone_number, email, password, minprice, maxprice, minbeds, minbaths, preferredcity)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert);
            mysqli_stmt_bind_param($stmt, 'ssssssssss', $first_name, $last_name, $phone, $email, $hashed_password, $minprice, $maxprice, $minbeds, $minbaths, $preferredcity);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['user_id'] = mysqli_insert_id($conn);
                $_SESSION['email'] = $email;
                $_SESSION['first_name'] = $first_name;
                $response = ["success" => true, "message" => "Registration successful"];
            } else {
                $response['message'] = "Registration failed.";
            }
        }
    }

    echo json_encode($response);
    exit;
}
?>