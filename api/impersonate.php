<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']); exit;
}

// Must be logged in
$me = current_user();
if (!$me) { http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit; }
if ($me['role'] !== 'admin') { http_response_code(403); echo json_encode(['error' => 'เฉพาะ Admin เท่านั้น']); exit; }

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$id   = (int)($data['id'] ?? 0);

if (!$id) { http_response_code(422); echo json_encode(['error' => 'ต้องระบุ id']); exit; }

if (impersonate($id)) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(403);
    echo json_encode(['error' => 'ไม่สามารถสวมสิทธิ์ได้ (ตรวจสอบว่าผู้ใช้ active และไม่ใช่ Admin)']);
}
