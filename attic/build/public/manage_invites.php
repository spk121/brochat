<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Invitation Codes</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="admin-container">
        <h2>Manage Invitation Codes</h2>

        <?php if (!empty($_SESSION['flash_message'])): ?>
            <?php foreach ($_SESSION['flash_message'] as $type => $message): ?>
                <p class="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($message); ?></p>
            <?php endforeach; ?>
            <?php unset($_SESSION['flash_message']); // Remove after display ?>
        <?php endif; ?>

        <h3>Active Invitation Codes</h3>
        <?php if (empty($active_invites)): ?>
            <p>No active invitation codes.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Code</th>
                    <th>Expiration Date</th>
                    <th>Usage Count</th>
                    <th>Max Uses</th>
                    <th>Expire Code</th>
                </tr>
                <?php foreach ($active_invites as $invite): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($invite['code']); ?></td>
                        <td><?php echo date('Y-m-d', $invite['expiration_date']); ?></td>
                        <td><?php echo htmlspecialchars($invite['usage_count']); ?></td>
                        <td><?php echo htmlspecialchars($invite['max_uses']); ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                <input type="hidden" name="expire_code" value="<?php echo htmlspecialchars($invite['code']); ?>">
                                <button type="submit">Expire</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <h3>Create a New Invitation Code</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <label for="new_expiration_days">Expiration (days):</label>
            <input type="number" id="new_expiration_days" name="new_expiration_days" min="1" required>

            <label for="new_max_uses">Max Uses:</label>
            <input type="number" id="new_max_uses" name="new_max_uses" min="1" required>

            <button type="submit">Generate Code</button>
        </form>

        <p><a href="index.php">Return to Home</a></p>
    </div>
</body>
</html>
