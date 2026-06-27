<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!current_user()) jsonResponse(['error' => 'Unauthorized'], 401);

$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $like = '%' . $q . '%';
    $rows = fetchAll('SELECT dep_id, depgroup_id, name FROM departments WHERE active=1 AND name LIKE ? ORDER BY depgroup_id, name LIMIT 20', [$like]);
} else {
    $rows = fetchAll('SELECT dep_id, depgroup_id, name FROM departments WHERE active=1 ORDER BY depgroup_id, name');
}

jsonResponse($rows);
