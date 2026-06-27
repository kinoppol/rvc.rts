<?php
/**
 * RVC RTS — Database Installer
 * รัน http://localhost/rvc.rts/install.php เพียงครั้งเดียว
 */
require_once __DIR__ . '/config.php';

$errors = [];
$log    = [];

try {
    // ── 1. เชื่อมต่อ MySQL/MariaDB ────────────────────────────────
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, '', (int)DB_PORT);
    if ($mysqli->connect_error) {
        throw new RuntimeException('เชื่อมต่อไม่สำเร็จ: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
    $log[] = '✅ เชื่อมต่อ MySQL/MariaDB สำเร็จ (Host: ' . DB_HOST . ')';

    // ── 2. อ่านไฟล์ install.sql ────────────────────────────────────
    $sqlFile = __DIR__ . '/install.sql';
    if (!file_exists($sqlFile)) throw new RuntimeException('ไม่พบไฟล์ install.sql');
    $sql = file_get_contents($sqlFile);
    $log[] = '✅ อ่านไฟล์ install.sql (' . number_format(strlen($sql)) . ' bytes)';

    // ── 3. รัน SQL ทั้งไฟล์ด้วย multi_query ─────────────────────
    $ok = 0; $skip = 0;
    if ($mysqli->multi_query($sql)) {
        do {
            // store/free result set (needed to advance to next query)
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
            if ($mysqli->errno) {
                $errno = $mysqli->errno;
                // Ignore: 1050=table exists, 1007=db exists, 1062=duplicate key, 1060=duplicate column
                if (in_array($errno, [1050, 1007, 1062, 1060])) {
                    $skip++;
                } else {
                    $errors[] = '[' . $errno . '] ' . htmlspecialchars($mysqli->error);
                }
            } else {
                $ok++;
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    } else {
        $errors[] = '[' . $mysqli->errno . '] ' . htmlspecialchars($mysqli->error);
    }
    $mysqli->close();

    $log[] = "✅ รันคำสั่ง SQL: สำเร็จ {$ok} / ข้ามที่มีอยู่แล้ว {$skip}" . (!empty($errors) ? ' / มีข้อผิดพลาด ' . count($errors) : '');

    // ── 4. สร้างโฟลเดอร์ uploads ──────────────────────────────────
    $uploadDirs = ['uploads/documents', 'uploads/avatars'];
    foreach ($uploadDirs as $dir) {
        $fullPath = __DIR__ . '/' . $dir;
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
            $log[] = "✅ สร้างโฟลเดอร์ {$dir}/";
        } else {
            $log[] = "✅ โฟลเดอร์ {$dir}/ มีอยู่แล้ว";
        }
    }

    // ── 5. ตรวจสอบผลลัพธ์ ─────────────────────────────────────────
    if (empty($errors)) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $log[]  = '✅ ตารางที่สร้างแล้ว: ' . implode(', ', $tables);
        $cnt    = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $log[]  = "✅ มีผู้ใช้ในระบบ {$cnt} คน";
        $dcnt   = $pdo->query('SELECT COUNT(*) FROM documents_in')->fetchColumn();
        $log[]  = "✅ มีหนังสือรับตัวอย่าง {$dcnt} ฉบับ";

        // Re-hash passwords using THIS PHP installation's bcrypt
        $hash = password_hash('password', PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password = ?')->execute([$hash]);
        $log[] = '✅ อัปเดต hash รหัสผ่านทุกบัญชีด้วย bcrypt ของ PHP นี้แล้ว';
    }

} catch (Throwable $e) {
    $errors[] = $e->getMessage();
}

$success = empty($errors);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ติดตั้งระบบ — RVC RTS</title>
<style>
*{box-sizing:border-box;}
body{font-family:'Noto Sans Thai',system-ui,sans-serif;background:#f0f2f5;margin:0;padding:30px;font-size:14px;}
.box{background:#fff;border-radius:12px;padding:30px;max-width:720px;margin:0 auto;box-shadow:0 4px 16px rgba(0,0,0,.1);}
h1{color:#7b1113;margin:0 0 4px;font-size:22px;}
.sub{color:#6b7280;margin:0 0 24px;font-size:13px;}
.alert{border-radius:8px;padding:14px 16px;margin-bottom:12px;font-size:13.5px;line-height:1.7;}
.alert-ok{background:#dcfce7;border:1px solid #86efac;color:#166534;}
.alert-err{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;}
.log{border-top:1px solid #e5e7eb;padding-top:16px;margin-top:20px;}
.log div{font-size:12.5px;line-height:2;font-family:monospace;}
a.btn{display:inline-block;background:#7b1113;color:#fff;padding:11px 26px;border-radius:6px;text-decoration:none;font-size:14px;font-weight:600;margin-top:16px;}
code{background:#f3f4f6;padding:2px 6px;border-radius:4px;}
</style>
</head>
<body>
<div class="box">
  <h1>🛠 ติดตั้งระบบสารบรรณ</h1>
  <p class="sub">วิทยาลัยอาชีวศึกษาร้อยเอ็ด — ฐานข้อมูล: <code><?= htmlspecialchars(DB_NAME) ?></code></p>

  <?php if ($errors): ?>
    <div class="alert alert-err">
      <strong>⚠️ พบข้อผิดพลาด:</strong><br>
      <?= implode('<br>', $errors) ?>
    </div>
  <?php endif ?>

  <?php if ($success): ?>
    <div class="alert alert-ok">
      <strong>✅ ติดตั้งเสร็จสมบูรณ์!</strong><br>
      ผู้ใช้ทดสอบ: <code>head1</code> รหัสผ่าน: <code>password</code><br>
      ผู้อำนวยการ: <code>director</code> รหัสผ่าน: <code>password</code>
    </div>
    <a class="btn" href="/rvc.rts/">เข้าสู่ระบบ →</a>
    <p style="font-size:12px;color:#dc2626;margin-top:14px">⚠️ กรุณาลบไฟล์ <code>install.php</code> หลังจากติดตั้งเสร็จแล้ว</p>
  <?php endif ?>

  <div class="log">
    <?php foreach ($log as $l): ?>
      <div><?= $l ?></div>
    <?php endforeach ?>
  </div>
</div>
</body>
</html>
