<?php
require_once __DIR__ . '/../private/auth.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register Account</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="register-container">
        <h2>Create an Account</h2>

        <?php if (!empty($_SESSION['flash_message'])): ?>
            <?php foreach ($_SESSION['flash_message'] as $type => $message): ?>
                <p class="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($message); ?></p>
            <?php endforeach; ?>
            <?php unset($_SESSION['flash_message']); // Remove after display ?>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password:</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>

            <div class="form-group">
                <label for="invitation_code">Invitation Code:</label>
                <input type="text" id="invitation_code" name="invitation_code" required>
            </div>

            <button type="submit">Register</button>
        </form>

        <p><a href="index.php">Already have an account? Log in.</a></p>
    </div>
</body>
</html>
