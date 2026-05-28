<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

// Must be logged in and a POST request
session_init();
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

// ── Validate project ─────────────────────────────────────────────────────────
$project = trim($_POST['project'] ?? '');
if (!$project || !array_key_exists($project, PROJECTS)) {
    echo json_encode(['success' => false, 'error' => 'Invalid project']);
    exit;
}

// ── Check file ──────────────────────────────────────────────────────────────
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $php_errors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload stopped by PHP extension.',
    ];
    $code  = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    $error = $php_errors[$code] ?? 'Upload error (code ' . $code . ')';
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

// ── Validate file type ────────────────────────────────────────────────────────
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
$allowed_exts  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

$tmp_path  = $_FILES['image']['tmp_name'];
$orig_name = $_FILES['image']['name'];
$file_size = $_FILES['image']['size'];

// Use finfo for reliable MIME type detection
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $tmp_path);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'File type not allowed. Use JPG, PNG, WebP, or GIF.']);
    exit;
}

// Validate extension
$ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_exts)) {
    echo json_encode(['success' => false, 'error' => 'File extension not allowed.']);
    exit;
}

// ── Validate file size (5MB max) ──────────────────────────────────────────────
if ($file_size > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File size exceeds 5MB limit.']);
    exit;
}

// ── Create upload directory ────────────────────────────────────────────────────
$upload_dir = UPLOAD_DIR . $project . '/';
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Could not create upload directory.']);
        exit;
    }
}

// ── Generate unique filename ───────────────────────────────────────────────────
// Sanitize original filename: strip non-alphanumeric except dots/dashes/underscores
$safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig_name);
$safe_name = preg_replace('/_+/', '_', $safe_name);
$filename  = time() . '_' . $safe_name;
$dest_path = $upload_dir . $filename;

// ── Move file ──────────────────────────────────────────────────────────────────
if (!move_uploaded_file($tmp_path, $dest_path)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file.']);
    exit;
}

// Set permissions
chmod($dest_path, 0644);

// ── Return URL ─────────────────────────────────────────────────────────────────
$url = UPLOAD_URL . $project . '/' . $filename;
echo json_encode(['success' => true, 'url' => $url, 'filename' => $filename]);
