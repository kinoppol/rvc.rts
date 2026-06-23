<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

session_start_safe();
if (current_user()) {
    header('Location: /rvc.rts/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$username || !$password) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $user = login($username, $password);
        if ($user) {
            header('Location: /rvc.rts/');
            exit;
        }
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}

$logo = '/rvc.rts/project/uploads/logo-1782108162561.png';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เข้าสู่ระบบ — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="/rvc.rts/assets/css/app.css">
</head>
<body>
<div class="rts" data-theme="light">
<div class="login-bg">
  <div class="login-box">
    <div class="login-head">
      <img class="login-logo" src="<?= $logo ?>" alt="logo" onerror="this.style.display='none'">
      <div class="login-title"><?= htmlspecialchars(SCHOOL_NAME) ?></div>
      <div class="login-sub"><?= htmlspecialchars(APP_NAME) ?></div>
    </div>
    <div class="login-body">
      <?php if ($error): ?>
        <div class="login-err">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif ?>
      <form method="post" action="">
        <div class="fg">
          <label class="fl">ชื่อผู้ใช้งาน</label>
          <input class="fc" type="text" name="username" placeholder="ชื่อผู้ใช้" autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autofocus>
        </div>
        <div class="fg">
          <label class="fl">รหัสผ่าน</label>
          <input class="fc" type="password" name="password" placeholder="รหัสผ่าน" autocomplete="current-password">
        </div>
        <button class="btn bp" type="submit" style="width:100%;justify-content:center;padding:10px">เข้าสู่ระบบ</button>
      </form>
      <div style="margin-top:16px;font-size:12px;color:var(--tx3);text-align:center">
        ทดสอบ: <code>head1</code> / <code>password</code>
      </div>
    </div>
  </div>
</div>
</div>
<script src="/rvc.rts/assets/js/app.js"></script>
</body>
</html>
