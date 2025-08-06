<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: user/order.php');
    }
    exit();
}

$error = '';

if ($_POST) {
    require_once 'includes/db_connect.php';

    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check user credentials - First get the user by username only
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Verify the password using password_verify
    if ($user && (password_verify($password, $user['password']) || md5($password) === $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['user_type'] = $user['role'];

        // Update old MD5 hash to new secure hash if needed
        if (md5($password) === $user['password']) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->execute([$newHash, $user['id']]);
        }

        // Redirect based on role
        if ($user['role'] === 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: user/order.php');
        }
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Operation System - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="images/Logo.png" type="image/png">
    <style>
        body {
            background: linear-gradient(135deg, #fff5f5 0%, #fef3c7 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            height: 600px;
            max-width: 800px;
            width: 100%;
        }

        .login-header {
            background: linear-gradient(135deg, #004600 0%, #228B22 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .login-body {
            padding: 2rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #004600 0%, #228B22 100%);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 12px 15px;
        }

        .form-control:focus {
            border-color: #004600;
            box-shadow: 0 0 0 0.2rem #228B22;
        }

    </style>
</head>

<body>
    <div class="login-card">
        <div class="login-header">
            <img src="images/Logo.png" alt="Logo" class="mb-3" style="width: 100px; height: 100px;">
            <h2>Transaction System</h2>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('password', 'password-icon')">
                            <img src="images/password_hide_icon.png" alt="Toggle Password" id="password-icon" style="width: 20px; height: 20px;">
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>

            <script>
                function togglePasswordVisibility(inputId, iconId) {
                    const passwordInput = document.getElementById(inputId);
                    const passwordIcon = document.getElementById(iconId);
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        passwordIcon.src = 'images/password_show_icon.png';
                    } else {
                        passwordInput.type = 'password';
                        passwordIcon.src = 'images/password_hide_icon.png';
                    }
                }
            </script>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>