<?php
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$id   = (int)($data['id'] ?? 0);

if (!$id) { http_response_code(422); echo json_encode(['error' => 'ต้องระบุ id']); exit; }

if (impersonate($id)) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(403);
    echo json_encode(['error' => 'ไม่สามารถสวมสิทธิ์ได้']);
}
