<?php
session_start();
require_once __DIR__ . '/../private/auth.php';

// Restrict access to logged-in users
if (!isset($_SESSION['username'])) {
    $_SESSION['flash_message'] = ["error" => "You must be logged in to create a Snap."];
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Snap</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="snap-container">
        <h2>Create a Snap</h2>

        <?php if (!empty($_SESSION['flash_message'])): ?>
            <?php foreach ($_SESSION['flash_message'] as $type => $message): ?>
                <p class="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($message); ?></p>
            <?php endforeach; ?>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        <form id="snapForm" action="new_snap_handler.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

            <div class="form-group">
                <label for="caption">Caption:</label>
                <textarea id="caption" name="caption" rows="4" required></textarea>
            </div>

            <div class="form-group">
                <label>Status:</label>
                <select name="status">
                    <option value="draft">Draft</option>
                    <option value="visible">Publish</option>
                    <option value="hidden">Hidden</option>
                </select>
            </div>

            <div class="form-group">
                <label>Add Images:</label>
                <input type="file" id="fileInput" name="pics[]" accept="image/*" multiple>
                <button type="button" id="cameraButton">Take Photo</button>
            </div>

            <video id="cameraPreview" autoplay style="display:none;"></video>
            <canvas id="cameraCanvas" style="display:none;"></canvas>

            <div id="previewContainer"></div> <!-- Thumbnail previews -->

            <button type="submit">Post Snap</button>
            <a href="index.php" class="cancel-button">Cancel</a>
        </form>

        <script src="js/snap.js"></script> <!-- External JS file -->
    </div>
</body>
</html>
