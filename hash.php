<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Hash Generator</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>

<body>
    <div class="container mt-5">
        <h1>Password Hash Generator</h1>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'];
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                echo "<div class='alert alert-success'>Hashed Password: " . htmlspecialchars($hash) . "</div>";
            } else {
                echo "<div class='alert alert-danger'>Please enter a password.</div>";
            }
        }
        ?>
        <form method="post">
            <div class="form-group">
                <label for="password">Enter Password:</label>
                <input type="text" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Generate Hash</button>
        </form>
        <p class="mt-3">Note: This is for demonstration purposes only. In a real application, the hashed password would
            be stored securely in a database, not displayed.</p>
    </div>
</body>

</html>