<!DOCTYPE html>
<html>
<head>
    <title>Logging out...</title>
</head>
<body>
    <script>
        // Clear the cart data from localStorage
        localStorage.removeItem('orderSystemCart');
        // Redirect to login page
        window.location.href = 'login.php';
    </script>
</body>
</html>
<?php
session_start();
session_destroy();
?>
