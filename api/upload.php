<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config.php';

$user = current_user();
if (!$user) jsonResponse(['error' => 'Unauthorized'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'POST only'], 405);

$file = $_FILES['file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) jsonResponse(['error' => 'ไม่พบไฟล์ที่อัพโหลด'], 422);

$maxBytes = UPLOAD_MAX_MB * 1024 * 1024;
if ($file['size'] > $maxBytes) jsonResponse(['error' => 'ไฟล์ใหญ่เกิน ' . UPLOAD_MAX_MB . ' MB'], 422);

$mime = mime_content_type($file['tmp_name']);
if ($mime !== 'application/pdf') jsonResponse(['error' => 'รองรับเฉพาะไฟล์ PDF'], 422);

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

$ext      = 'pdf';
$filename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest     = UPLOAD_DIR . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    jsonResponse(['error' => 'บันทึกไฟล์ไม่สำเร็จ'], 500);
}

// Update doc if doc_id provided
$docId = (int)($_POST['doc_id'] ?? 0);
if ($docId) {
    update('documents_in', ['file_path' => $filename], 'id=?', [$docId]);
}

jsonResponse(['filename' => $filename, 'message' => 'อัพโหลดสำเร็จ']);
