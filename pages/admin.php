<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Only admin
$_me = current_user();
if (!$_me || $_me['role'] !== 'admin') {
    echo '<div class="alrt ae"><span>🚫</span><span>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</span></div>';
    return;
}

$action  = $_POST['action']  ?? '';
$msg     = '';
$msgType = 'ai';

// ── Handle actions ─────────────────────────────────────────────────────
if ($action === 'reset_doc_number') {
    $prefix = trim($_POST['prefix'] ?? 'รบ');
    $start  = max(1, (int)($_POST['start'] ?? 1));
    $year   = (int)($_POST['year'] ?? (date('Y') + 543));
    $seqVal = $start - 1;
    query("INSERT INTO settings (setting_key,setting_value) VALUES ('doc_seq',?) ON DUPLICATE KEY UPDATE setting_value=?",    [$seqVal, $seqVal]);
    query("INSERT INTO settings (setting_key,setting_value) VALUES ('doc_prefix',?) ON DUPLICATE KEY UPDATE setting_value=?", [$prefix, $prefix]);
    query("INSERT INTO settings (setting_key,setting_value) VALUES ('doc_year',?) ON DUPLICATE KEY UPDATE setting_value=?",   [$year, $year]);
    $msg = "ตั้งค่าเลขที่เอกสารเรียบร้อย — ถัดไปจะเป็น {$prefix}/{$start}/{$year}";
    $msgType = 'ai';
}

if ($action === 'clear_docs') {
    if ($_POST['confirm'] === 'DELETE') {
        query('DELETE FROM document_departments');
        query('DELETE FROM tasks');
        query('DELETE FROM documents_in');
        query('DELETE FROM documents_out');
        query("INSERT INTO settings (setting_key,setting_value) VALUES ('doc_seq','0') ON DUPLICATE KEY UPDATE setting_value='0'");
        $msg = '✅ ล้างข้อมูลเอกสารและงานทั้งหมดเรียบร้อยแล้ว';
        $msgType = 'ai';
    } else {
        $msg = '❌ ยืนยันไม่ถูกต้อง — พิมพ์ DELETE เพื่อยืนยัน';
        $msgType = 'ae';
    }
}

if ($action === 'add_role_column') {
    // Alter users table to add 'admin' if not already in ENUM
    try {
        db()->exec("ALTER TABLE users MODIFY role ENUM('admin','director','deputy','head','dept_head','teacher','staff') NOT NULL DEFAULT 'staff'");
        $msg = '✅ อัปเดต ENUM role ในตาราง users เรียบร้อย';
        $msgType = 'ai';
    } catch (\Exception $e) {
        $msg = 'ข้อผิดพลาด: ' . $e->getMessage();
        $msgType = 'ae';
    }
}

if ($action === 'create_admin') {
    $uname = trim($_POST['adm_user'] ?? '');
    $pw    = $_POST['adm_pw'] ?? '';
    $name  = trim($_POST['adm_name'] ?? '');
    if (!$uname || !$pw || !$name) {
        $msg = 'กรุณากรอกข้อมูลให้ครบ'; $msgType = 'ae';
    } elseif (fetchValue('SELECT id FROM users WHERE username=?', [$uname])) {
        $msg = 'ชื่อผู้ใช้นี้มีในระบบแล้ว'; $msgType = 'ae';
    } else {
        insert('users', [
            'username' => $uname,
            'password' => password_hash($pw, PASSWORD_BCRYPT),
            'name'     => $name,
            'title'    => 'ผู้ดูแลระบบ',
            'role'     => 'admin',
            'dept'     => 'ฝ่ายเทคโนโลยีสารสนเทศ',
            'active'   => 1,
        ]);
        $msg = "✅ สร้างบัญชี Admin \"$uname\" เรียบร้อยแล้ว";
        $msgType = 'ai';
    }
}

// ── Stats ──────────────────────────────────────────────────────────────
$stats = [
    'users'    => (int)fetchValue('SELECT COUNT(*) FROM users'),
    'docs_in'  => (int)fetchValue('SELECT COUNT(*) FROM documents_in'),
    'docs_out' => (int)fetchValue('SELECT COUNT(*) FROM documents_out'),
    'tasks'    => (int)fetchValue('SELECT COUNT(*) FROM tasks'),
    'settings' => (int)fetchValue('SELECT COUNT(*) FROM settings'),
];

$adminUsers = fetchAll("SELECT id,username,name,active,created_at FROM users WHERE role='admin' ORDER BY id");

// ── User management data ───────────────────────────────────────────────
$allUsers   = fetchAll("SELECT id,username,name,nickname,title,role,extra_roles,dept,email,avatar,active,created_at FROM users ORDER BY FIELD(role,'admin','director','deputy','head','dept_head','teacher','staff'), name");
$roleStats  = fetchAll("SELECT role, COUNT(*) as cnt FROM users WHERE active=1 GROUP BY role");
$roleStatsMap = [];
foreach ($roleStats as $r) $roleStatsMap[$r['role']] = (int)$r['cnt'];
$inactiveCount = (int)fetchValue("SELECT COUNT(*) FROM users WHERE active=0");

$lastDoc = fetchValue("SELECT doc_number FROM documents_in ORDER BY id DESC LIMIT 1") ?: '-';

// Load doc sequence settings
$docSettings = fetchAll("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('doc_prefix','doc_year','doc_seq')");
$docCfg = array_column($docSettings, 'setting_value', 'setting_key');
$curPrefix = $docCfg['doc_prefix'] ?? 'รบ';
$curYear   = (int)($docCfg['doc_year'] ?? (date('Y') + 543));
$curSeq    = (int)($docCfg['doc_seq'] ?? 0) + 1;

// Check if admin ENUM exists
try {
    $colInfo = fetchOne("SHOW COLUMNS FROM users LIKE 'role'");
    $enumHasAdmin = str_contains($colInfo['Type'] ?? '', 'admin');
} catch (\Exception $e) {
    $enumHasAdmin = false;
}

// PHP / MariaDB info
$phpVer    = PHP_VERSION;
$mariaVer  = fetchValue('SELECT VERSION()');
$diskFree  = disk_free_space(__DIR__ . '/../uploads') ?: 0;
$diskTotal = disk_total_space(__DIR__ . '/../uploads') ?: 1;
$diskPct   = round((1 - $diskFree / $diskTotal) * 100);
$uploadSize = array_sum(array_map('filesize', glob(__DIR__ . '/../uploads/documents/*') ?: []));
?>

<div class="pgh fxab">
  <div>
    <div class="pgt">🔧 จัดการระบบ (Admin)</div>
    <div class="pgs">เฉพาะผู้ดูแลระบบเท่านั้น — จัดการข้อมูลเบื้องหลังและการตั้งค่าขั้นสูง</div>
  </div>
  <span class="bdg bg-r" style="font-size:12px;padding:5px 12px">🔒 Admin Only</span>
</div>

<?php if ($msg): ?>
<div class="alrt <?= $msgType ?> mb4"><span><?= $msgType==='ae'?'❌':'✅' ?></span><span><?= e($msg) ?></span></div>
<?php endif ?>

<!-- ── System stats ─────────────────────────────────────── -->
<div class="stg mb4" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
  <?php
  $cards = [
    ['ผู้ใช้งาน',     $stats['users'],    '#ede9fe','#7c3aed','👤'],
    ['หนังสือรับ',    $stats['docs_in'],  '#dbeafe','#1d4ed8','📥'],
    ['หนังสือส่ง',   $stats['docs_out'], '#fef9c3','#ca8a04','📤'],
    ['งานทั้งหมด',   $stats['tasks'],    '#dcfce7','#15803d','📋'],
    ['การตั้งค่า',   $stats['settings'], '#ffedd5','#c2410c','⚙️'],
  ];
  foreach ($cards as [$l,$v,$bg,$col,$ic]):
  ?>
  <div class="stc">
    <div class="sti" style="background:<?= $bg ?>"><?= $ic ?></div>
    <div class="fxc"><div class="stv" style="color:<?= $col ?>"><?= $v ?></div><div class="stl"><?= e($l) ?></div></div>
  </div>
  <?php endforeach ?>
</div>

<div class="g2 mb4">

  <!-- ── Server info ──────────────────────────────────── -->
  <div class="card">
    <div class="ch"><span class="ct">🖥 ข้อมูลเซิร์ฟเวอร์</span></div>
    <div class="cb">
      <table>
        <tbody>
          <tr><td style="color:var(--tx2);font-size:12px;width:140px">PHP Version</td><td><span class="bdg bg-g"><?= e($phpVer) ?></span></td></tr>
          <tr><td style="color:var(--tx2);font-size:12px">MariaDB/MySQL</td><td><span class="bdg bg-b"><?= e($mariaVer) ?></span></td></tr>
          <tr><td style="color:var(--tx2);font-size:12px">ฐานข้อมูล</td><td><code style="font-size:12px"><?= e(DB_NAME) ?></code></td></tr>
          <tr><td style="color:var(--tx2);font-size:12px">เลขที่เอกสารล่าสุด</td><td><strong style="color:var(--p)"><?= e($lastDoc) ?></strong></td></tr>
          <tr><td style="color:var(--tx2);font-size:12px">พื้นที่ Uploads ใช้ไป</td><td><?= round($uploadSize/1024/1024, 2) ?> MB</td></tr>
          <tr><td style="color:var(--tx2);font-size:12px">Disk ว่าง</td><td><?= round($diskFree/1024/1024/1024, 1) ?> GB (ใช้ <?= $diskPct ?>%)</td></tr>
          <tr><td style="color:var(--tx2);font-size:12px">ENUM role (admin)</td>
              <td><?= $enumHasAdmin ? '<span class="bdg bg-g">✅ รองรับแล้ว</span>' : '<span class="bdg bg-r">❌ ยังไม่อัปเดต</span>' ?></td></tr>
        </tbody>
      </table>
      <?php if (!$enumHasAdmin): ?>
      <div class="dv"></div>
      <form method="post">
        <input type="hidden" name="action" value="add_role_column">
        <button class="btn bwn bsm" type="submit">⚡ อัปเดต ENUM role ให้รองรับ admin</button>
      </form>
      <?php endif ?>
    </div>
  </div>

  <!-- ── Admin accounts ───────────────────────────────── -->
  <div class="card">
    <div class="ch"><span class="ct">👤 บัญชีผู้ดูแลระบบ</span></div>
    <div class="cb">
      <?php if ($adminUsers): ?>
        <table style="margin-bottom:14px">
          <thead><tr><th>#</th><th>Username</th><th>ชื่อ</th><th>สถานะ</th></tr></thead>
          <tbody>
          <?php foreach ($adminUsers as $i => $u): ?>
            <tr>
              <td style="font-size:12px;color:var(--tx3)"><?= $i+1 ?></td>
              <td><code style="font-size:12px"><?= e($u['username']) ?></code></td>
              <td><?= e($u['name']) ?></td>
              <td><?= $u['active'] ? badge('bg-g','ใช้งาน') : badge('bg-s','ระงับ') ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="alrt aw mb3"><span>⚠️</span><span>ยังไม่มีบัญชี Admin ในระบบ</span></div>
      <?php endif ?>
      <div class="dv"></div>
      <div style="font-size:13px;font-weight:600;margin-bottom:10px">➕ เพิ่มบัญชี Admin ใหม่</div>
      <form method="post">
        <input type="hidden" name="action" value="create_admin">
        <div class="g2">
          <div class="fg"><label class="fl">Username <span class="req">*</span></label><input class="fc" name="adm_user" placeholder="admin2"></div>
          <div class="fg"><label class="fl">รหัสผ่าน <span class="req">*</span></label><input class="fc" type="password" name="adm_pw"></div>
        </div>
        <div class="fg"><label class="fl">ชื่อ-สกุล <span class="req">*</span></label><input class="fc" name="adm_name" placeholder="ชื่อผู้ดูแลระบบ"></div>
        <button class="btn bp bsm" type="submit">✅ สร้างบัญชี Admin</button>
      </form>
    </div>
  </div>
</div>

<div class="g2 mb4">

  <!-- ── Doc number sequence ──────────────────────────── -->
  <div class="card">
    <div class="ch"><span class="ct">🔢 ลำดับเลขที่เอกสาร</span></div>
    <div class="cb">
      <div class="alrt ai mb3"><span>ℹ️</span><span>ตั้งค่าเลขที่หนังสือรับฉบับถัดไป</span></div>
      <form method="post">
        <input type="hidden" name="action" value="reset_doc_number">
        <div class="g2">
          <div class="fg"><label class="fl">คำนำหน้า (Prefix)</label><input class="fc" name="prefix" value="<?= e($curPrefix) ?>" placeholder="รบ"></div>
          <div class="fg"><label class="fl">ปี พ.ศ.</label><input class="fc" name="year" type="number" value="<?= $curYear ?>"></div>
        </div>
        <div class="fg"><label class="fl">เริ่มต้นที่เลขที่ <span style="font-size:11.5px;color:var(--tx2)">(ปัจจุบันถัดไปคือ <?= $curSeq ?>)</span></label><input class="fc" name="start" type="number" value="<?= $curSeq ?>" min="1"></div>
        <button class="btn bp bsm" type="submit">💾 บันทึก</button>
      </form>
    </div>
  </div>

  <!-- ── Danger zone ───────────────────────────────────── -->
  <div class="card" style="border-color:var(--er)">
    <div class="ch" style="background:#fff1f2"><span class="ct" style="color:var(--er)">⚠️ Danger Zone</span></div>
    <div class="cb">
      <div class="alrt ae mb3"><span>⚠️</span><span>การดำเนินการนี้ไม่สามารถย้อนกลับได้ กรุณาระวัง</span></div>
      <div style="font-size:13px;font-weight:600;margin-bottom:10px;color:var(--er)">🗑 ล้างข้อมูลเอกสารทั้งหมด</div>
      <p style="font-size:12.5px;color:var(--tx2);margin:0 0 12px">จะลบ documents_in, documents_out, tasks, document_departments ทั้งหมด (ไม่ลบผู้ใช้)</p>
      <form method="post" onsubmit="return confirm('แน่ใจหรือไม่? ข้อมูลทั้งหมดจะหายไปถาวร')">
        <input type="hidden" name="action" value="clear_docs">
        <div class="fg"><label class="fl">พิมพ์ <strong>DELETE</strong> เพื่อยืนยัน</label><input class="fc" name="confirm" placeholder="DELETE" style="border-color:var(--er)"></div>
        <button class="btn ber bsm" type="submit">🗑 ล้างข้อมูลทั้งหมด</button>
      </form>
    </div>
  </div>
</div>

<!-- ── RMS Import ────────────────────────────────────────────────────── -->
<?php
$rmsBaseUrl = fetchValue("SELECT setting_value FROM settings WHERE setting_key='rms_base_url'") ?: '';
$rmsFullUrl = $rmsBaseUrl ? rtrim($rmsBaseUrl, '/') . '/api_connection.php?app_name=nutty&data=people' : '(ยังไม่ได้ตั้งค่า)';
?>
<div class="card mb4">
  <div class="ch"><span class="ct">🔄 โอนข้อมูลผู้ใช้จากระบบ RMS</span></div>
  <div class="cb">
    <div class="g2 mb4" style="align-items:start">
      <div>
        <div style="font-size:12.5px;color:var(--tx2);margin-bottom:6px">แหล่งข้อมูล</div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <code style="font-size:12px;background:var(--sur2);padding:5px 10px;border-radius:var(--r2);border:1px solid var(--bd);word-break:break-all"><?= e($rmsFullUrl) ?></code>
          <?php if ($rmsBaseUrl): ?>
            <span class="bdg bg-g">✅ ตั้งค่าแล้ว</span>
          <?php else: ?>
            <a href="/rvc.rts/?page=settings" class="bdg bg-r" style="text-decoration:none">⚠️ ยังไม่ได้ตั้งค่า → ไปตั้งค่า</a>
          <?php endif ?>
        </div>
      </div>
      <div>
        <div style="font-size:12.5px;color:var(--tx2);margin-bottom:6px">เงื่อนไขการโอน</div>
        <ul style="font-size:12.5px;color:var(--tx2);margin:0;padding-left:16px;line-height:1.9">
          <li>โอนเฉพาะผู้ที่ <code>people_exit = 0</code></li>
          <li><code>people_id</code> → username | <code>people_name + people_surname</code> → ชื่อ</li>
          <li><code>ath_pass</code> → รหัสผ่าน (bcrypt) | <code>people_email</code> → อีเมล</li>
          <li>ผู้ใช้ที่มีอยู่แล้ว: อัปเดตชื่อ/อีเมล ไม่รีเซ็ต created_at</li>
        </ul>
      </div>
    </div>

    <div id="rms-result" style="display:none" class="mb4"></div>

    <div class="fx g3x">
      <button class="btn bo" id="rms-test-btn" onclick="rmsTest()" <?= !$rmsBaseUrl?'disabled':'' ?>>
        🔍 ทดสอบการเชื่อมต่อ
      </button>
      <button class="btn bp" id="rms-import-btn" onclick="rmsImport()" <?= !$rmsBaseUrl?'disabled':'' ?>>
        🔄 โอนข้อมูลเดี๋ยวนี้
      </button>
    </div>
  </div>
</div>

<script>
async function rmsTest() {
    const btn = document.getElementById('rms-test-btn');
    btn.disabled = true; btn.textContent = '⏳ กำลังเชื่อมต่อ...';
    const box = document.getElementById('rms-result');
    box.style.display = '';
    box.innerHTML = '<div class="alrt ai"><span>⏳</span><span>กำลังดึงข้อมูล...</span></div>';
    try {
        const res = await api('/rvc.rts/api/import_rms.php?preview=1');
        // preview mode: just show counts without importing
        box.innerHTML = `<div class="alrt ai">
            <div>
                <div style="font-weight:600;margin-bottom:6px">✅ เชื่อมต่อสำเร็จ — พบข้อมูล ${res.total} รายการ</div>
                <div style="font-size:12.5px">ผู้ใช้ที่ยังไม่ออก (people_exit=0): <strong>${res.active_count}</strong> คน
                 | มีในระบบแล้ว: <strong>${res.existing}</strong> | ใหม่: <strong>${res.new_count}</strong></div>
                <div style="font-size:12px;color:var(--tx2);margin-top:4px">แหล่งข้อมูล: ${res.source}</div>
            </div>
        </div>`;
    } catch(e) {
        box.innerHTML = `<div class="alrt ae"><span>❌</span><span>${e.message}</span></div>`;
    }
    btn.disabled = false; btn.textContent = '🔍 ทดสอบการเชื่อมต่อ';
}

async function rmsImport() {
    if (!confirm('โอนข้อมูลผู้ใช้จาก RMS เข้าระบบใช่หรือไม่?')) return;
    const btn = document.getElementById('rms-import-btn');
    btn.disabled = true; btn.textContent = '⏳ กำลังโอนข้อมูล...';
    const box = document.getElementById('rms-result');
    box.style.display = '';
    box.innerHTML = '<div class="alrt ai"><span>⏳</span><span>กำลังโอนข้อมูล กรุณารอสักครู่...</span></div>';
    try {
        const res = await api('/rvc.rts/api/import_rms.php');
        let errHtml = '';
        if (res.errors?.length) {
            errHtml = `<div style="margin-top:8px;font-size:12px;color:var(--er)">${res.errors.slice(0,5).map(e=>'⚠ '+e).join('<br>')}</div>`;
        }
        box.innerHTML = `<div class="alrt ai">
            <div>
                <div style="font-weight:600;margin-bottom:6px">✅ โอนข้อมูลเสร็จสิ้น</div>
                <div style="font-size:13px">
                    เพิ่มใหม่ <strong style="color:var(--ok)">${res.added}</strong> คน &nbsp;|&nbsp;
                    อัปเดต <strong style="color:var(--p)">${res.updated}</strong> คน &nbsp;|&nbsp;
                    ข้าม <strong style="color:var(--tx2)">${res.skipped}</strong> รายการ
                </div>
                ${errHtml}
                <div style="font-size:11.5px;color:var(--tx2);margin-top:6px">แหล่งข้อมูล: ${res.source}</div>
            </div>
        </div>`;
        if (res.added > 0 || res.updated > 0) {
            toast(`โอนข้อมูลสำเร็จ: เพิ่ม ${res.added} อัปเดต ${res.updated}`, 'ok');
        }
    } catch(e) {
        box.innerHTML = `<div class="alrt ae"><span>❌</span><span>${e.message}</span></div>`;
    }
    btn.disabled = false; btn.textContent = '🔄 โอนข้อมูลเดี๋ยวนี้';
}
</script>

<!-- ── User management ──────────────────────────────────────────────── -->
<div class="card mb4">
  <div class="ch fxab">
    <span class="ct">👥 จัดการผู้ใช้งานทั้งหมด</span>
    <div class="fx g2x">
      <button class="btn bo bsm" onclick="openModal('bulk-modal')">📥 นำเข้า CSV</button>
      <button class="btn bp bsm" onclick="openAddUser()">➕ เพิ่มผู้ใช้</button>
    </div>
  </div>

  <!-- Role stats bar -->
  <div class="cb" style="padding-bottom:8px">
    <div class="fx g3x" style="flex-wrap:wrap">
      <?php foreach (USER_ROLES as $k => $v):
        $cnt = $roleStatsMap[$k] ?? 0;
        if (!$cnt) continue;
      ?>
        <div style="display:flex;align-items:center;gap:6px;background:var(--sur2);border-radius:var(--r2);padding:5px 10px;font-size:12.5px">
          <?= badge($v['c'], $v['l']) ?>
          <span style="font-weight:700;color:var(--tx)"><?= $cnt ?> คน</span>
        </div>
      <?php endforeach ?>
      <?php if ($inactiveCount): ?>
        <div style="display:flex;align-items:center;gap:6px;background:var(--sur2);border-radius:var(--r2);padding:5px 10px;font-size:12.5px">
          <?= badge('bg-s','ระงับ') ?> <span style="font-weight:700;color:var(--tx2)"><?= $inactiveCount ?> คน</span>
        </div>
      <?php endif ?>
    </div>
  </div>

  <!-- Search + bulk action bar -->
  <div class="cb" style="padding-top:0;padding-bottom:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input class="fc" id="adm-user-search" style="flex:1;min-width:200px" placeholder="🔍 ค้นหาชื่อ / username / ฝ่าย..." oninput="filterAdmUsers(this.value)">
    <div id="bulk-bar" style="display:none;align-items:center;gap:8px">
      <span id="bulk-count" style="font-size:13px;color:var(--tx2)">เลือก 0 คน</span>
      <button class="btn ber bsm" onclick="openBulkDelete()">🗑 ลบที่เลือก</button>
      <button class="btn bg bsm" onclick="clearSelection()">✕ ยกเลิก</button>
    </div>
  </div>

  <div style="overflow-x:auto">
    <table>
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" id="chk-all" onchange="toggleAllUsers(this.checked)" title="เลือกทั้งหมด"></th>
          <th>ชื่อ-สกุล</th><th>Username</th><th>ฝ่าย/สังกัด</th><th>บทบาท</th><th>สถานะ</th><th style="min-width:160px">จัดการ</th>
        </tr>
      </thead>
      <tbody id="adm-user-tbody">
      <?php foreach ($allUsers as $i => $u): ?>
        <tr data-search="<?= e(mb_strtolower($u['name'].$u['username'].$u['dept'],'UTF-8')) ?>" data-id="<?= (int)$u['id'] ?>" data-role="<?= e($u['role']) ?>">
          <td>
            <?php if ($u['role'] !== 'admin'): ?>
              <input type="checkbox" class="user-chk" value="<?= (int)$u['id'] ?>" onchange="onUserChk()">
            <?php endif ?>
          </td>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <?= avatarHtml($u, '30px', '11px') ?>
              <div>
                <div style="font-weight:600;font-size:13px"><?= e($u['name']) ?></div>
                <?php if ($u['title']): ?><div style="font-size:11.5px;color:var(--tx2)"><?= e($u['title']) ?></div><?php endif ?>
              </div>
            </div>
          </td>
          <td><code style="font-size:12px"><?= e($u['username']) ?></code></td>
          <td style="font-size:12px;color:var(--tx2)">
            <?php
              $depts = fetchAll('SELECT dep_name FROM user_departments WHERE user_id=? ORDER BY dep_name', [(int)$u['id']]);
              if ($depts): foreach ($depts as $d): ?>
                <span style="display:inline-block;background:var(--sur2);border:1px solid var(--bd);border-radius:20px;padding:1px 8px;margin:1px 2px;white-space:nowrap"><?= e($d['dep_name']) ?></span>
              <?php endforeach; else: ?>
                <?= e($u['dept']) ?>
              <?php endif ?>
          </td>
          <td>
            <?= roleBadge($u['role']) ?>
            <?php
              $extra = json_decode($u['extra_roles'] ?? '[]', true) ?: [];
              foreach ($extra as $er): echo roleBadge($er); endforeach;
            ?>
          </td>
          <td><?= $u['active'] ? badge('bg-g','ใช้งาน') : badge('bg-s','ระงับ') ?></td>
          <td>
            <div class="fx g2x" style="flex-wrap:wrap">
              <button class="btn bo bsm" onclick='admEditUser(<?= json_encode(['id'=>(int)$u['id'],'username'=>$u['username'],'name'=>$u['name'],'title'=>$u['title'],'role'=>$u['role'],'dept'=>$u['dept'],'email'=>$u['email'],'active'=>(int)$u['active']], JSON_UNESCAPED_UNICODE) ?>)' title="แก้ไข">✏️</button>
              <button class="btn bg bsm" onclick="admResetPwd(<?= (int)$u['id'] ?>, <?= json_encode($u['name'], JSON_UNESCAPED_UNICODE) ?>)" title="รีเซ็ตรหัสผ่าน">🔑</button>
              <?php if ($u['role'] !== 'admin'): ?>
                <button class="btn bg bsm" onclick="impersonate(<?= (int)$u['id'] ?>, <?= json_encode($u['name'], JSON_UNESCAPED_UNICODE) ?>)" title="สวมสิทธิ์">👁️</button>
                <?php if ($u['active']): ?>
                  <button class="btn bwn bsm" onclick="admToggle(<?= (int)$u['id'] ?>,0,this)" title="ระงับการใช้งาน">🚫 ระงับ</button>
                <?php else: ?>
                  <button class="btn bok bsm" onclick="admToggle(<?= (int)$u['id'] ?>,1,this)" title="เปิดใช้งาน">✅ เปิดใช้</button>
                <?php endif ?>
                <button class="btn ber bsm" onclick="admDelete(<?= (int)$u['id'] ?>, <?= json_encode($u['name'], JSON_UNESCAPED_UNICODE) ?>)" title="ลบออกจากระบบ">🗑</button>
              <?php endif ?>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Bulk delete confirm modal -->
<div class="ov hidden" id="bulk-delete-modal">
  <div class="mdl" style="max-width:420px;text-align:center">
    <div class="mb" style="padding:32px 28px 20px">
      <div style="width:64px;height:64px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 16px">🗑</div>
      <div style="font-size:17px;font-weight:700;color:var(--tx);margin-bottom:10px">ยืนยันการลบผู้ใช้</div>
      <div style="font-size:13.5px;color:var(--tx2);line-height:1.7">
        จะลบผู้ใช้ที่เลือกทั้งหมด <strong id="bulk-del-count" style="color:var(--er)">0</strong> คน<br>
        <span style="color:var(--er);font-size:12.5px;margin-top:8px;display:block">⚠️ การลบไม่สามารถกู้คืนได้</span>
      </div>
    </div>
    <div class="mf" style="justify-content:center;gap:12px;padding:16px 24px 24px">
      <button class="btn bg" style="min-width:100px" onclick="closeModal('bulk-delete-modal')">ยกเลิก</button>
      <button class="btn ber" style="min-width:140px" onclick="closeModal('bulk-delete-modal');doBulkDelete()">🗑 ลบเลย</button>
    </div>
  </div>
</div>

<!-- Single delete confirm modal -->
<div class="ov hidden" id="single-delete-modal">
  <div class="mdl" style="max-width:420px;text-align:center">
    <div class="mb" style="padding:32px 28px 20px">
      <div style="width:64px;height:64px;border-radius:50%;background:#fee2e2;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 16px">🗑</div>
      <div style="font-size:17px;font-weight:700;color:var(--tx);margin-bottom:10px">ยืนยันการลบผู้ใช้</div>
      <div style="font-size:13.5px;color:var(--tx2);line-height:1.8">
        จะลบ <strong id="single-del-name" style="color:var(--tx)"></strong> ออกจากระบบ<br>
        <span style="color:var(--er);font-size:12.5px;margin-top:8px;display:block">⚠️ การลบไม่สามารถกู้คืนได้</span>
      </div>
    </div>
    <div class="mf" style="justify-content:center;gap:12px;padding:16px 24px 24px">
      <button class="btn bg" style="min-width:100px" onclick="closeModal('single-delete-modal')">ยกเลิก</button>
      <button class="btn ber" style="min-width:120px" onclick="doSingleDelete()">🗑 ลบเลย</button>
    </div>
  </div>
</div>

<!-- Add/Edit user modal (admin) -->
<div class="ov hidden" id="adm-user-modal">
  <div class="mdl">
    <div class="mh">
      <span class="mt" id="adm-um-title">เพิ่มผู้ใช้งาน</span>
      <button class="mx" onclick="closeModal('adm-user-modal')">×</button>
    </div>
    <div class="mb">
      <input type="hidden" id="adm-um-id">
      <div class="g2">
        <div class="fg"><label class="fl">ชื่อผู้ใช้ <span class="req">*</span></label><input class="fc" id="adm-um-user" placeholder="ชื่อเข้าระบบ (ภาษาอังกฤษ)"></div>
        <div class="fg"><label class="fl">รหัสผ่าน</label><input class="fc" type="password" id="adm-um-pw" placeholder="เว้นว่างถ้าไม่เปลี่ยน"></div>
      </div>
      <div class="fg"><label class="fl">ชื่อ-สกุล <span class="req">*</span></label><input class="fc" id="adm-um-name"></div>
      <div class="fg"><label class="fl">ตำแหน่ง</label><input class="fc" id="adm-um-title2" placeholder="ครู / นักการเงิน ฯลฯ"></div>

      <div class="fg">
        <label class="fl">บทบาทหลัก <span class="req">*</span></label>
        <select class="fc" id="adm-um-role">
          <?php foreach (USER_ROLES as $k => $v): ?>
            <option value="<?= e($k) ?>"><?= e($v['l']) ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="fg">
        <label class="fl">บทบาทเพิ่มเติม</label>
        <div class="tag-wrap" id="role-tags" style="display:flex;flex-wrap:wrap;gap:6px;padding:6px 8px;border:1px solid var(--bd);border-radius:var(--r2);min-height:38px;cursor:text" onclick="document.getElementById('role-tag-input').focus()">
          <!-- tags rendered by JS -->
          <select id="role-tag-input" class="fc" style="border:none;padding:2px 4px;min-width:120px;flex:1;background:transparent;outline:none" onchange="addRoleTag(this.value);this.value=''">
            <option value="">+ เพิ่มบทบาท...</option>
            <?php foreach (USER_ROLES as $k => $v): ?>
              <option value="<?= e($k) ?>"><?= e($v['l']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>

      <div class="fg">
        <label class="fl">ฝ่าย/สังกัด</label>
        <div class="tag-wrap" id="dept-tags" style="display:flex;flex-wrap:wrap;gap:6px;padding:6px 8px;border:1px solid var(--bd);border-radius:var(--r2);min-height:38px;cursor:text" onclick="document.getElementById('dept-tag-input').focus()">
          <!-- tags rendered by JS -->
          <div style="position:relative;flex:1;min-width:140px">
            <input id="dept-tag-input" class="fc" style="border:none;padding:2px 4px;background:transparent;outline:none;width:100%" placeholder="พิมพ์แล้วกด Enter หรือเลือกจากรายการ..." oninput="deptTagSearch(this.value)" onkeydown="deptTagKey(event)">
            <div class="org-dd hidden" id="dept-tag-dd" style="top:28px"></div>
          </div>
        </div>
      </div>

      <div class="g2">
        <div class="fg"><label class="fl">อีเมล</label><input class="fc" type="email" id="adm-um-email"></div>
        <div class="fg">
          <label class="fl">สถานะ</label>
          <select class="fc" id="adm-um-active"><option value="1">ใช้งาน</option><option value="0">ระงับ</option></select>
        </div>
      </div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('adm-user-modal')">ยกเลิก</button>
      <button class="btn bp" onclick="admSaveUser()">💾 บันทึก</button>
    </div>
  </div>
</div>

<!-- Bulk import modal -->
<div class="ov hidden" id="bulk-modal">
  <div class="mdl" style="max-width:600px">
    <div class="mh">
      <span class="mt">📥 นำเข้าผู้ใช้จาก CSV</span>
      <button class="mx" onclick="closeModal('bulk-modal')">×</button>
    </div>
    <div class="mb">
      <div class="alrt ai mb3">
        <div>
          <div style="font-weight:600;margin-bottom:4px">รูปแบบ CSV (header บรรทัดแรก):</div>
          <code style="font-size:12px;display:block;background:var(--sur2);padding:6px 10px;border-radius:4px;margin-top:4px">username,name,title,role,dept,email</code>
          <div style="font-size:12px;color:var(--tx2);margin-top:6px">role ที่ใช้ได้: admin / director / deputy / head / dept_head / teacher / staff</div>
          <div style="font-size:12px;color:var(--tx2)">รหัสผ่านเริ่มต้น: <strong>password</strong></div>
        </div>
      </div>
      <div class="fg">
        <label class="fl">วางข้อมูล CSV ที่นี่ <span class="req">*</span></label>
        <textarea class="fc" id="bulk-csv" style="min-height:160px;font-family:monospace;font-size:12px" placeholder="username,name,title,role,dept,email&#10;jsmith,จอห์น สมิธ,ครู,teacher,ฝ่ายวิชาการ,"></textarea>
      </div>
      <div id="bulk-preview" style="display:none">
        <div class="dv"></div>
        <div style="font-size:13px;font-weight:600;margin-bottom:8px">ตัวอย่างข้อมูล (5 แถวแรก)</div>
        <div style="overflow-x:auto"><table id="bulk-preview-table" style="font-size:12px"></table></div>
      </div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('bulk-modal')">ยกเลิก</button>
      <button class="btn bo" onclick="previewCsv()">👁 ตรวจสอบ</button>
      <button class="btn bp" id="bulk-import-btn" onclick="doBulkImport()" style="display:none">📥 นำเข้าเลย</button>
    </div>
  </div>
</div>

<script>
// ── Filter ────────────────────────────────────────────────────────────
function filterAdmUsers(q) {
    const lq = q.toLowerCase();
    document.querySelectorAll('#adm-user-tbody tr[data-search]').forEach(tr => {
        tr.style.display = tr.dataset.search.includes(lq) ? '' : 'none';
    });
}

// ── Add/Edit user ─────────────────────────────────────────────────────
// ── Role labels map ───────────────────────────────────────────────────
const ROLE_LABELS = {<?php foreach(USER_ROLES as $k=>$v): ?>'<?= $k ?>':'<?= $v['l'] ?>',<?php endforeach ?>};

// ── Tag helpers (roles) ───────────────────────────────────────────────
let _roleTags = [];
function renderRoleTags() {
    const wrap = document.getElementById('role-tags');
    wrap.querySelectorAll('.tag-chip').forEach(el => el.remove());
    const sel  = document.getElementById('role-tag-input');
    _roleTags.forEach(r => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.style = 'display:inline-flex;align-items:center;gap:4px;background:var(--pbg2);color:var(--p);border-radius:20px;padding:2px 10px;font-size:12.5px;font-weight:600';
        chip.innerHTML = `${ROLE_LABELS[r]||r} <span style="cursor:pointer;font-size:14px;line-height:1" onclick="removeRoleTag('${r}')">×</span>`;
        wrap.insertBefore(chip, sel.parentElement || sel);
    });
}
function addRoleTag(val) {
    if (!val || _roleTags.includes(val)) return;
    const primary = document.getElementById('adm-um-role').value;
    if (val === primary) { toast('บทบาทนี้เป็นบทบาทหลักอยู่แล้ว','wn'); return; }
    _roleTags.push(val);
    renderRoleTags();
}
function removeRoleTag(val) { _roleTags = _roleTags.filter(r => r !== val); renderRoleTags(); }

// ── Tag helpers (departments) ─────────────────────────────────────────
let _deptTags = [], _deptTimer2;
function renderDeptTags() {
    const wrap = document.getElementById('dept-tags');
    wrap.querySelectorAll('.tag-chip').forEach(el => el.remove());
    const inputWrap = document.getElementById('dept-tag-input').parentElement;
    _deptTags.forEach(d => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.style = 'display:inline-flex;align-items:center;gap:4px;background:var(--sur2);color:var(--tx);border:1px solid var(--bd);border-radius:20px;padding:2px 10px;font-size:12.5px';
        chip.innerHTML = `${d} <span style="cursor:pointer;font-size:14px;line-height:1;color:var(--er)" onclick="removeDeptTag('${d.replace(/'/g,"\\'")}')">×</span>`;
        wrap.insertBefore(chip, inputWrap);
    });
}
function addDeptTag(name) {
    name = name.trim();
    if (!name || _deptTags.includes(name)) return;
    _deptTags.push(name);
    renderDeptTags();
    document.getElementById('dept-tag-input').value = '';
    document.getElementById('dept-tag-dd').classList.add('hidden');
}
function removeDeptTag(name) { _deptTags = _deptTags.filter(d => d !== name); renderDeptTags(); }
function deptTagKey(e) { if (e.key === 'Enter') { e.preventDefault(); addDeptTag(e.target.value); } }
async function deptTagSearch(q) {
    const dd = document.getElementById('dept-tag-dd');
    clearTimeout(_deptTimer2);
    if (!q.trim()) { dd.classList.add('hidden'); return; }
    _deptTimer2 = setTimeout(async () => {
        try {
            const rows = await api(`/rvc.rts/api/departments.php?q=${encodeURIComponent(q)}`);
            if (!rows.length) { dd.classList.add('hidden'); return; }
            dd.innerHTML = rows.map(r => `<div class="org-item" onclick="addDeptTag('${r.name.replace(/'/g,"\\'")}')"><span class="org-name">${r.name}</span></div>`).join('');
            dd.classList.remove('hidden');
        } catch(e) {}
    }, 200);
}
document.addEventListener('click', e => {
    if (!e.target.closest('#dept-tags')) document.getElementById('dept-tag-dd')?.classList.add('hidden');
});

// ── Open/fill modal ───────────────────────────────────────────────────
function resetModal() {
    _roleTags = []; _deptTags = [];
    renderRoleTags(); renderDeptTags();
    document.getElementById('role-tag-input').value = '';
    document.getElementById('dept-tag-input').value = '';
}

function openAddUser() {
    document.getElementById('adm-um-title').textContent = 'เพิ่มผู้ใช้งาน';
    document.getElementById('adm-um-id').value    = '';
    document.getElementById('adm-um-user').value  = '';
    document.getElementById('adm-um-user').readOnly = false;
    document.getElementById('adm-um-pw').value    = '';
    document.getElementById('adm-um-name').value  = '';
    document.getElementById('adm-um-title2').value = '';
    document.getElementById('adm-um-role').value  = 'staff';
    document.getElementById('adm-um-email').value = '';
    document.getElementById('adm-um-active').value = '1';
    resetModal();
    openModal('adm-user-modal');
}

async function admEditUser(u) {
    document.getElementById('adm-um-title').textContent = 'แก้ไขผู้ใช้งาน';
    document.getElementById('adm-um-id').value     = u.id;
    document.getElementById('adm-um-user').value   = u.username;
    document.getElementById('adm-um-user').readOnly = true;
    document.getElementById('adm-um-pw').value     = '';
    document.getElementById('adm-um-name').value   = u.name;
    document.getElementById('adm-um-title2').value  = u.title;
    document.getElementById('adm-um-role').value   = u.role;
    document.getElementById('adm-um-email').value  = u.email || '';
    document.getElementById('adm-um-active').value = String(u.active);
    resetModal();
    // Load extra data from API
    try {
        const full = await api('/rvc.rts/api/users.php?id=' + u.id);
        _roleTags = full.extra_roles || [];
        _deptTags = (full.departments || []).map(d => d.dep_name);
        // fallback: if no dept table entries, split from dept field
        if (!_deptTags.length && u.dept) _deptTags = u.dept.split(/[,،،]+/).map(s => s.trim()).filter(Boolean);
        renderRoleTags(); renderDeptTags();
    } catch(e) {
        if (u.dept) { _deptTags = u.dept.split(/[,،،]+/).map(s => s.trim()).filter(Boolean); renderDeptTags(); }
    }
    openModal('adm-user-modal');
}

async function admSaveUser() {
    const id = document.getElementById('adm-um-id').value;
    const pw = document.getElementById('adm-um-pw').value;
    const payload = {
        username:     document.getElementById('adm-um-user').value.trim(),
        name:         document.getElementById('adm-um-name').value.trim(),
        title:        document.getElementById('adm-um-title2').value.trim(),
        role:         document.getElementById('adm-um-role').value,
        extra_roles:  _roleTags,
        departments:  _deptTags,
        email:        document.getElementById('adm-um-email').value.trim(),
        active:       document.getElementById('adm-um-active').value,
    };
    if (!payload.username || !payload.name) { toast('กรุณากรอก username และชื่อ-สกุล','er'); return; }
    if (!id && !pw) { toast('กรุณากรอกรหัสผ่าน','er'); return; }
    if (pw) payload.password = pw;
    try {
        if (id) await api('/rvc.rts/api/users.php?id='+id, 'PUT', payload);
        else     await api('/rvc.rts/api/users.php', 'POST', payload);
        toast('บันทึกเรียบร้อย ✅','ok');
        closeModal('adm-user-modal');
        setTimeout(() => location.reload(), 700);
    } catch(e) { toast('เกิดข้อผิดพลาด: '+e.message,'er'); }
}

// ── Impersonate ───────────────────────────────────────────────────────
function impersonate(id, name) {
    if (!confirm(`สวมสิทธิ์ในนาม "${name}" ?\n\nเมื่อลงชื่อออก ระบบจะกลับมาเป็น Admin โดยอัตโนมัติ`)) return;
    api('/rvc.rts/api/impersonate.php', 'POST', { id })
        .then(() => { location.href = '/rvc.rts/'; })
        .catch(e => toast('ไม่สามารถสวมสิทธิ์ได้: ' + e.message, 'er'));
}

// ── Toggle active ─────────────────────────────────────────────────────
async function admToggle(id, active, btn) {
    try {
        await api('/rvc.rts/api/users.php?id='+id, 'PUT', {active});
        toast(active ? 'เปิดใช้งานแล้ว ✅':'ระงับแล้ว','ok');
        setTimeout(() => location.reload(), 600);
    } catch(e) { toast('เกิดข้อผิดพลาด','er'); }
}

// ── Reset password ────────────────────────────────────────────────────
async function admResetPwd(id, name) {
    if (!confirm(`รีเซ็ตรหัสผ่านของ "${name}" เป็น "password" ใช่หรือไม่?`)) return;
    try {
        await api('/rvc.rts/api/users.php?id='+id, 'PUT', {reset_password: true});
        toast('รีเซ็ตรหัสผ่านเป็น "password" แล้ว ✅','ok');
    } catch(e) { toast('เกิดข้อผิดพลาด','er'); }
}

// ── Delete user ───────────────────────────────────────────────────────
let _deleteTargetId = null;
function admDelete(id, name) {
    _deleteTargetId = id;
    document.getElementById('single-del-name').textContent = name;
    openModal('single-delete-modal');
}
async function doSingleDelete() {
    closeModal('single-delete-modal');
    if (!_deleteTargetId) return;
    try {
        await api('/rvc.rts/api/users.php?id=' + _deleteTargetId, 'DELETE');
        toast('ลบผู้ใช้เรียบร้อย ✅', 'ok');
        setTimeout(() => location.reload(), 700);
    } catch(e) { toast('เกิดข้อผิดพลาด: ' + e.message, 'er'); }
}

// ── Bulk import CSV ───────────────────────────────────────────────────
let parsedCsvRows = [];

function parseCsv(text) {
    const lines = text.trim().split('\n').map(l => l.trim()).filter(Boolean);
    if (lines.length < 2) return [];
    const headers = lines[0].split(',').map(h => h.trim().toLowerCase());
    return lines.slice(1).map(line => {
        const vals = line.split(',');
        const obj = {};
        headers.forEach((h, i) => obj[h] = (vals[i] || '').trim());
        return obj;
    });
}

function previewCsv() {
    const text = document.getElementById('bulk-csv').value;
    parsedCsvRows = parseCsv(text);
    if (!parsedCsvRows.length) { toast('ไม่พบข้อมูล CSV ที่ถูกต้อง','er'); return; }
    const preview = parsedCsvRows.slice(0, 5);
    const keys = Object.keys(preview[0]);
    const tableHtml = `<thead><tr>${keys.map(k=>`<th>${k}</th>`).join('')}</tr></thead><tbody>`
        + preview.map(r=>`<tr>${keys.map(k=>`<td>${r[k]||''}</td>`).join('')}</tr>`).join('')
        + '</tbody>';
    document.getElementById('bulk-preview-table').innerHTML = tableHtml;
    document.getElementById('bulk-preview').style.display = '';
    document.getElementById('bulk-import-btn').style.display = '';
    toast(`พบข้อมูล ${parsedCsvRows.length} แถว — กด "นำเข้าเลย" เพื่อยืนยัน`,'ok');
}

async function doBulkImport() {
    if (!parsedCsvRows.length) { toast('กรุณาตรวจสอบข้อมูลก่อน','er'); return; }
    try {
        const res = await api('/rvc.rts/api/users.php?bulk=1', 'POST', {rows: parsedCsvRows});
        toast(`นำเข้าสำเร็จ ${res.added} คน, ข้าม ${res.skipped} รายการ`,'ok');
        if (res.errors?.length) res.errors.forEach(e => toast(e,'aw'));
        closeModal('bulk-modal');
        setTimeout(() => location.reload(), 1000);
    } catch(e) { toast('เกิดข้อผิดพลาด','er'); }
}
</script>

<!-- ── Organizations management ─────────────────────────────────────── -->
<div class="card mb4">
  <div class="ch fxab">
    <span class="ct">🏢 จัดการแหล่งที่มาของหนังสือ</span>
    <button class="btn bp bsm" onclick="openModal('org-modal')">➕ เพิ่มหน่วยงาน</button>
  </div>
  <div class="cb" style="padding-bottom:0">
    <div class="fx g3x mb3">
      <input class="fc" id="org-search" placeholder="ค้นหาชื่อหน่วยงาน..." oninput="filterOrgs(this.value)" style="flex:1">
    </div>
  </div>
  <div style="overflow-x:auto">
    <table id="org-table">
      <thead>
        <tr><th>#</th><th>ชื่อหน่วยงาน</th><th>ชื่อย่อ</th><th>ประเภท</th><th>สถานะ</th><th>จัดการ</th></tr>
      </thead>
      <tbody id="org-tbody">
        <?php
        $allOrgs = fetchAll("SELECT * FROM organizations ORDER BY name");
        $typeLabel = ['government'=>'หน่วยงานรัฐ','private'=>'เอกชน','person'=>'บุคคล','other'=>'อื่นๆ'];
        $typeBadge = ['government'=>'bg-b','private'=>'bg-p','person'=>'bg-g','other'=>'bg-s'];
        foreach ($allOrgs as $i => $o):
        ?>
        <tr data-org-name="<?= e(mb_strtolower($o['name'],'UTF-8')) ?>" data-org-id="<?= (int)$o['id'] ?>">
          <td style="font-size:12px;color:var(--tx3)"><?= $i+1 ?></td>
          <td style="font-weight:500;font-size:13px"><?= e($o['name']) ?></td>
          <td style="font-size:12px;color:var(--tx2)"><?= e($o['short_name']) ?></td>
          <td><?= badge($typeBadge[$o['type']]??'bg-s', $typeLabel[$o['type']]??$o['type']) ?></td>
          <td><?= $o['active'] ? badge('bg-g','ใช้งาน') : badge('bg-s','ระงับ') ?></td>
          <td>
            <div class="fx g2x">
              <button class="btn bo bsm" onclick='editOrg(<?= json_encode(['id'=>(int)$o['id'],'name'=>$o['name'],'short_name'=>$o['short_name'],'type'=>$o['type'],'active'=>(int)$o['active']], JSON_UNESCAPED_UNICODE) ?>)'>แก้ไข</button>
              <?php if ($o['active']): ?>
              <button class="btn ber bsm" onclick="toggleOrg(<?= (int)$o['id'] ?>,0,this)">ระงับ</button>
              <?php else: ?>
              <button class="btn bok bsm" onclick="toggleOrg(<?= (int)$o['id'] ?>,1,this)">เปิดใช้</button>
              <?php endif ?>
            </div>
          </td>
        </tr>
        <?php endforeach ?>
        <?php if (empty($allOrgs)): ?>
        <tr><td colspan="6" class="empty">ยังไม่มีข้อมูล</td></tr>
        <?php endif ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Org add/edit modal -->
<div class="ov hidden" id="org-modal">
  <div class="mdl" style="max-width:480px">
    <div class="mh">
      <span class="mt" id="org-modal-title">เพิ่มหน่วยงาน</span>
      <button class="mx" onclick="closeModal('org-modal')">×</button>
    </div>
    <div class="mb">
      <input type="hidden" id="org-edit-id" value="">
      <div class="fg">
        <label class="fl">ชื่อหน่วยงาน <span class="req">*</span></label>
        <input class="fc" id="org-f-name" placeholder="เช่น สำนักงานคณะกรรมการการอาชีวศึกษา">
      </div>
      <div class="g2">
        <div class="fg">
          <label class="fl">ชื่อย่อ</label>
          <input class="fc" id="org-f-short" placeholder="เช่น สอศ.">
        </div>
        <div class="fg">
          <label class="fl">ประเภท</label>
          <select class="fc" id="org-f-type">
            <option value="government">หน่วยงานรัฐ</option>
            <option value="private">เอกชน</option>
            <option value="person">บุคคล</option>
            <option value="other">อื่นๆ</option>
          </select>
        </div>
      </div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('org-modal')">ยกเลิก</button>
      <button class="btn bp" onclick="saveOrg()">💾 บันทึก</button>
    </div>
  </div>
</div>

<script>
function filterOrgs(q) {
    const lq = q.toLowerCase();
    document.querySelectorAll('#org-tbody tr[data-org-id]').forEach(tr => {
        tr.style.display = tr.dataset.orgName.includes(lq) ? '' : 'none';
    });
}

function editOrg(o) {
    document.getElementById('org-modal-title').textContent = 'แก้ไขหน่วยงาน';
    document.getElementById('org-edit-id').value   = o.id;
    document.getElementById('org-f-name').value    = o.name;
    document.getElementById('org-f-short').value   = o.short_name;
    document.getElementById('org-f-type').value    = o.type;
    openModal('org-modal');
}

function openOrgAdd() {
    document.getElementById('org-modal-title').textContent = 'เพิ่มหน่วยงาน';
    document.getElementById('org-edit-id').value = '';
    document.getElementById('org-f-name').value  = '';
    document.getElementById('org-f-short').value = '';
    document.getElementById('org-f-type').value  = 'government';
    openModal('org-modal');
}

// Override the modal open button
document.querySelector('[onclick="openModal(\'org-modal\')"]')
    ?.addEventListener('click', e => { e.preventDefault(); openOrgAdd(); }, true);

async function saveOrg() {
    const id    = document.getElementById('org-edit-id').value;
    const name  = document.getElementById('org-f-name').value.trim();
    const short = document.getElementById('org-f-short').value.trim();
    const type  = document.getElementById('org-f-type').value;
    if (!name) { toast('กรุณาระบุชื่อหน่วยงาน','er'); return; }
    try {
        if (id) {
            await api('/rvc.rts/api/orgs.php?id='+id, 'PUT', {name, short_name:short, type});
        } else {
            await api('/rvc.rts/api/orgs.php', 'POST', {name, short_name:short, type});
        }
        toast('บันทึกเรียบร้อย ✅','ok');
        closeModal('org-modal');
        setTimeout(() => location.reload(), 700);
    } catch(e) { toast('เกิดข้อผิดพลาด','er'); }
}

async function toggleOrg(id, active, btn) {
    try {
        await api('/rvc.rts/api/orgs.php?id='+id, 'PUT', {active});
        toast(active ? 'เปิดใช้งานแล้ว' : 'ระงับแล้ว','ok');
        setTimeout(() => location.reload(), 600);
    } catch(e) { toast('เกิดข้อผิดพลาด','er'); }
}

// ── Bulk selection ────────────────────────────────────────────────────
function getSelectedIds() {
    return [...document.querySelectorAll('.user-chk:checked')].map(c => +c.value);
}
function onUserChk() {
    const ids = getSelectedIds();
    const bar = document.getElementById('bulk-bar');
    bar.style.display = ids.length ? 'flex' : 'none';
    document.getElementById('bulk-count').textContent = `เลือก ${ids.length} คน`;
    const all = [...document.querySelectorAll('.user-chk')];
    document.getElementById('chk-all').indeterminate = ids.length > 0 && ids.length < all.length;
    document.getElementById('chk-all').checked = ids.length === all.length && all.length > 0;
}
function toggleAllUsers(checked) {
    document.querySelectorAll('.user-chk').forEach(c => {
        const tr = c.closest('tr');
        if (!tr || tr.style.display === 'none') return;
        c.checked = checked;
    });
    onUserChk();
}
function clearSelection() {
    document.querySelectorAll('.user-chk').forEach(c => c.checked = false);
    document.getElementById('chk-all').checked = false;
    onUserChk();
}
function openBulkDelete() {
    const ids = getSelectedIds();
    document.getElementById('bulk-del-count').textContent = ids.length;
    openModal('bulk-delete-modal');
}
async function doBulkDelete() {
    const ids = getSelectedIds();
    if (!ids.length) return;
    try {
        await api('/rvc.rts/api/users.php?bulk_delete=1', 'DELETE', { ids });
        toast(`ลบ ${ids.length} คนเรียบร้อย ✅`, 'ok');
        setTimeout(() => location.reload(), 800);
    } catch(e) { toast('เกิดข้อผิดพลาด: ' + e.message, 'er'); }
}

// ── Dept autocomplete ──────────────────────────────────────────────────
let _deptTimer = {};
async function deptSearch(pfx) {
    const inp = document.getElementById(`${pfx}-dept`);
    const dd  = document.getElementById(`dept-dd-${pfx}`);
    const q   = inp.value.trim();
    clearTimeout(_deptTimer[pfx]);
    if (!q) { dd.classList.add('hidden'); return; }
    _deptTimer[pfx] = setTimeout(async () => {
        try {
            const rows = await api(`/rvc.rts/api/departments.php?q=${encodeURIComponent(q)}`);
            if (!rows.length) { dd.classList.add('hidden'); return; }
            dd.innerHTML = rows.map(r =>
                `<div class="org-item" onclick="deptSelect('${pfx}','${r.name.replace(/'/g,"\\'")}')">
                    <span class="org-name">${r.name}</span>
                </div>`
            ).join('');
            dd.classList.remove('hidden');
        } catch(e) {}
    }, 200);
}
function deptSelect(pfx, name) {
    document.getElementById(`${pfx}-dept`).value = name;
    document.getElementById(`dept-dd-${pfx}`).classList.add('hidden');
}
document.addEventListener('click', e => {
    document.querySelectorAll('[id^="dept-dd-"]').forEach(dd => {
        if (!dd.closest('.org-wrap')?.contains(e.target)) dd.classList.add('hidden');
    });
});
</script>

<!-- ── Audit log (recent activity) ──────────────────────────────────── -->
<div class="card">
  <div class="ch"><span class="ct">📋 กิจกรรมล่าสุดในระบบ</span></div>
  <div style="overflow-x:auto">
    <table>
      <thead><tr><th>#</th><th>เลขที่</th><th>เรื่อง</th><th>สถานะ</th><th>ผู้สร้าง</th><th>วันที่</th></tr></thead>
      <tbody>
      <?php
      $recent = fetchAll(
        "SELECT d.id, d.doc_number, d.subject, d.status, u.name as creator, d.created_at
         FROM documents_in d LEFT JOIN users u ON u.id=d.created_by
         ORDER BY d.created_at DESC LIMIT 10"
      );
      foreach ($recent as $i => $r):
      ?>
        <tr>
          <td style="font-size:12px;color:var(--tx3)"><?= $i+1 ?></td>
          <td><span style="font-weight:600;color:var(--p);font-size:12px"><?= e($r['doc_number']) ?></span></td>
          <td><span class="text-el" style="display:block;max-width:220px;font-size:12.5px"><?= e($r['subject']) ?></span></td>
          <td><?= statusBadge($r['status']) ?></td>
          <td style="font-size:12px"><?= e($r['creator'] ?? '-') ?></td>
          <td style="font-size:11.5px;color:var(--tx2);white-space:nowrap"><?= e($r['created_at']) ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (empty($recent)): ?>
        <tr><td colspan="6" class="empty">ไม่มีข้อมูล</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  </div>
</div>
