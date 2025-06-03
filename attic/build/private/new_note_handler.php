<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/csrf.php';

// Restrict to logged-in users
if (!isset($_SESSION['username'])) {
    $_SESSION['flash_message'] = ["error" => "You must be logged in to create a note."];
    header('Location: index.php');
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message'] = ["error" => "Invalid request."];
    header('Location: index.php');
    exit;
}

// CSRF validation
if (validateCsrfFromPost() !== CsrfValidationResult::VALID) {
    $_SESSION['flash_message'] = ["error" => "CSRF validation failed."];
    header('Location: index.php');
    exit;
}

// Retrieve user ID
$db = Database::getConnection();
$stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
$stmt->execute(['username' => $_SESSION['username']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $_SESSION['flash_message'] = ["error" => "User not found."];
    header('Location: index.php');
    exit;
}

// Process note submission
$note_content = trim($_POST['note_content'] ?? '');
$status = $_POST['status'] ?? 'draft';
if (empty($note_content) || !in_array($status, ['draft', 'visible', 'hidden'])) {
    $_SESSION['flash_message'] = ["error" => "Invalid note submission."];
    header('Location: new_note.php');
    exit;
}

// Generate filename: {username}_{YYYYMMDD}_{HHMMSS}.txt
$filename = $_SESSION['username'] . '_' . date('Ymd_His') . '.txt';
file_put_contents(__DIR__ . '/../data/notes/' . $filename, $note_content);

// Save note details to database
$stmt = $db->prepare("
    INSERT INTO notes (author_id, timestamp, status, filename)
    VALUES (:author_id, :timestamp, :status, :filename)
");
$stmt->execute([
    'author_id' => $user['id'],
    'timestamp' => time(),
    'status' => $status,
    'filename' => $filename
]);

logEvent($db, 'note_created', $_SESSION['username'], getClientIP(), "Created new note '{$filename}', status: {$status}.");
$_SESSION['flash_message'] = ["success" => "Note saved!"];
header('Location: index.php');
exit;
?>

