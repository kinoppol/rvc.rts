<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_auth();

$page  = preg_replace('/[^a-z]/', '', strtolower($_GET['page'] ?? 'dashboard'));
$pages = ['dashboard','register','newdoc','annotate','route','tasks','reply','orgchart','users','settings','admin','import'];
if (!in_array($page, $pages, true)) $page = 'dashboard';

$titles = [
    'dashboard' => 'หน้าหลัก',
    'register'  => 'ทะเบียนหนังสือรับ-ส่ง',
    'newdoc'    => 'รับเอกสารใหม่',
    'annotate'  => 'เกษียนหนังสือ',
    'route'     => 'เสนอ / มอบหมาย',
    'tasks'     => 'ติดตามงาน',
    'reply'     => 'เกษียนตอบ / รายงาน',
    'orgchart'  => 'โครงสร้างองค์กร',
    'users'     => 'จัดการผู้ใช้งาน',
    'settings'  => 'ตั้งค่าระบบ',
    'admin'     => 'จัดการระบบ (Admin)',
    'import'    => 'โอนข้อมูลบุคลากร',
];

// Admin-only page guard
if (in_array($page, ['admin', 'import', 'users']) && $user['role'] !== 'admin') {
    $page = 'dashboard';
}

renderShell($user, $page, $titles[$page] ?? 'หน้าหลัก', function() use ($page, $user) {
    $file = __DIR__ . '/pages/' . $page . '.php';
    if (file_exists($file)) include $file;
});
