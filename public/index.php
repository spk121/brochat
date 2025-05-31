<?php
session_start();
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/db.php';

// Define pagination settings
$itemsPerPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $itemsPerPage;

// Fetch notes & snaps (reverse chronological order)
$db = Database::getConnection();
$stmt = $db->prepare("
    SELECT 'note' AS type, notes.id, notes.timestamp, users.username, notes.filename 
    FROM notes 
    JOIN users ON notes.author_id = users.id 
    WHERE notes.status = 'visible'
    UNION
    SELECT 'snap' AS type, pics.id, pics.timestamp, users.username, pics.caption_filename, pics.pic1_filename, pics.pic2_filename, pics.pic3_filename, pics.pic4_filename 
    FROM pics 
    JOIN users ON pics.author_id = users.id 
    WHERE pics.status = 'visible'
    ORDER BY timestamp DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Formats timestamps for human readability.
 */
function formatTimeAgo($timestamp) {
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return "$diff seconds ago";
    } elseif ($diff < 3600) {
        return floor($diff / 60) . " minutes ago";
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . " hours ago";
    } elseif (date("Y", $timestamp) == date("Y")) {
        return date("M d", $timestamp);
    } else {
        return date("M d, Y", $timestamp);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Weblog</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <nav>
            <ul>
                <?php if (!isset($_SESSION['username'])): ?>
                    <li><a href="login.php">Log In</a></li>
                    <li><a href="register.php">Sign Up</a></li>
                <?php else: ?>
                    <li><a href="logout.php">Log Out</a></li>
                    <?php if ($_SESSION['role'] === 'ADMIN'): ?>
                        <li><a href="manage_invites.php">Manage Invitations</a></li>
                    <?php endif; ?>
                    <li><a href="new_note.php">Post Note</a></li>
                    <li><a href="new_snap.php">Post Snap</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <h1>Welcome to My Weblog</h1>

        <!-- Display notes & snaps -->
        <?php foreach ($entries as $entry): ?>
            <div class="entry">
                <p class="meta">
                    <?php echo htmlspecialchars($entry['username']); ?> · 
                    <?php echo formatTimeAgo($entry['timestamp']); ?>
                </p>
                
                <?php if ($entry['type'] === 'note'): ?>
                    <p><?php echo nl2br(htmlspecialchars(file_get_contents("data/notes/" . $entry['filename']))); ?></p>
                
                <?php elseif ($entry['type'] === 'snap'): ?>
                    <!-- Display snap caption -->
                    <p><?php echo nl2br(htmlspecialchars(file_get_contents("data/snaps/" . $entry['caption_filename']))); ?></p>

                    <!-- Display all available images in a viewport-sensitive grid -->
                    <div class="snap-gallery">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <?php
                            // Safely get and validate the filename
                            $filename = $entry["pic{$i}_filename"] ?? '';
                            if (!empty($filename)) {
                                // Sanitize filename (strip directories, allow only specific extensions)
                                $filename = basename($filename);
                                if (preg_match('/^[a-zA-Z0-9_-]+\.(jpg|png|gif)$/i', $filename) && file_exists("data/snaps/{$filename}")) {
                                    $safe_filename = htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
                                } else {
                                    $safe_filename = null; // Skip invalid or non-existent files
                                }
                            } else {
                                $safe_filename = null; // Skip empty filenames
                            }
                            ?>
                            <?php if ($safe_filename): ?>
                                <img src="data/snaps/previews/<?php echo $safe_filename; ?>_mobile.jpg"
                                    onclick="window.open('data/snaps/<?php echo $safe_filename; ?>', '_blank')">
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <p>Unknown entry type.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="prev-button">← Previous</a>
            <?php endif; ?>
            <a href="?page=<?php echo $page + 1; ?>" class="next-button">Next →</a>
        </div>
    </main>
</body>
</html>
