<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
if (!$user) { jsonResponse(['error' => 'Unauthorized'], 401); }

$method = $_SERVER['REQUEST_METHOD'];

// GET single document
if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $doc = fetchOne('SELECT * FROM documents_in WHERE id=?', [$id]);
    if (!$doc) jsonResponse(['error' => 'Not found'], 404);
    $depts = fetchAll('SELECT dept_name FROM document_departments WHERE doc_id=?', [$id]);
    $personnel = $doc['personnel'] ? array_filter(array_map('trim', explode(',', $doc['personnel']))) : [];
    jsonResponse(['doc' => $doc, 'depts' => array_column($depts, 'dept_name'), 'personnel' => array_values($personnel)]);
}

// GET list
if ($method === 'GET') {
    $type  = $_GET['type']  ?? 'in';
    $q     = '%' . ($_GET['q'] ?? '') . '%';
    $limit = min((int)($_GET['limit'] ?? 50), 100);

    if ($type === 'out') {
        $rows = fetchAll(
            "SELECT * FROM documents_out WHERE subject LIKE ? OR to_org LIKE ? OR doc_number LIKE ? ORDER BY sent_date DESC LIMIT $limit",
            [$q, $q, $q]
        );
    } else {
        $rows = fetchAll(
            "SELECT d.*, GROUP_CONCAT(dd.dept_name SEPARATOR ',') as depts
             FROM documents_in d
             LEFT JOIN document_departments dd ON dd.doc_id=d.id
             WHERE d.subject LIKE ? OR d.from_org LIKE ? OR d.doc_number LIKE ?
             GROUP BY d.id ORDER BY d.received_date DESC, d.id DESC LIMIT $limit",
            [$q, $q, $q]
        );
    }
    jsonResponse(['rows' => $rows]);
}

// POST — create new incoming document
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $required = ['doc_number', 'received_date', 'from_org', 'subject'];
    foreach ($required as $f) {
        if (empty($data[$f])) jsonResponse(['error' => "กรุณากรอก $f"], 422);
    }

    // Check duplicate doc number
    if (fetchValue('SELECT id FROM documents_in WHERE doc_number=?', [$data['doc_number']])) {
        jsonResponse(['error' => 'เลขที่รับนี้มีในระบบแล้ว'], 422);
    }

    // Determine workflow status
    $requireDeputy = fetchValue("SELECT setting_value FROM settings WHERE setting_key='require_deputy_review'") === '1';
    $deputyId      = ($requireDeputy && !empty($data['deputy_id'])) ? (int)$data['deputy_id'] : null;
    $hasAnnotation = !empty($data['annotation']);
    if (!$hasAnnotation) {
        $initStatus = 'pending_annotation';
    } elseif ($deputyId) {
        $initStatus = 'pending_deputy';
    } else {
        $initStatus = 'pending_director';
    }

    $id = insert('documents_in', [
        'doc_number'    => $data['doc_number'],
        'received_date' => $data['received_date'],
        'from_org'      => $data['from_org'],
        'from_short'    => $data['from_short'] ?? '',
        'subject'       => $data['subject'],
        'doc_type'      => $data['doc_type']   ?? 'ราชการ',
        'pages'         => (int)($data['pages'] ?? 0),
        'urgency'       => $data['urgency']    ?? 'normal',
        'secrecy'       => $data['secrecy']    ?? 'none',
        'deputy_id'     => $deputyId,
        'personnel'     => $data['personnel']  ?? null,
        'file_path'     => $data['file_path']  ?? null,
        'annotation'    => $data['annotation'] ?? null,
        'status'        => $initStatus,
        'created_by'    => $user['id'],
    ]);

    // Departments
    $depts = array_filter(explode(',', $data['depts'] ?? ''));
    foreach ($depts as $dept) {
        insert('document_departments', ['doc_id' => $id, 'dept_name' => trim($dept)]);
    }

    // Increment doc_seq so next doc gets the next number
    query("INSERT INTO settings (setting_key,setting_value) VALUES ('doc_seq',1) ON DUPLICATE KEY UPDATE setting_value=setting_value+1");

    jsonResponse(['id' => $id, 'message' => 'บันทึกเรียบร้อย']);
}

// PUT — update document (annotation, deputy_note, director_note, reply_text, status)
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($_GET['id'] ?? $data['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ต้องระบุ id'], 422);

    $doc = fetchOne('SELECT * FROM documents_in WHERE id=?', [$id]);
    if (!$doc) jsonResponse(['error' => 'Not found'], 404);

    $allowed = ['annotation','deputy_note','director_note','reply_text','status','urgency','secrecy'];
    // Allow editing core fields while still pending_annotation
    $pendingStatuses = ['pending_annotation','pending_deputy','pending_director'];
    if (in_array($doc['status'], $pendingStatuses) && ($data['_edit'] ?? false)) {
        $allowed = array_merge($allowed, ['received_date','from_org','from_short','subject','doc_type','pages']);
    }
    // Allow editing annotation text by creator or admin
    if ($data['_edit_annot'] ?? false) {
        $isCreator = (int)$doc['created_by'] === (int)$user['id'];
        $isAdmin   = $user['role'] === 'admin';
        if (!$isCreator && !$isAdmin) jsonResponse(['error' => 'ไม่มีสิทธิ์แก้ไขความเห็น'], 403);
        $allowed[] = 'annotation';
    }
    $textFields = ['annotation','deputy_note','director_note','reply_text'];
    $upd = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $data))
            $upd[$f] = in_array($f, $textFields) ? trim($data[$f]) : $data[$f];
    }

    // Auto-advance status when annotation saved: pending_annotation → pending_deputy or pending_director
    if (isset($data['annotation']) && $doc['status'] === 'pending_annotation') {
        $nextStatus = $doc['deputy_id'] ? 'pending_deputy' : 'pending_director';
        $upd['status'] = $nextStatus;
    }
    // Auto-advance when deputy adds note: pending_deputy → pending_director
    if (isset($data['deputy_note']) && $doc['status'] === 'pending_deputy') {
        $upd['status'] = 'pending_director';
    }

    if ($upd) update('documents_in', $upd, 'id=?', [$id]);

    // Update depts if provided
    if (isset($data['depts'])) {
        query('DELETE FROM document_departments WHERE doc_id=?', [$id]);
        foreach (array_filter(explode(',', $data['depts'])) as $dept) {
            insert('document_departments', ['doc_id' => $id, 'dept_name' => trim($dept)]);
        }
    }

    jsonResponse(['message' => 'อัพเดทเรียบร้อย']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
