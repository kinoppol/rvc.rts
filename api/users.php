<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
if (!$user) jsonResponse(['error' => 'Unauthorized'], 401);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $q    = '%' . ($_GET['q'] ?? '') . '%';
    $role = $_GET['role'] ?? 'all';
    $sql  = 'SELECT id,username,name,title,role,dept,active,email FROM users WHERE (name LIKE ? OR title LIKE ? OR dept LIKE ?)';
    $params = [$q, $q, $q];
    if ($role !== 'all') { $sql .= ' AND role=?'; $params[] = $role; }
    $sql .= ' ORDER BY FIELD(role,"director","deputy","head","dept_head","teacher","staff"), name';
    jsonResponse(['rows' => fetchAll($sql, $params)]);
}

if ($method === 'POST') {
    if (!can('head', $user)) jsonResponse(['error' => 'ไม่มีสิทธิ์'], 403);
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    foreach (['username','name','password'] as $f) if (empty($data[$f])) jsonResponse(['error' => "ต้องระบุ $f"], 422);
    if (fetchValue('SELECT id FROM users WHERE username=?', [$data['username']])) {
        jsonResponse(['error' => 'ชื่อผู้ใช้นี้มีในระบบแล้ว'], 422);
    }
    $id = insert('users', [
        'username' => $data['username'],
        'password' => password_hash($data['password'], PASSWORD_BCRYPT),
        'name'     => $data['name'],
        'title'    => $data['title'] ?? '',
        'role'     => $data['role']  ?? 'staff',
        'dept'     => $data['dept']  ?? '',
        'email'    => $data['email'] ?? '',
        'active'   => 1,
    ]);
    jsonResponse(['id' => $id, 'message' => 'เพิ่มผู้ใช้เรียบร้อย']);
}

if ($method === 'PUT') {
    if (!can('head', $user)) jsonResponse(['error' => 'ไม่มีสิทธิ์'], 403);
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($_GET['id'] ?? $data['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ต้องระบุ id'], 422);

    $upd = [];
    foreach (['name','title','role','dept','email','active'] as $f) {
        if (array_key_exists($f, $data)) $upd[$f] = $data[$f];
    }
    if (!empty($data['password'])) $upd['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
    if ($upd) update('users', $upd, 'id=?', [$id]);
    jsonResponse(['message' => 'อัพเดทเรียบร้อย']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
