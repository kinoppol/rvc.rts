<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_auth();

$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)($_GET['id'] ?? 0);

if ($method === 'GET') {
    $q = trim($_GET['q'] ?? '');
    if ($q !== '') {
        $like = '%' . $q . '%';
        $rows = fetchAll(
            "SELECT id, name, short_name, type FROM organizations
              WHERE active=1 AND (name LIKE ? OR short_name LIKE ?)
              ORDER BY name LIMIT 30",
            [$like, $like]
        );
    } else {
        $rows = fetchAll(
            "SELECT id, name, short_name, type, active FROM organizations ORDER BY name"
        );
    }
    jsonResponse(['data' => $rows]);
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $name  = trim($body['name']  ?? '');
    $short = trim($body['short_name'] ?? '');
    $type  = in_array($body['type'] ?? '', ['government','private','person','other'])
              ? $body['type'] : 'government';
    if (!$name) jsonResponse(['error' => 'กรุณาระบุชื่อหน่วยงาน'], 422);

    // Check duplicate
    $dup = fetchValue('SELECT id FROM organizations WHERE name=?', [$name]);
    if ($dup) jsonResponse(['error' => 'มีหน่วยงานนี้ในระบบแล้ว', 'id' => (int)$dup], 409);

    $newId = insert('organizations', ['name' => $name, 'short_name' => $short, 'type' => $type]);
    jsonResponse(['id' => $newId, 'name' => $name, 'short_name' => $short, 'type' => $type], 201);
}

if ($method === 'PUT' && $id) {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $data  = array_filter([
        'name'       => isset($body['name'])       ? trim($body['name'])       : null,
        'short_name' => isset($body['short_name']) ? trim($body['short_name']) : null,
        'type'       => isset($body['type'])       && in_array($body['type'],['government','private','person','other']) ? $body['type'] : null,
        'active'     => isset($body['active'])     ? (int)(bool)$body['active'] : null,
    ], fn($v) => $v !== null);
    if (empty($data)) jsonResponse(['error' => 'no data'], 400);
    update('organizations', $data, ['id' => $id]);
    jsonResponse(['ok' => true]);
}

if ($method === 'DELETE' && $id) {
    update('organizations', ['active' => 0], ['id' => $id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'method not allowed'], 405);
