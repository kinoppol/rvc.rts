<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$q    = trim($_GET['q'] ?? '');
$role = $_GET['role'] ?? 'all';
$like = '%' . $q . '%';

$sql    = 'SELECT id,username,name,title,role,dept,active,email FROM users WHERE (name LIKE ? OR title LIKE ? OR dept LIKE ?)';
$params = [$like, $like, $like];
if ($role !== 'all') { $sql .= ' AND role=?'; $params[] = $role; }
$sql .= ' ORDER BY FIELD(role,"director","deputy","head","dept_head","teacher","staff"), name';
$users = fetchAll($sql, $params);
$total = (int)fetchValue('SELECT COUNT(*) FROM users');
?>

<div class="pgh fxab">
  <div>
    <div class="pgt">👥 จัดการผู้ใช้งาน</div>
    <div class="pgs">ผู้ใช้งานทั้งหมด <?= $total ?> คน</div>
  </div>
  <button class="btn bp" onclick="openModal('user-modal')">+ เพิ่มผู้ใช้</button>
</div>

<div class="card">
  <div class="cb-sm">
    <form method="get" action="/rvc.rts/" class="fxw g3x" style="align-items:center">
      <input type="hidden" name="page" value="users">
      <div class="sr" style="flex:1;min-width:200px;max-width:300px">
        <span class="sr-i">🔍</span>
        <input class="fc" name="q" placeholder="ค้นหาผู้ใช้..." value="<?= e($q) ?>">
      </div>
      <select class="fc" name="role" style="width:auto;min-width:160px" onchange="this.form.submit()">
        <option value="all" <?= $role==='all'?'selected':'' ?>>ทุกบทบาท</option>
        <?php foreach (USER_ROLES as $k => $v): ?>
          <option value="<?= e($k) ?>" <?= $role===$k?'selected':'' ?>><?= e($v['l']) ?></option>
        <?php endforeach ?>
      </select>
    </form>
  </div>
  <div style="overflow-x:auto">
    <table>
      <thead><tr>
        <th>#</th><th>ชื่อ-สกุล</th><th>ตำแหน่ง</th><th>ฝ่าย/สังกัด</th><th>บทบาท</th><th>สถานะ</th><th>จัดการ</th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $i => $u): ?>
        <tr>
          <td><span style="font-size:12px;color:var(--tx3)"><?= $i+1 ?></span></td>
          <td>
            <div class="fxa g2x">
              <div style="width:28px;height:28px;border-radius:50%;background:var(--p);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0"><?= e(avatarChars($u['name'])) ?></div>
              <span style="font-weight:500"><?= e($u['name']) ?></span>
            </div>
          </td>
          <td><span style="font-size:12.5px"><?= e($u['title']) ?></span></td>
          <td><span style="font-size:12.5px"><?= e($u['dept']) ?></span></td>
          <td><?= roleBadge($u['role']) ?></td>
          <td><?= $u['active'] ? badge('bg-g','ใช้งาน') : badge('bg-s','ระงับ') ?></td>
          <td>
            <div class="fx g2x">
              <button class="btn bg bsm" onclick='openEditModal(<?= json_encode($u, JSON_UNESCAPED_UNICODE) ?>)'>✏️</button>
              <button class="btn bg bsm" onclick="resetPwd(<?= (int)$u['id'] ?>)">🔑</button>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add/Edit modal -->
<div class="ov hidden" id="user-modal">
  <div class="mdl">
    <div class="mh">
      <span class="mt" id="um-title">เพิ่มผู้ใช้งาน</span>
      <button class="mx" onclick="closeModal('user-modal')">×</button>
    </div>
    <div class="mb">
      <input type="hidden" id="um-id">
      <div class="g2">
        <div class="fg"><label class="fl">ชื่อผู้ใช้ <span class="req">*</span></label><input class="fc" id="um-username" placeholder="ชื่อเข้าระบบ"></div>
        <div class="fg"><label class="fl">รหัสผ่าน</label><input class="fc" type="password" id="um-pw" placeholder="เว้นว่างถ้าไม่เปลี่ยน"></div>
      </div>
      <div class="fg"><label class="fl">ชื่อ-สกุล <span class="req">*</span></label><input class="fc" id="um-name"></div>
      <div class="g2">
        <div class="fg"><label class="fl">ตำแหน่ง</label><input class="fc" id="um-title2"></div>
        <div class="fg">
          <label class="fl">บทบาท</label>
          <select class="fc" id="um-role">
            <?php foreach (USER_ROLES as $k => $v): ?>
              <option value="<?= e($k) ?>"><?= e($v['l']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>
      <div class="fg"><label class="fl">ฝ่าย/สังกัด</label><input class="fc" id="um-dept"></div>
      <div class="fg"><label class="fl">อีเมล</label><input class="fc" type="email" id="um-email"></div>
      <div class="fg">
        <label class="fl">สถานะ</label>
        <select class="fc" id="um-active"><option value="1">ใช้งาน</option><option value="0">ระงับ</option></select>
      </div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('user-modal')">ยกเลิก</button>
      <button class="btn bp" onclick="saveUser()">💾 บันทึก</button>
    </div>
  </div>
</div>

<!-- Reset password modal -->
<div class="ov hidden" id="pwd-modal">
  <div class="mdl" style="max-width:420px">
    <div class="mh"><span class="mt">เปลี่ยนรหัสผ่าน</span><button class="mx" onclick="closeModal('pwd-modal')">×</button></div>
    <div class="mb">
      <input type="hidden" id="pm-id">
      <div class="fg"><label class="fl">รหัสผ่านใหม่ <span class="req">*</span></label><input class="fc" type="password" id="pm-pw"></div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('pwd-modal')">ยกเลิก</button>
      <button class="btn bp" onclick="savePwd()">🔑 บันทึก</button>
    </div>
  </div>
</div>

<script>
function openEditModal(u) {
    document.getElementById('um-title').textContent = 'แก้ไขผู้ใช้งาน';
    document.getElementById('um-id').value       = u.id;
    document.getElementById('um-username').value = u.username;
    document.getElementById('um-username').readOnly = true;
    document.getElementById('um-pw').value       = '';
    document.getElementById('um-name').value     = u.name;
    document.getElementById('um-title2').value   = u.title;
    document.getElementById('um-role').value     = u.role;
    document.getElementById('um-dept').value     = u.dept;
    document.getElementById('um-email').value    = u.email || '';
    document.getElementById('um-active').value   = u.active ? '1' : '0';
    openModal('user-modal');
}

document.getElementById('user-modal')?.addEventListener('click', e => {
    if (e.target.id === 'user-modal') return;
});

async function saveUser() {
    const id = document.getElementById('um-id').value;
    const payload = {
        username: document.getElementById('um-username').value,
        password: document.getElementById('um-pw').value,
        name:     document.getElementById('um-name').value,
        title:    document.getElementById('um-title2').value,
        role:     document.getElementById('um-role').value,
        dept:     document.getElementById('um-dept').value,
        email:    document.getElementById('um-email').value,
        active:   document.getElementById('um-active').value,
    };
    try {
        if (id) await api('/rvc.rts/api/users.php?id='+id, 'PUT', payload);
        else     await api('/rvc.rts/api/users.php', 'POST', payload);
        toast('บันทึกเรียบร้อย ✅','ok');
        closeModal('user-modal');
        setTimeout(() => location.reload(), 800);
    } catch(e) { toast('เกิดข้อผิดพลาด: '+e.message,'er'); }
}

function resetPwd(id) {
    document.getElementById('pm-id').value = id;
    document.getElementById('pm-pw').value = '';
    openModal('pwd-modal');
}

async function savePwd() {
    const id = document.getElementById('pm-id').value;
    const pw = document.getElementById('pm-pw').value;
    if (!pw) { toast('กรุณาระบุรหัสผ่าน','er'); return; }
    try {
        await api('/rvc.rts/api/users.php?id='+id, 'PUT', { password: pw });
        toast('เปลี่ยนรหัสผ่านเรียบร้อย ✅','ok');
        closeModal('pwd-modal');
    } catch(e) { toast('เกิดข้อผิดพลาด','er'); }
}
</script>
