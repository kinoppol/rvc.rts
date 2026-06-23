<?php
require_once __DIR__ . '/functions.php';

function renderHead(string $title = ''): void {
    $appName = APP_NAME;
    $pageTitle = $title ? "$title — $appName" : $appName;
    echo <<<HTML
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$pageTitle}</title>
<link rel="stylesheet" href="/rvc.rts/assets/css/app.css">
</head>
HTML;
}

function renderShell(array $user, string $page, string $pageTitle, callable $content): void {
    renderHead($pageTitle);

    $logo = '/rvc.rts/project/uploads/logo-1782108162561.png';
    $schoolName = SCHOOL_NAME;
    $appName = APP_NAME;
    $av = e(avatarChars($user['name']));
    $uname = e($user['name']);
    $urole = USER_ROLES[$user['role']]['l'] ?? $user['role'];
    $udept = e($user['dept']);

    // Nav items: [page_key, icon, label, badge_query]
    $navItems = [
        ['dashboard', '🏠', 'หน้าหลัก', null],
    ];

    // Count badges
    require_once __DIR__ . '/db.php';
    $annCount  = (int)fetchValue("SELECT COUNT(*) FROM documents_in WHERE status='pending_annotation'");
    $taskCount = (int)fetchValue("SELECT COUNT(*) FROM tasks WHERE status != 'done'");

    $navGroups = [
        'สารบรรณ' => [
            ['register', '📋', 'ทะเบียนหนังสือรับ-ส่ง', null],
            ['newdoc',   '📥', 'รับเอกสารใหม่', null],
            ['annotate', '✏️', 'เกษียนหนังสือ', $annCount ?: null],
        ],
        'การมอบหมาย' => [
            ['route',  '📤', 'เสนอ / มอบหมาย', null],
            ['tasks',  '📊', 'ติดตามงาน', $taskCount ?: null],
            ['reply',  '↩️', 'เกษียนตอบ / รายงาน', null],
        ],
        'องค์กร' => [
            ['orgchart', '🏢', 'โครงสร้างองค์กร', null],
            ['users',    '👥', 'จัดการผู้ใช้งาน', null],
        ],
        'ระบบ' => array_filter([
            ['settings', '⚙️', 'ตั้งค่าระบบ', null],
            $user['role'] === 'admin' ? ['admin', '🔧', 'จัดการระบบ (Admin)', null] : null,
        ]),
    ];

    echo <<<HTML
<body>
<div class="rts" data-theme="light">
<div class="wrap">
<aside class="sb" id="sidebar">
  <div class="sb-brand">
    <img class="sb-logo" src="{$logo}" alt="logo" onerror="this.style.display='none'">
    <div class="sb-txt">
      <div class="sb-name">{$schoolName}</div>
      <div class="sb-sub">{$appName}</div>
    </div>
  </div>
  <nav class="sb-nav">
HTML;

    // Dashboard
    $cls = $page === 'dashboard' ? 'si act' : 'si';
    $bdg = $taskCount ? "<span class=\"si-bdg\">{$taskCount}</span>" : '<span class="si-bdg"></span>';
    echo "<a class=\"{$cls}\" href=\"/rvc.rts/?page=dashboard\"><span class=\"si-ico\">🏠</span><span class=\"si-lbl\">หน้าหลัก</span>{$bdg}</a>\n";

    foreach ($navGroups as $groupName => $items) {
        echo "<div class=\"sb-sec\">" . e($groupName) . "</div>\n";
        foreach ($items as [$key, $icon, $label, $badge]) {
            $cls = $page === $key ? 'si act' : 'si';
            $bdgHtml = $badge ? "<span class=\"si-bdg\">{$badge}</span>" : '<span class="si-bdg"></span>';
            echo "<a class=\"{$cls}\" href=\"/rvc.rts/?page={$key}\"><span class=\"si-ico\">{$icon}</span><span class=\"si-lbl\">" . e($label) . "</span>{$bdgHtml}</a>\n";
        }
    }

    echo <<<HTML
  </nav>
  <div class="sb-foot">
    <div class="sb-av">{$av}</div>
    <div class="sb-ui">
      <div class="sb-uname">{$uname}</div>
      <div class="sb-urole">{$udept}</div>
    </div>
  </div>
</aside>

<div class="main" id="main-content">
  <header class="hdr">
    <div class="htgl" id="sb-toggle">☰</div>
    <div class="hbc">บริหารงานสถานศึกษา / <strong>{$pageTitle}</strong></div>
    <div class="hact">
      <button class="hbtn" id="theme-btn" title="เปลี่ยนธีม">☀️</button>
      <button class="hbtn" title="การแจ้งเตือน">🔔<span class="ndot"></span></button>
      <div class="uav" title="{$uname}">{$av}</div>
      <button class="hbtn" onclick="openModal('logout-modal')" title="ออกจากระบบ">🚪</button>
    </div>
  </header>
  <div class="pg" id="page-content">
HTML;

    $content();

    echo <<<HTML
  </div>
</div>
</div>
</div>
<div id="toast"></div>

<!-- Logout confirm modal -->
<div class="ov hidden" id="logout-modal">
  <div class="mdl" style="max-width:360px;text-align:center">
    <div class="mb" style="padding:32px 24px 20px">
      <div style="font-size:48px;margin-bottom:12px">🚪</div>
      <div style="font-size:16px;font-weight:700;margin-bottom:8px">ออกจากระบบ</div>
      <div style="font-size:13.5px;color:var(--tx2)">คุณต้องการออกจากระบบใช่หรือไม่?</div>
    </div>
    <div class="mf" style="justify-content:center;gap:12px">
      <button class="btn bg" onclick="closeModal('logout-modal')">ยกเลิก</button>
      <a class="btn ber" href="/rvc.rts/logout.php">ออกจากระบบ</a>
    </div>
  </div>
</div>

HTML;
    renderDocModal();
    echo <<<HTML
<script src="/rvc.rts/assets/js/app.js"></script>
</body>
</html>
HTML;
}

function renderDocModal(): void {
    echo <<<'HTML'
<!-- Document detail modal -->
<div class="ov hidden" id="doc-modal">
  <div class="mdl">
    <div class="mh">
      <div>
        <span class="mt" id="dm-title"></span>
        <div class="fx g2x" style="margin-top:5px" id="dm-badges"></div>
      </div>
      <button class="mx" onclick="closeModal('doc-modal')">×</button>
    </div>
    <div class="mb">
      <div class="g2 mb3">
        <div><div style="font-size:11px;color:var(--tx2);margin-bottom:2px">วันที่รับ</div><div style="font-weight:600" id="dm-date"></div></div>
        <div><div style="font-size:11px;color:var(--tx2);margin-bottom:2px">จาก</div><div id="dm-from"></div></div>
      </div>
      <div class="mb4"><div style="font-size:11px;color:var(--tx2);margin-bottom:2px">เรื่อง</div><div style="font-weight:500" id="dm-subj"></div></div>
      <div class="tl">
        <div class="tli">
          <div class="tld" id="dm-tl-ann"></div>
          <div class="tlt">งานบริหารงานทั่วไป — เกษียนหนังสือ</div>
          <div class="tln" style="margin-top:5px" id="dm-ann-row"><div class="an-lbl">เกษียน</div><div id="dm-ann-txt"></div></div>
        </div>
        <div class="tli">
          <div class="tld" id="dm-tl-dep"></div>
          <div class="tlt">รองฯ ฝ่ายบริหารทรัพยากร — เพิ่มความเห็น</div>
          <div class="tln" style="margin-top:5px" id="dm-dep-row"><div id="dm-dep-txt"></div></div>
        </div>
        <div class="tli">
          <div class="tld" id="dm-tl-dir"></div>
          <div class="tlt">ผู้อำนวยการ — พิจารณา / มอบหมาย</div>
          <div class="tln" style="margin-top:5px" id="dm-dir-row"><div id="dm-dir-txt"></div></div>
        </div>
        <div class="tli">
          <div class="tld" id="dm-tl-rep"></div>
          <div class="tlt">ฝ่าย/งานที่รับผิดชอบ — เกษียนตอบ</div>
          <div class="tln" style="margin-top:5px" id="dm-rep-row"><div id="dm-rep-txt"></div></div>
        </div>
      </div>
      <div class="chips mt4" id="dm-depts"></div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('doc-modal')">ปิด</button>
      <a class="btn bp" id="dm-annot-btn" href="#">✏️ เกษียน</a>
    </div>
  </div>
</div>
HTML;
}
