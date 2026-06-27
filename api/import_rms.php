<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = current_user();
if (!$me || $me['role'] !== 'admin') jsonResponse(['error' => 'เฉพาะ Admin เท่านั้น'], 403);

// ── Endpoints (base URL from DB, paths hardcoded) ─────────────────────
const RMS_PATH_PEOPLE = '/api_connection.php?app_name=nutty&data=people';
const RMS_PATH_DEP    = '/api_connection.php?app_name=nutty&data=people_dep';

$baseUrl = fetchValue("SELECT setting_value FROM settings WHERE setting_key='rms_base_url'") ?: '';
if (!$baseUrl) jsonResponse(['error' => 'ยังไม่ได้ตั้งค่า URL แหล่งข้อมูล RMS (ตั้งค่าระบบ → การเชื่อมต่อข้อมูล)'], 422);

$action = $_GET['action'] ?? 'people';  // people | dep

// ── Route to department import ─────────────────────────────────────────
if ($action === 'dep') {
    $url  = rtrim($baseUrl, '/') . RMS_PATH_DEP;
    $ctx  = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $raw  = @file_get_contents($url, false, $ctx);
    if ($raw === false) jsonResponse(['error' => "เชื่อมต่อ RMS ไม่ได้: $url"], 502);
    $json = json_decode($raw, true);
    if (!is_array($json)) jsonResponse(['error' => 'ข้อมูลไม่ใช่ JSON'], 502);
    $deps = isset($json[0]) ? $json : ($json['data'] ?? array_values($json)[0] ?? []);

    $added = 0; $updated = 0;
    foreach ($deps as $d) {
        $depId   = (int)($d['people_dep_id']      ?? 0);
        $groupId = (int)($d['people_depgroup_id'] ?? 0);
        $name    = trim((string)($d['people_dep_name'] ?? ''));
        if (!$depId || !$name) continue;

        $exists = fetchValue('SELECT id FROM departments WHERE dep_id=?', [$depId]);
        if ($exists) {
            update('departments', ['depgroup_id' => $groupId, 'name' => $name, 'active' => 1], 'dep_id=?', [$depId]);
            $updated++;
        } else {
            insert('departments', ['dep_id' => $depId, 'depgroup_id' => $groupId, 'name' => $name, 'active' => 1]);
            $added++;
        }
    }

    $now = date('Y-m-d H:i:s');
    query("INSERT INTO settings (setting_key,setting_value) VALUES ('rms_last_dep_import',?) ON DUPLICATE KEY UPDATE setting_value=?", [$now, $now]);
    jsonResponse(['ok' => true, 'added' => $added, 'updated' => $updated, 'total' => count($deps), 'imported_at' => $now]);
}

// ── People import ──────────────────────────────────────────────────────
$url = rtrim($baseUrl, '/') . RMS_PATH_PEOPLE;

// ── Fetch ──────────────────────────────────────────────────────────────
$ctx = stream_context_create(['http' => [
    'timeout'       => 15,
    'ignore_errors' => true,
]]);
$raw = @file_get_contents($url, false, $ctx);
if ($raw === false) {
    jsonResponse(['error' => "เชื่อมต่อ RMS ไม่ได้: $url"], 502);
}

$json = json_decode($raw, true);
if (!is_array($json)) {
    jsonResponse(['error' => 'ข้อมูลที่ได้รับไม่ใช่ JSON ที่ถูกต้อง', 'raw' => mb_substr($raw, 0, 300)], 502);
}

// รองรับทั้ง [{...},{...}] และ {"data":[...]}
$people = isset($json[0]) ? $json : ($json['data'] ?? $json['people'] ?? array_values($json)[0] ?? []);
if (!is_array($people)) jsonResponse(['error' => 'ไม่พบรายการผู้ใช้ใน JSON'], 502);

// ── Avatar downloader ──────────────────────────────────────────────────
function downloadAvatar(string $baseUrl, string $picName, string $username): string {
    if (!$picName) return '';
    $avatarDir = __DIR__ . '/../uploads/avatars/';
    $ext       = strtolower(pathinfo($picName, PATHINFO_EXTENSION)) ?: 'jpg';
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) return '';
    $filename  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username) . '.' . $ext;
    $savePath  = $avatarDir . $filename;
    $srcUrl    = rtrim($baseUrl, '/') . '/files/' . ltrim($picName, '/');

    $ctx = stream_context_create(['http' => ['timeout' => 8, 'ignore_errors' => true]]);
    $img = @file_get_contents($srcUrl, false, $ctx);
    if (!$img || strlen($img) < 100) return '';

    // Validate it's actually an image
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_buffer($finfo, $img);
    finfo_close($finfo);
    if (!str_starts_with($mime, 'image/')) return '';

    file_put_contents($savePath, $img);
    return $filename;
}

// ── Preview mode (ทดสอบโดยไม่บันทึก) ─────────────────────────────────
$previewOnly = isset($_GET['preview']);
if ($previewOnly) {
    $activeCount = 0;
    $existingCount = 0;
    foreach ($people as $p) {
        if ((string)($p['people_exit'] ?? '1') !== '0') continue;
        $activeCount++;
        $u = trim((string)($p['people_id'] ?? ''));
        if ($u && fetchValue('SELECT id FROM users WHERE username=?', [$u])) $existingCount++;
    }
    jsonResponse([
        'ok'          => true,
        'total'       => count($people),
        'active_count'=> $activeCount,
        'existing'    => $existingCount,
        'new_count'   => max(0, $activeCount - $existingCount),
        'source'      => $url,
    ]);
}

// ── Import ─────────────────────────────────────────────────────────────
$added    = 0;
$updated  = 0;
$skipped  = 0;
$deactivated = 0;
$errors   = [];

foreach ($people as $p) {
    $username = trim((string)($p['people_id'] ?? ''));
    if (!$username) { $skipped++; continue; }

    // ถ้า people_exit = 1 → ปิดการใช้งานในระบบ (ถ้ามีอยู่)
    if ((string)($p['people_exit'] ?? '1') !== '0') {
        $existing = fetchOne('SELECT id, active FROM users WHERE username=?', [$username]);
        if ($existing && $existing['active']) {
            update('users', ['active' => 0], 'id=?', [$existing['id']]);
            $deactivated++;
        } else {
            $skipped++;
        }
        continue;
    }

    $username = trim((string)($p['people_id']       ?? ''));
    $name     = trim(trim((string)($p['people_name'] ?? '')) . ' ' . trim((string)($p['people_surname'] ?? '')));
    $nickname = trim((string)($p['people_nickname'] ?? ''));
    $email    = trim((string)($p['people_email']    ?? ''));
    // people_pass คือ MD5 จาก RMS — เก็บตรงๆ ให้ auth.php ตรวจสอบและ upgrade เป็น bcrypt เองเมื่อ login
    $rawPw    = trim((string)($p['people_pass']     ?? ''));

    if (!$username || !$name) {
        $errors[] = "ข้ามแถว: people_id='$username' — ขาดข้อมูลจำเป็น";
        $skipped++;
        continue;
    }

    $existing = fetchOne('SELECT id, created_at FROM users WHERE username=?', [$username]);

    $picName    = trim((string)($p['people_pic'] ?? ''));
    $avatarFile = $picName ? downloadAvatar($baseUrl, $picName, $username) : '';

    if ($existing) {
        // อัปเดตข้อมูล แต่ไม่แตะ created_at
        $upd = ['name' => $name];
        if ($nickname)    $upd['nickname'] = $nickname;
        if ($email)       $upd['email']    = $email;
        if ($rawPw)       $upd['password'] = $rawPw;   // MD5 — auth.php จะ upgrade เป็น bcrypt ตอน login
        if ($avatarFile)  $upd['avatar']   = $avatarFile;
        update('users', $upd, 'id=?', [$existing['id']]);
        $updated++;
    } else {
        // สร้างใหม่
        insert('users', [
            'username' => $username,
            'password' => $rawPw ?: password_hash('password', PASSWORD_BCRYPT),
            'name'     => $name,
            'nickname' => $nickname,
            'title'    => '',
            'role'     => 'staff',
            'dept'     => '',
            'email'    => $email,
            'avatar'   => $avatarFile,
            'active'   => 1,
        ]);
        $added++;
    }
}

$now = date('Y-m-d H:i:s');
query("INSERT INTO settings (setting_key,setting_value) VALUES ('rms_last_import',?) ON DUPLICATE KEY UPDATE setting_value=?", [$now, $now]);
$stats = ['added'=>$added,'updated'=>$updated,'skipped'=>$skipped,'deactivated'=>$deactivated,'total'=>count($people)];
query("INSERT INTO settings (setting_key,setting_value) VALUES ('rms_last_stats',?) ON DUPLICATE KEY UPDATE setting_value=?",
    [json_encode($stats, JSON_UNESCAPED_UNICODE), json_encode($stats, JSON_UNESCAPED_UNICODE)]);

jsonResponse([
    'ok'          => true,
    'added'       => $added,
    'updated'     => $updated,
    'skipped'     => $skipped,
    'deactivated' => $deactivated,
    'errors'      => $errors,
    'total'       => count($people),
    'source'      => $url,
    'imported_at' => $now,
]);
