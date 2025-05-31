<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../private/auth.php';
require_once __DIR__ . '/../private/db.php';
require_once __DIR__ . '/../private/csrf.php';

// Restrict to logged-in users
if (!isset($_SESSION['username'])) {
    $_SESSION['flash_message'] = ["error" => "You must be logged in to create a Snap."];
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

// Process snap submission
$status = $_POST['status'] ?? 'draft';
$caption_text = trim($_POST['caption'] ?? '');
if (empty($caption_text) || !in_array($status, ['draft', 'visible', 'hidden'])) {
    $_SESSION['flash_message'] = ["error" => "Invalid snap submission."];
    header('Location: new_snap.php');
    exit;
}

// Generate filenames
$timestamp = date('Ymd_His');
$random_hex = bin2hex(random_bytes(2));
$base_filename = $_SESSION['username'] . "_{$timestamp}_{$random_hex}";

$caption_filename = "{$base_filename}.txt";
file_put_contents(__DIR__ . '/../data/snaps/' . $caption_filename, $caption_text);

// Process uploaded images
$pic_filenames = [];
foreach ($_FILES['pics']['tmp_name'] as $index => $tmp_name) {
    if (!empty($tmp_name)) {
        $file_ext = strtolower(pathinfo($_FILES['pics']['name'][$index], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_ext, $allowed_exts)) {
            continue; // Skip invalid files
        }

        $pic_filename = "{$base_filename}_".bin2hex(random_bytes(2)).".{$file_ext}";
        $pic_filenames[] = $pic_filename;
        move_uploaded_file($tmp_name, __DIR__ . '/../data/snaps/' . $pic_filename);

        // Generate previews in different sizes
        createSmartPreview(__DIR__ . '/../data/snaps/' . $pic_filename, __DIR__ . '/../data/snaps/previews/' . "{$pic_filename}_mobile.jpg", 600);
        createSmartPreview(__DIR__ . '/../data/snaps/' . $pic_filename, __DIR__ . '/../data/snaps/previews/' . "{$pic_filename}_desktop.jpg", 1200);
    }
}

// Ensure at least one valid image
if (empty($pic_filenames)) {
    $_SESSION['flash_message'] = ["error" => "At least one image is required."];
    header('Location: new_snap.php');
    exit;
}

// Save snap metadata to database
$stmt = $db->prepare("
    INSERT INTO pics (author_id, timestamp, status, caption_filename, pic1_filename, pic2_filename, pic3_filename, pic4_filename)
    VALUES (:author_id, :timestamp, :status, :caption_filename, :pic1, :pic2, :pic3, :pic4)
");
$stmt->execute([
    'author_id' => $user['id'],
    'timestamp' => time(),
    'status' => $status,
    'caption_filename' => $caption_filename,
    'pic1' => $pic_filenames[0] ?? null,
    'pic2' => $pic_filenames[1] ?? null,
    'pic3' => $pic_filenames[2] ?? null,
    'pic4' => $pic_filenames[3] ?? null
]);

logEvent($db, 'snap_created', $_SESSION['username'], getClientIP(), "Created new snap '{$caption_filename}', status: {$status}.");
$_SESSION['flash_message'] = ["success" => "Snap posted successfully!"];
header('Location: index.php');
exit;

/**
 * Generates optimized previews at specified max width.
 */
function createSmartPreview($originalPath, $previewPath, $maxWidth) {
    list($width, $height, $type) = getimagesize($originalPath);
    
    // Load image based on type
    if ($type === IMAGETYPE_PNG) {
        $image = imagecreatefrompng($originalPath);
    } elseif ($type === IMAGETYPE_GIF) {
        $image = imagecreatefromgif($originalPath);
    } elseif ($type === IMAGETYPE_JPEG) {
        $image = imagecreatefromjpeg($originalPath);
    } else {
        throw new Exception("Unsupported image type: {$type}");
    }
    if (!$image) {
        throw new Exception("Failed to create image from file: {$originalPath}");
    }

    // **Auto-rotate based on EXIF orientation**
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = exif_read_data($originalPath);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $image = imagerotate($image, 180, 0); // Upside down
                    break;
                case 6:
                    $image = imagerotate($image, -90, 0); // Rotate right
                    break;
                case 8:
                    $image = imagerotate($image, 90, 0); // Rotate left
                    break;
            }
        }
    }

    // Resize image
    $newWidth = min($width, $maxWidth);
    $newHeight = ($height / $width) * $newWidth;
    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Save as optimized JPEG
    imagejpeg($resizedImage, $previewPath, 80);
}

?>
