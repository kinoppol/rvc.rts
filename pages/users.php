<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$_me  = current_user();
$isAdmin = $_me && $_me['role'] === 'admin';

$q    = trim($_GET['q'] ?? '');
$role = $_GET['role'] ?? 'all';
$like = '%' . $q . '%';

$sql    = 'SELECT id,username,name,nickname,title,role,extra_roles,dept,active,email,avatar FROM users WHERE (name LIKE ? OR title LIKE ? OR dept LIKE ?)';
$params = [$like, $like, $like];
if ($role !== 'all') { $sql .= ' AND role=?'; $params[] = $role; }
$sql .= ' ORDER BY FIELD(role,"admin","director","deputy","head","dept_head","teacher","staff"), name';
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
  <div class="cb-sm" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <form method="get" action="/rvc.rts/" class="fxw g3x" style="align-items:center;flex:1">
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
    <?php if ($isAdmin): ?>
    <div id="bulk-bar" style="display:none;align-items:center;gap:8px">
      <span id="bulk-count" style="font-size:13px;color:var(--tx2)">เลือก 0 คน</span>
      <button class="btn ber bsm" onclick="openBulkDelete()">🗑 ลบที่เลือก</button>
      <button class="btn bg bsm" onclick="clearSelection()">✕ ยกเลิก</button>
    </div>
    <?php endif ?>
  </div>
  <div style="overflow-x:auto">
    <table>
      <thead><tr>
        <?php if ($isAdmin): ?><th style="width:36px"><input type="checkbox" id="chk-all" onchange="toggleAllUsers(this.checked)"></th><?php endif ?>
        <th>#</th><th>ชื่อ-สกุล</th><th>ตำแหน่ง</th><th>ฝ่าย/สังกัด</th><th>บทบาท</th><th>สถานะ</th><th>จัดการ</th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $i => $u): ?>
        <tr>
          <?php if ($isAdmin): ?>
          <td><?php if ($u['role'] !== 'admin'): ?><input type="checkbox" class="user-chk" value="<?= (int)$u['id'] ?>" onchange="onUserChk()"><?php endif ?></td>
          <?php endif ?>
          <td><span style="font-size:12px;color:var(--tx3)"><?= $i+1 ?></span></td>
          <td>
            <div class="fxa g2x" style="align-items:center">
              <?= avatarHtml($u, '28px', '11px') ?>
              <span style="font-weight:500"><?= e($u['name']) ?></span>
            </div>
          </td>
          <td><span style="font-size:12.5px"><?= e($u['title']) ?></span></td>
          <td style="font-size:12px">
            <?php
              $depts = fetchAll('SELECT dep_name FROM user_departments WHERE user_id=? ORDER BY dep_name', [(int)$u['id']]);
              if ($depts): foreach ($depts as $d): ?>
                <span style="display:inline-block;background:var(--sur2);border:1px solid var(--bd);border-radius:20px;padding:1px 8px;margin:1px 2px;white-space:nowrap"><?= e($d['dep_name']) ?></span>
              <?php endforeach; else: echo e($u['dept']); endif ?>
          </td>
          <td>
            <?= roleBadge($u['role']) ?>
            <?php $extra = json_decode($u['extra_roles'] ?? '[]', true) ?: []; foreach ($extra as $er): echo roleBadge($er); endforeach ?>
          </td>
          <td><?= $u['active'] ? badge('bg-g','ใช้งาน') : badge('bg-s','ระงับ') ?></td>
          <td>
            <div class="fx g2x">
              <button class="btn bg bsm" onclick='openEditModal(<?= json_encode($u, JSON_UNESCAPED_UNICODE) ?>)'>✏️</button>
              <button class="btn bg bsm" onclick="resetPwd(<?= (int)$u['id'] ?>)">🔑</button>
              <?php if ($u['role'] !== 'admin'): ?>
              <button class="btn bwn bsm" onclick="impersonate(<?= (int)$u['id'] ?>, <?= json_encode($u['name'], JSON_UNESCAPED_UNICODE) ?>)" title="สวมสิทธิ์">👁️</button>
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
      <div class="fg"><label class="fl">ตำแหน่ง</label><input class="fc" id="um-title2"></div>

      <div class="fg">
        <label class="fl">บทบาทหลัก <span class="req">*</span></label>
        <select class="fc" id="um-role">
          <?php foreach (USER_ROLES as $k => $v): ?>
            <option value="<?= e($k) ?>"><?= e($v['l']) ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="fg">
        <label class="fl">บทบาทเพิ่มเติม</label>
        <div class="tag-wrap" id="um-role-tags" style="display:flex;flex-wrap:wrap;gap:6px;padding:6px 8px;border:1px solid var(--bd);border-radius:var(--r2);min-height:38px;cursor:text" onclick="document.getElementById('um-role-sel').focus()">
          <select id="um-role-sel" class="fc" style="border:none;padding:2px 4px;min-width:120px;flex:1;background:transparent;outline:none" onchange="umAddRoleTag(this.value);this.value=''">
            <option value="">+ เพิ่มบทบาท...</option>
            <?php foreach (USER_ROLES as $k => $v): ?>
              <option value="<?= e($k) ?>"><?= e($v['l']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
      </div>

      <div class="fg">
        <label class="fl">ฝ่าย/สังกัด</label>
        <div class="tag-wrap" id="um-dept-tags" style="display:flex;flex-wrap:wrap;gap:6px;padding:6px 8px;border:1px solid var(--bd);border-radius:var(--r2);min-height:38px;cursor:text" onclick="document.getElementById('um-dept-inp').focus()">
          <div style="position:relative;flex:1;min-width:140px">
            <input id="um-dept-inp" class="fc" style="border:none;padding:2px 4px;background:transparent;outline:none;width:100%" placeholder="พิมพ์แล้วกด Enter หรือเลือกจากรายการ..." oninput="umDeptSearch(this.value)" onkeydown="umDeptKey(event)">
            <div class="org-dd hidden" id="um-dept-dd" style="top:28px"></div>
          </div>
        </div>
      </div>

      <div class="g2">
        <div class="fg"><label class="fl">อีเมล</label><input class="fc" type="email" id="um-email"></div>
        <div class="fg">
          <label class="fl">สถานะ</label>
          <select class="fc" id="um-active"><option value="1">ใช้งาน</option><option value="0">ระงับ</option></select>
        </div>
      </div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('user-modal')">ยกเลิก</button>
      <button class="btn bp" onclick="saveUser()">💾 บันทึก</button>
    </div>
  </div>
</div>

<!-- Impersonate confirm modal -->
<div class="ov hidden" id="imp-modal">
  <div class="mdl" style="max-width:400px;text-align:center">
    <div class="mb" style="padding:32px 28px 20px">
      <div style="font-size:48px;margin-bottom:12px">👁️</div>
      <div style="font-size:16px;font-weight:700;margin-bottom:8px">สวมสิทธิ์ผู้ใช้</div>
      <div style="font-size:13.5px;color:var(--tx2);line-height:1.7">
        คุณจะเข้าสู่ระบบในนาม<br><strong id="imp-name" style="color:var(--p)"></strong><br>
        <span style="font-size:12.5px;margin-top:8px;display:block">เมื่อลงชื่อออก ระบบจะกลับมาเป็น Admin โดยอัตโนมัติ</span>
      </div>
    </div>
    <div class="mf" style="justify-content:center;gap:12px;padding:16px 24px 24px">
      <button class="btn bg" style="min-width:100px" onclick="closeModal('imp-modal')">ยกเลิก</button>
      <button class="btn bp" style="min-width:140px" id="imp-confirm-btn">👁️ สวมสิทธิ์</button>
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
const UM_ROLE_LABELS = {<?php foreach(USER_ROLES as $k=>$v): ?>'<?= $k ?>':'<?= $v['l'] ?>',<?php endforeach ?>};

// ── Role tags ─────────────────────────────────────────────────────────
let _umRoleTags = [];
function umRenderRoleTags() {
    const wrap = document.getElementById('um-role-tags');
    wrap.querySelectorAll('.tag-chip').forEach(el => el.remove());
    const sel = document.getElementById('um-role-sel');
    _umRoleTags.forEach(r => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.style = 'display:inline-flex;align-items:center;gap:4px;background:var(--pbg2);color:var(--p);border-radius:20px;padding:2px 10px;font-size:12.5px;font-weight:600';
        chip.innerHTML = `${UM_ROLE_LABELS[r]||r} <span style="cursor:pointer;font-size:14px;line-height:1" onclick="umRemoveRoleTag('${r}')">×</span>`;
        wrap.insertBefore(chip, sel);
    });
}
function umAddRoleTag(val) {
    if (!val || _umRoleTags.includes(val)) return;
    if (val === document.getElementById('um-role').value) { toast('บทบาทนี้เป็นบทบาทหลักอยู่แล้ว','wn'); return; }
    _umRoleTags.push(val);
    umRenderRoleTags();
}
function umRemoveRoleTag(val) { _umRoleTags = _umRoleTags.filter(r => r !== val); umRenderRoleTags(); }

// ── Dept tags ─────────────────────────────────────────────────────────
let _umDeptTags = [], _umDeptTimer;
function umRenderDeptTags() {
    const wrap = document.getElementById('um-dept-tags');
    wrap.querySelectorAll('.tag-chip').forEach(el => el.remove());
    const inputWrap = document.getElementById('um-dept-inp').parentElement;
    _umDeptTags.forEach(d => {
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.style = 'display:inline-flex;align-items:center;gap:4px;background:var(--sur2);color:var(--tx);border:1px solid var(--bd);border-radius:20px;padding:2px 10px;font-size:12.5px';
        chip.innerHTML = `${d} <span style="cursor:pointer;font-size:14px;line-height:1;color:var(--er)" onclick="umRemoveDeptTag('${d.replace(/'/g,"\\'")}')">×</span>`;
        wrap.insertBefore(chip, inputWrap);
    });
}
function umAddDeptTag(name) {
    name = name.trim();
    if (!name || _umDeptTags.includes(name)) return;
    _umDeptTags.push(name);
    umRenderDeptTags();
    document.getElementById('um-dept-inp').value = '';
    document.getElementById('um-dept-dd').classList.add('hidden');
}
function umRemoveDeptTag(name) { _umDeptTags = _umDeptTags.filter(d => d !== name); umRenderDeptTags(); }
function umDeptKey(e) { if (e.key === 'Enter') { e.preventDefault(); umAddDeptTag(e.target.value); } }
async function umDeptSearch(q) {
    const dd = document.getElementById('um-dept-dd');
    clearTimeout(_umDeptTimer);
    if (!q.trim()) { dd.classList.add('hidden'); return; }
    _umDeptTimer = setTimeout(async () => {
        try {
            const rows = await api(`/rvc.rts/api/departments.php?q=${encodeURIComponent(q)}`);
            if (!rows.length) { dd.classList.add('hidden'); return; }
            dd.innerHTML = rows.map(r => `<div class="org-item" onclick="umAddDeptTag('${r.name.replace(/'/g,"\\'")}')"><span class="org-name">${r.name}</span></div>`).join('');
            dd.classList.remove('hidden');
        } catch(e) {}
    }, 200);
}
document.addEventListener('click', e => {
    if (!e.target.closest('#um-dept-tags')) document.getElementById('um-dept-dd')?.classList.add('hidden');
});

// ── Open / fill modal ──────────────────────────────────────────────────
function umReset() {
    _umRoleTags = []; _umDeptTags = [];
    umRenderRoleTags(); umRenderDeptTags();
    document.getElementById('um-role-sel').value = '';
    document.getElementById('um-dept-inp').value = '';
}

async function openEditModal(u) {
    document.getElementById('um-title').textContent = 'แก้ไขผู้ใช้งาน';
    document.getElementById('um-id').value       = u.id;
    document.getElementById('um-username').value = u.username;
    document.getElementById('um-username').readOnly = true;
    document.getElementById('um-pw').value       = '';
    document.getElementById('um-name').value     = u.name;
    document.getElementById('um-title2').value   = u.title;
    document.getElementById('um-role').value     = u.role;
    document.getElementById('um-email').value    = u.email || '';
    document.getElementById('um-active').value   = u.active ? '1' : '0';
    umReset();
    try {
        const full = await api('/rvc.rts/api/users.php?id=' + u.id);
        _umRoleTags = full.extra_roles || [];
        _umDeptTags = (full.departments || []).map(d => d.dep_name);
        if (!_umDeptTags.length && u.dept) _umDeptTags = u.dept.split(/[,،،]+/).map(s => s.trim()).filter(Boolean);
        umRenderRoleTags(); umRenderDeptTags();
    } catch(e) {
        if (u.dept) { _umDeptTags = u.dept.split(/[,،،]+/).map(s => s.trim()).filter(Boolean); umRenderDeptTags(); }
    }
    openModal('user-modal');
}

async function saveUser() {
    const id = document.getElementById('um-id').value;
    const pw = document.getElementById('um-pw').value;
    const payload = {
        username:    document.getElementById('um-username').value,
        name:        document.getElementById('um-name').value,
        title:       document.getElementById('um-title2').value,
        role:        document.getElementById('um-role').value,
        extra_roles: _umRoleTags,
        departments: _umDeptTags,
        email:       document.getElementById('um-email').value,
        active:      document.getElementById('um-active').value,
    };
    if (!payload.username || !payload.name) { toast('กรุณากรอก username และชื่อ-สกุล','er'); return; }
    if (!id && !pw) { toast('กรุณากรอกรหัสผ่าน','er'); return; }
    if (pw) payload.password = pw;
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

// ── Impersonate ───────────────────────────────────────────────────────
let _impId = null;
function impersonate(id, name) {
    _impId = id;
    document.getElementById('imp-name').textContent = name;
    document.getElementById('imp-confirm-btn').onclick = doImpersonate;
    openModal('imp-modal');
}
async function doImpersonate() {
    if (!_impId) return;
    const btn = document.getElementById('imp-confirm-btn');
    btn.disabled = true; btn.textContent = 'กำลังสวมสิทธิ์...';
    try {
        await api('/rvc.rts/api/impersonate.php', 'POST', { id: _impId });
        location.href = '/rvc.rts/';
    } catch(e) {
        let msg = e.message;
        try { msg = JSON.parse(msg).error || msg; } catch(_) {}
        toast('ไม่สามารถสวมสิทธิ์ได้: ' + msg, 'er');
        btn.disabled = false; btn.textContent = '👁️ สวมสิทธิ์';
    }
}

// ── Bulk selection ────────────────────────────────────────────────────
function getSelectedIds() { return [...document.querySelectorAll('.user-chk:checked')].map(c => +c.value); }
function onUserChk() {
    const ids = getSelectedIds();
    const bar = document.getElementById('bulk-bar');
    if (!bar) return;
    bar.style.display = ids.length ? 'flex' : 'none';
    document.getElementById('bulk-count').textContent = `เลือก ${ids.length} คน`;
    const all = [...document.querySelectorAll('.user-chk')];
    const chkAll = document.getElementById('chk-all');
    if (chkAll) { chkAll.indeterminate = ids.length > 0 && ids.length < all.length; chkAll.checked = ids.length > 0 && ids.length === all.length; }
}
function toggleAllUsers(checked) { document.querySelectorAll('.user-chk').forEach(c => c.checked = checked); onUserChk(); }
function clearSelection() { document.querySelectorAll('.user-chk').forEach(c => c.checked = false); const a = document.getElementById('chk-all'); if (a) a.checked = false; onUserChk(); }
function openBulkDelete() { document.getElementById('bulk-del-count').textContent = getSelectedIds().length; openModal('bulk-delete-modal'); }
async function doBulkDelete() {
    const ids = getSelectedIds();
    if (!ids.length) return;
    try {
        await api('/rvc.rts/api/users.php?bulk_delete=1', 'DELETE', { ids });
        toast(`ลบ ${ids.length} คนเรียบร้อย ✅`, 'ok');
        setTimeout(() => location.reload(), 800);
    } catch(e) { toast('เกิดข้อผิดพลาด: ' + e.message, 'er'); }
}
</script>
