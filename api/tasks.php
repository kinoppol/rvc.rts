<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
if (!$user) jsonResponse(['error' => 'Unauthorized'], 401);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $status = $_GET['status'] ?? 'all';
    $sql = "SELECT t.*,
              ab.name as assigned_by_name,
              at2.name as assigned_to_name,
              d.doc_number
            FROM tasks t
            LEFT JOIN users ab  ON ab.id  = t.assigned_by_id
            LEFT JOIN users at2 ON at2.id = t.assigned_to_id
            LEFT JOIN documents_in d ON d.id = t.doc_id";
    $params = [];
    if ($status !== 'all') { $sql .= ' WHERE t.status=?'; $params[] = $status; }
    $sql .= ' ORDER BY FIELD(t.urgency,"critical","urgent","normal"), t.due_date';
    jsonResponse(['rows' => fetchAll($sql, $params)]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['title', 'assigned_to_id'];
    foreach ($required as $f) if (empty($data[$f])) jsonResponse(['error' => "ต้องระบุ $f"], 422);

    $count = (int)fetchValue('SELECT COUNT(*)+1 FROM tasks');
    $code  = sprintf('T%03d', $count);

    $id = insert('tasks', [
        'task_code'      => $code,
        'doc_id'         => $data['doc_id'] ?: null,
        'title'          => $data['title'],
        'assigned_by_id' => (int)($data['assigned_by_id'] ?? $user['id']),
        'assigned_to_id' => (int)$data['assigned_to_id'],
        'dept'           => $data['dept']     ?? '',
        'due_date'       => $data['due_date'] ?? null,
        'urgency'        => $data['urgency']  ?? 'normal',
        'note'           => $data['note']     ?? '',
        'status'         => 'todo',
    ]);
    jsonResponse(['id' => $id, 'message' => 'สร้างงานเรียบร้อย']);
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($_GET['id'] ?? $data['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ต้องระบุ id'], 422);

    $allowed = ['status', 'response', 'note', 'due_date', 'urgency'];
    $upd = [];
    foreach ($allowed as $f) if (array_key_exists($f, $data)) $upd[$f] = $data[$f];
    if ($upd) update('tasks', $upd, 'id=?', [$id]);
    jsonResponse(['message' => 'อัพเดทเรียบร้อย']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
