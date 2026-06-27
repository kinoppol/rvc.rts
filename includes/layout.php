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
    require_once __DIR__ . '/auth.php';
    $isImpersonating = is_impersonating();
    renderHead($pageTitle);

    $logo = '/rvc.rts/project/uploads/logo-1782108162561.png';
    $schoolName = SCHOOL_NAME;
    $appName = APP_NAME;
    $av    = e(avatarChars($user['name']));
    $avImg = avatarHtml($user, '32px', '11px');
    $avImgHdr = avatarHtml($user, '34px', '12px');
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
        ['📁', 'สารบรรณ', [
            ['register', '📋', 'ทะเบียนหนังสือรับ-ส่ง', null],
            ['newdoc',   '📥', 'รับเอกสารใหม่', null],
            ['annotate', '✏️', 'เกษียนหนังสือ', $annCount ?: null],
        ]],
        ['📌', 'การมอบหมาย', [
            ['route',  '📤', 'เสนอ / มอบหมาย', null],
            ['tasks',  '📊', 'ติดตามงาน', $taskCount ?: null],
            ['reply',  '↩️', 'เกษียนตอบ / รายงาน', null],
        ]],
        ['🏛️', 'องค์กร', [
            ['orgchart', '🏢', 'โครงสร้างองค์กร', null],
        ]],
        ['⚙️', 'ระบบ', array_values(array_filter([
            ['settings', '⚙️', 'ตั้งค่าระบบ', null],
            $user['role'] === 'admin' ? ['users',  '👥', 'จัดการผู้ใช้งาน', null] : null,
            $user['role'] === 'admin' ? ['import', '🔄', 'โอนข้อมูลบุคลากร', null] : null,
            $user['role'] === 'admin' ? ['admin',  '🔧', 'จัดการระบบ (Admin)', null] : null,
        ]))],
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

    // Dashboard (standalone — always visible)
    $cls = $page === 'dashboard' ? 'si act' : 'si';
    $bdg = $taskCount ? "<span class=\"si-bdg\">{$taskCount}</span>" : '<span class="si-bdg"></span>';
    echo "<a class=\"{$cls}\" href=\"/rvc.rts/?page=dashboard\"><span class=\"si-ico\">🏠</span><span class=\"si-lbl\">หน้าหลัก</span>{$bdg}</a>\n";

    // Collapsible groups
    $gIdx = 0;
    foreach ($navGroups as [$gIcon, $groupName, $items]) {
        $groupActive = false;
        $groupBadgeTotal = 0;
        foreach ($items as [$key, $icon, $label, $badge]) {
            if ($page === $key) $groupActive = true;
            $groupBadgeTotal += (int)$badge;
        }
        $openAttr = $groupActive ? ' open' : '';
        $gId      = 'sg-' . $gIdx++;
        $gBdgHtml = $groupBadgeTotal ? "<span class=\"sg-bdg\">{$groupBadgeTotal}</span>" : '';

        echo "<details class=\"sb-grp\"{$openAttr} id=\"{$gId}\">\n";
        echo "  <summary class=\"sb-sec-toggle\">\n";
        echo "    <span class=\"sb-grp-ico\">{$gIcon}</span>\n";
        echo "    <span class=\"sb-sec-lbl\">" . e($groupName) . "</span>\n";
        echo "    {$gBdgHtml}\n";
        echo "    <span class=\"sb-chv\"></span>\n";
        echo "  </summary>\n";
        echo "  <div class=\"sb-grp-items\">\n";
        foreach ($items as [$key, $icon, $label, $badge]) {
            $cls     = $page === $key ? 'si act' : 'si';
            $bdgHtml = $badge ? "<span class=\"si-bdg\">{$badge}</span>" : '<span class="si-bdg"></span>';
            echo "    <a class=\"{$cls}\" href=\"/rvc.rts/?page={$key}\"><span class=\"si-ico\">{$icon}</span><span class=\"si-lbl\">" . e($label) . "</span>{$bdgHtml}</a>\n";
        }
        echo "  </div>\n";
        echo "</details>\n";
    }

    echo <<<HTML
  </nav>
  <div class="sb-foot">
    <div class="sb-av">{$avImg}</div>
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
      <div class="uav" title="{$uname}">{$avImgHdr}</div>
      <button class="hbtn" onclick="openModal('logout-modal')" title="ออกจากระบบ">🚪</button>
    </div>
  </header>
  <div class="pg" id="page-content">
HTML;
    if ($isImpersonating) {
        $adminUser = fetchOne('SELECT name FROM users WHERE id=?', [$_SESSION['impersonate_admin_id']]);
        $adminName = e($adminUser['name'] ?? 'Admin');
        echo <<<HTML
<div style="position:sticky;top:0;z-index:500;background:#92400e;color:#fff;padding:8px 16px;display:flex;align-items:center;gap:12px;font-size:13px;font-weight:500">
  <span>👁️ กำลังสวมสิทธิ์ในนาม <strong>{$uname}</strong> — ล็อกอินจริงคือ <strong>{$adminName}</strong></span>
  <a href="/rvc.rts/logout.php" style="margin-left:auto;background:rgba(255,255,255,.2);color:#fff;padding:4px 14px;border-radius:20px;text-decoration:none;font-size:12.5px">↩ กลับเป็น Admin</a>
</div>
HTML;
    }

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
    echo <<<'HTML'
<div class="ov ov-fs hidden" id="pdf-modal">
  <div class="mdl-fs">
    <div class="mdl-fs-hd">
      <span style="font-weight:600;flex:1;font-size:14px" id="pdf-modal-title">ไฟล์แนบ</span>
      <a id="pdf-modal-dl" href="#" download style="font-size:12px;color:var(--p);text-decoration:none;margin-right:8px">⬇️ ดาวน์โหลด</a>
      <button class="mx" onclick="closePdfModal()" style="font-size:22px;line-height:1;background:none;border:none;cursor:pointer;color:var(--tx2)">×</button>
    </div>
    <div class="mdl-fs-body">
      <iframe id="pdf-modal-frame" src="" style="width:100%;height:100%;border:none;display:block"></iframe>
    </div>
  </div>
</div>
HTML;
    $meJson = json_encode(['id' => (int)$user['id'], 'role' => $user['role']]);
    echo <<<HTML
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js" crossorigin="anonymous"></script>
<script>if(window.pdfjsLib) pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
<script>window.__ME={$meJson};</script>
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
          <div class="tlt" style="display:flex;align-items:center;gap:8px">
            <span>งานบริหารงานทั่วไป — เกษียนหนังสือ</span>
            <button id="dm-ann-edit-btn" style="display:none;background:none;border:1px solid var(--bd);border-radius:var(--r2);padding:1px 8px;font-size:11px;cursor:pointer;color:var(--tx2)" onclick="toggleAnnotEdit()">✏️ แก้ไข</button>
          </div>
          <div class="tln" style="margin-top:5px" id="dm-ann-row">
            <div class="an-lbl">เกษียน</div>
            <div id="dm-ann-txt"></div>
            <div id="dm-ann-edit-area" style="display:none;margin-top:8px">
              <textarea id="dm-ann-edit-txt" class="fc" style="min-height:80px;font-size:13px"></textarea>
              <div class="fx g2x" style="margin-top:6px;justify-content:flex-end">
                <button class="btn bg bsm" onclick="toggleAnnotEdit()">ยกเลิก</button>
                <button class="btn bp bsm" onclick="saveAnnotEdit()">💾 บันทึก</button>
              </div>
            </div>
          </div>
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
      <div id="dm-files-row" style="display:none;margin-top:14px">
        <div style="font-size:11px;color:var(--tx2);margin-bottom:6px">ไฟล์แนบ</div>
        <div id="dm-files" class="fx" style="flex-wrap:wrap;gap:6px"></div>
      </div>
      <div class="chips mt4" id="dm-depts"></div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('doc-modal')">ปิด</button>
      <button class="btn bo" id="dm-edit-btn" onclick="openEditDocModal()" style="display:none">✏️ แก้ไขข้อมูล</button>
      <a class="btn bp" id="dm-annot-btn" href="#">📝 เกษียน</a>
    </div>
  </div>
</div>

<div class="ov hidden" id="edit-doc-modal">
  <div class="mdl">
    <div class="mh">
      <span class="mt">แก้ไขข้อมูลเอกสาร</span>
      <button class="mx" onclick="closeModal('edit-doc-modal')">×</button>
    </div>
    <div class="mb">
      <input type="hidden" id="edm-id">
      <div class="fg mb3">
        <label class="fl">วันที่รับ <span class="req">*</span></label>
        <input class="fc" type="date" id="edm-date">
      </div>
      <div class="g2 mb3">
        <div class="fg">
          <label class="fl">จากหน่วยงาน <span class="req">*</span></label>
          <input class="fc" id="edm-from-org" placeholder="ชื่อหน่วยงาน">
        </div>
        <div class="fg">
          <label class="fl">ชื่อย่อ</label>
          <input class="fc" id="edm-from-short" placeholder="เช่น สอศ.">
        </div>
      </div>
      <div class="fg mb3">
        <label class="fl">เรื่อง <span class="req">*</span></label>
        <input class="fc" id="edm-subject" placeholder="ระบุเรื่อง">
      </div>
      <div class="g2 mb3">
        <div class="fg">
          <label class="fl">ประเภทหนังสือ</label>
          <select class="fc" id="edm-doctype">
            <option value="ราชการ">หนังสือราชการ</option>
            <option value="เอกชน">หนังสือเอกชน</option>
            <option value="บุคคล">จากบุคคลทั่วไป</option>
          </select>
        </div>
        <div class="fg">
          <label class="fl">จำนวนหน้า</label>
          <input class="fc" type="number" id="edm-pages" min="0">
        </div>
      </div>
      <div class="g2 mb3">
        <div class="fg">
          <label class="fl">ความเร่งด่วน</label>
          <select class="fc" id="edm-urgency">
            <option value="normal">ปกติ</option>
            <option value="urgent">เร่งด่วน</option>
            <option value="critical">ด่วนที่สุด</option>
          </select>
        </div>
        <div class="fg">
          <label class="fl">ระดับความลับ</label>
          <select class="fc" id="edm-secrecy">
            <option value="none">ไม่ลับ</option>
            <option value="secret">ลับ</option>
            <option value="top_secret">ลับที่สุด</option>
          </select>
        </div>
      </div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('edit-doc-modal')">ยกเลิก</button>
      <button class="btn bp" onclick="saveEditDoc()">💾 บันทึก</button>
    </div>
  </div>
</div>
HTML;
}
