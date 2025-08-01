<?php
session_start();
include('api/db.php');

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if ($password !== $confirm) {
        $message = "Passwords do not match.";
    } else {
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_fetch_assoc($result)) {
            $message = " Email already registered.";
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert = "INSERT INTO users (email, password) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $insert);
            mysqli_stmt_bind_param($stmt, 'ss', $email, $hashed_password);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['user_id'] = mysqli_insert_id($conn);
                $_SESSION['email'] = $email;
                header("Location: index.php");
                exit();
            } else {
                $message = "Registration failed: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width: 500px;">
    <h2 class="mb-4 text-center">Create an Account</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-warning"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php">
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required />
        </div>

        <div class="mb-3">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required />
        </div>

        <div class="mb-3">
            <label>Confirm Password</label>
            <input type="password" name="confirm" class="form-control" required />
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary">Register</button>
        </div>
    </form>

    <hr class="my-4">
    <div class="text-center">
        <p>Already have an account?</p>
        <a href="login.php" class="btn btn-outline-secondary">Go to Login</a>
    </div>
</div>
</body>
</html>