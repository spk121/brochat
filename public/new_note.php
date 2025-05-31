<?php
session_start();
require_once __DIR__ . '/../private/auth.php';

// Restrict access to logged-in users
if (!isset($_SESSION['username'])) {
    $_SESSION['flash_message'] = ["error" => "You must be logged in to create a note."];
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Note</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="note-container">
        <h2>Create a Note</h2>

        <?php if (!empty($_SESSION['flash_message'])): ?>
            <?php foreach ($_SESSION['flash_message'] as $type => $message): ?>
                <p class="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($message); ?></p>
            <?php endforeach; ?>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        <form action="new_note_handler.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="form-group">
                <label for="note_content">Your Note:</label>
                <textarea id="note_content" name="note_content" rows="6" required></textarea>
            </div>

            <div class="form-group">
                <label>Status:</label>
                <select name="status">
                    <option value="draft">Draft</option>
                    <option value="visible">Publish</option>
                    <option value="hidden">Hidden</option>
                </select>
            </div>

            <button type="submit">Save Note</button>
            <a href="index.php" class="cancel-button">Cancel</a>
        </form>
    </div>
</body>
</html>
