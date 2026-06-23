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
    $prefix = preg_replace('/[^ก-๙a-zA-Z.\/]/', '', $_POST['prefix'] ?? 'รบ');
    $start  = max(1, (int)($_POST['start'] ?? 1));
    $year   = (int)($_POST['year'] ?? (date('Y') + 543));
    query("UPDATE settings SET setting_value=? WHERE setting_key='doc_seq'", [$start - 1]);
    query("INSERT INTO settings (setting_key,setting_value) VALUES ('doc_seq',?) ON DUPLICATE KEY UPDATE setting_value=?", [$start - 1, $start - 1]);
    query("UPDATE settings SET setting_value=? WHERE setting_key='doc_prefix'", [$prefix]);
    query("INSERT INTO settings (setting_key,setting_value) VALUES ('doc_prefix',?) ON DUPLICATE KEY UPDATE setting_value=?", [$prefix, $prefix]);
    query("UPDATE settings SET setting_value=? WHERE setting_key='doc_year'", [$year]);
    query("INSERT INTO settings (setting_key,setting_value) VALUES ('doc_year',?) ON DUPLICATE KEY UPDATE setting_value=?", [$year, $year]);
    $msg = "ตั้งค่าเลขที่เอกสารเรียบร้อย — ถัดไปจะเป็น {$prefix}.{$start}/{$year}";
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

$lastDoc = fetchValue("SELECT doc_number FROM documents_in ORDER BY id DESC LIMIT 1") ?: '-';

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
          <div class="fg"><label class="fl">คำนำหน้า (Prefix)</label><input class="fc" name="prefix" value="รบ" placeholder="รบ"></div>
          <div class="fg"><label class="fl">ปี พ.ศ.</label><input class="fc" name="year" type="number" value="2568"></div>
        </div>
        <div class="fg"><label class="fl">เริ่มต้นที่เลขที่</label><input class="fc" name="start" type="number" value="1" min="1"></div>
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
