<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../private/auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log In</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Log In</h2>

        <?php if (!empty($_SESSION['flash_message'])): ?>
            <?php foreach ($_SESSION['flash_message'] as $type => $message): ?>
                <p class="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($message); ?></p>
            <?php endforeach; ?>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        <form action="login_handler.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Log In</button>
        </form>

        <p><a href="register.php">Create Account</a></p>
    </div>
</body>
</html>
