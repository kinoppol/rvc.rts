<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
if (!$user) jsonResponse(['error' => 'Unauthorized'], 401);

$stats = [
    'total_in'   => (int)fetchValue('SELECT COUNT(*) FROM documents_in'),
    'pending'    => (int)fetchValue("SELECT COUNT(*) FROM documents_in WHERE status NOT IN ('done')"),
    'urgent'     => (int)fetchValue("SELECT COUNT(*) FROM documents_in WHERE urgency != 'normal'"),
    'done'       => (int)fetchValue("SELECT COUNT(*) FROM documents_in WHERE status='done'"),
    'ann_pending'=> (int)fetchValue("SELECT COUNT(*) FROM documents_in WHERE status='pending_annotation'"),
    'task_pending'=> (int)fetchValue("SELECT COUNT(*) FROM tasks WHERE status != 'done'"),
];

$recentDocs = fetchAll(
    "SELECT id,doc_number,subject,status,urgency,received_date,from_short FROM documents_in ORDER BY received_date DESC,id DESC LIMIT 5"
);
$pendingTasks = fetchAll(
    "SELECT t.*,u.name as to_name FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to_id WHERE t.status!='done' ORDER BY FIELD(t.urgency,'critical','urgent','normal'),t.due_date LIMIT 8"
);

jsonResponse(['stats' => $stats, 'recentDocs' => $recentDocs, 'pendingTasks' => $pendingTasks]);
