<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
if (!$user) jsonResponse(['error' => 'Unauthorized'], 401);

$method = $_SERVER['REQUEST_METHOD'];

// ── Helper: save departments for a user ───────────────────────────────
function saveDepartments(int $userId, array $depts): void {
    query('DELETE FROM user_departments WHERE user_id=?', [$userId]);
    foreach (array_unique(array_filter($depts)) as $dep) {
        $depId = fetchValue('SELECT dep_id FROM departments WHERE name=?', [$dep]) ?: null;
        try {
            insert('user_departments', ['user_id' => $userId, 'dep_name' => $dep, 'dep_id' => $depId]);
        } catch (\Throwable) {}
    }
}

if ($method === 'GET') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        $u = fetchOne('SELECT id,username,name,nickname,title,role,extra_roles,dept,active,email,avatar FROM users WHERE id=?', [$id]);
        if (!$u) jsonResponse(['error' => 'ไม่พบผู้ใช้'], 404);
        $u['extra_roles'] = json_decode($u['extra_roles'] ?? '[]', true) ?: [];
        $u['departments'] = fetchAll('SELECT dep_name, dep_id FROM user_departments WHERE user_id=? ORDER BY dep_name', [$id]);
        jsonResponse($u);
    }
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
    $extraRoles = array_values(array_filter($data['extra_roles'] ?? []));
    $depts = array_values(array_filter($data['departments'] ?? []));
    $id = insert('users', [
        'username'    => $data['username'],
        'password'    => password_hash($data['password'], PASSWORD_BCRYPT),
        'name'        => $data['name'],
        'title'       => $data['title'] ?? '',
        'role'        => $data['role']  ?? 'staff',
        'extra_roles' => $extraRoles ? json_encode($extraRoles, JSON_UNESCAPED_UNICODE) : null,
        'dept'        => implode(', ', $depts),
        'email'       => $data['email'] ?? '',
        'active'      => 1,
    ]);
    if ($depts) saveDepartments((int)$id, $depts);
    jsonResponse(['id' => $id, 'message' => 'เพิ่มผู้ใช้เรียบร้อย']);
}

if ($method === 'PUT') {
    if (!can('head', $user)) jsonResponse(['error' => 'ไม่มีสิทธิ์'], 403);
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($_GET['id'] ?? $data['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ต้องระบุ id'], 422);

    if (!empty($data['reset_password'])) {
        update('users', ['password' => password_hash('password', PASSWORD_BCRYPT)], 'id=?', [$id]);
        jsonResponse(['message' => 'รีเซ็ตรหัสผ่านเป็น "password" เรียบร้อย']);
    }

    $upd = [];
    foreach (['name','title','role','email','active'] as $f) {
        if (array_key_exists($f, $data)) $upd[$f] = $data[$f];
    }
    if (array_key_exists('extra_roles', $data)) {
        $er = array_values(array_filter($data['extra_roles'] ?? []));
        $upd['extra_roles'] = $er ? json_encode($er, JSON_UNESCAPED_UNICODE) : null;
    }
    if (array_key_exists('departments', $data)) {
        $depts = array_values(array_filter($data['departments'] ?? []));
        $upd['dept'] = implode(', ', $depts);
        saveDepartments($id, $depts);
    }
    if (!empty($data['password'])) $upd['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
    if ($upd) update('users', $upd, 'id=?', [$id]);
    jsonResponse(['message' => 'อัพเดทเรียบร้อย']);
}

if ($method === 'DELETE') {
    if ($user['role'] !== 'admin') jsonResponse(['error' => 'เฉพาะ Admin เท่านั้น'], 403);

    // Bulk hard delete
    if (isset($_GET['bulk_delete'])) {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids  = array_filter(array_map('intval', $body['ids'] ?? []), fn($id) => $id > 0);
        if (!$ids) jsonResponse(['error' => 'ไม่มี id ที่ระบุ'], 422);
        $deleted = 0;
        foreach ($ids as $id) {
            if ($id === (int)$user['id']) continue;
            $target = fetchOne('SELECT role FROM users WHERE id=?', [$id]);
            if (!$target || $target['role'] === 'admin') continue;
            query('DELETE FROM users WHERE id=?', [$id]);
            $deleted++;
        }
        jsonResponse(['message' => "ลบเรียบร้อย {$deleted} คน", 'deleted' => $deleted]);
    }

    // Single hard delete
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ต้องระบุ id'], 422);
    if ($id === (int)$user['id']) jsonResponse(['error' => 'ไม่สามารถลบบัญชีตัวเองได้'], 422);
    $target = fetchOne('SELECT role FROM users WHERE id=?', [$id]);
    if ($target && $target['role'] === 'admin') jsonResponse(['error' => 'ไม่สามารถลบ Admin ได้'], 422);
    query('DELETE FROM users WHERE id=?', [$id]);
    jsonResponse(['message' => 'ลบผู้ใช้เรียบร้อย']);
}

// Bulk import via CSV (admin only)
if ($method === 'POST' && isset($_GET['bulk'])) {
    if ($user['role'] !== 'admin') jsonResponse(['error' => 'เฉพาะ Admin เท่านั้น'], 403);
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $rows = $data['rows'] ?? [];
    $defaultPw = password_hash('password', PASSWORD_BCRYPT);
    $ok = 0; $skip = 0; $errors = [];
    foreach ($rows as $i => $r) {
        $uname = trim($r['username'] ?? '');
        $name  = trim($r['name']     ?? '');
        if (!$uname || !$name) { $skip++; continue; }
        if (fetchValue('SELECT id FROM users WHERE username=?', [$uname])) { $skip++; $errors[] = "แถว ".($i+1).": '$uname' มีอยู่แล้ว"; continue; }
        insert('users', [
            'username' => $uname,
            'password' => $defaultPw,
            'name'     => $name,
            'title'    => trim($r['title'] ?? ''),
            'role'     => in_array($r['role'] ?? '', array_keys(USER_ROLES)) ? $r['role'] : 'staff',
            'dept'     => trim($r['dept'] ?? ''),
            'email'    => trim($r['email'] ?? ''),
            'active'   => 1,
        ]);
        $ok++;
    }
    jsonResponse(['added' => $ok, 'skipped' => $skip, 'errors' => $errors]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
