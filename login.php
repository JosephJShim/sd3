<?php
session_start();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width: 500px;">
    <h2 class="mb-4 text-center">Login</h2>

    <form action="login_action.php" method="POST">
        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required />
        </div>
        <div class="mb-3">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required />
        </div>
        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-success">Login</button>
        </div>
    </form>

    <hr class="my-4">

    <div class="text-center">
        <p>Don't have an account? Register today!</p>
        <a href="register.php" class="btn btn-outline-primary">Create Account</a>
    </div>
</div>
</body>
</html>