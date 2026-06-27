<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Documents waiting for deputy review — show to deputy/admin
$isDeputy = in_array($user['role'], ['deputy','admin']);
if ($isDeputy) {
    $pendingDeputy = $user['role'] === 'admin'
        ? fetchAll("SELECT d.*, u.name as deputy_name FROM documents_in d LEFT JOIN users u ON u.id=d.deputy_id WHERE d.status='pending_deputy' ORDER BY d.received_date DESC")
        : fetchAll("SELECT d.*, u.name as deputy_name FROM documents_in d LEFT JOIN users u ON u.id=d.deputy_id WHERE d.status='pending_deputy' AND d.deputy_id=? ORDER BY d.received_date DESC", [$user['id']]);
} else {
    $pendingDeputy = [];
}

$pending = fetchAll("SELECT * FROM documents_in WHERE status='pending_director' ORDER BY urgency='critical' DESC, urgency='urgent' DESC, received_date DESC");
?>

<div class="pgh">
  <div class="pgt">📤 เสนอสายบังคับบัญชา / มอบหมายงาน</div>
  <div class="pgs">เสนอเรื่องผ่านรองฯ ถึงผู้อำนวยการ และมอบหมายให้ฝ่ายที่เกี่ยวข้องดำเนินการ</div>
</div>

<!-- Flow diagram -->
<div class="card mb4">
  <div class="ch"><span class="ct">🔄 ขั้นตอนการเสนอเรื่อง</span></div>
  <div class="cb">
    <div class="fx g3x" style="align-items:center;flex-wrap:wrap;justify-content:center">
      <?php
      $steps = ['งานบริหารทั่วไป'."\n".'(รับ+เกษียน)','รองฯ ฝ่ายบริหาร'."\n".'(เพิ่มความเห็น)','ผู้อำนวยการ'."\n".'(พิจารณา+มอบหมาย)','ฝ่าย/งาน'."\n".'(ดำเนินการ)','รายงานผล'."\n".'(เกษียนตอบ)'];
      foreach ($steps as $i => $s): $hi = $i < 3;
      ?>
        <div style="text-align:center;padding:10px 14px;background:<?= $hi?'var(--pbg)':'var(--sur2)' ?>;border:1.5px solid <?= $hi?'var(--pl)':'var(--bd)' ?>;border-radius:var(--r);font-size:12.5px;font-weight:500;white-space:pre-line;line-height:1.5"><?= e($s) ?></div>
        <?php if ($i < count($steps)-1): ?><div style="font-size:18px;color:var(--tx3)">→</div><?php endif ?>
      <?php endforeach ?>
    </div>
  </div>
</div>

<?php if ($isDeputy): ?>
<!-- Pending deputy review -->
<div class="card mb4">
  <div class="ch"><span class="ct">📋 รอรองผู้อำนวยการให้ความเห็น (<?= count($pendingDeputy) ?> เรื่อง)</span></div>
  <div style="overflow-x:auto">
    <table>
      <thead><tr>
        <th>เลขที่</th><th>เรื่อง</th><th>ความเร่งด่วน</th><th>ความลับ</th><th>เกษียน (งานบริหาร)</th><th>จัดการ</th>
      </tr></thead>
      <tbody>
      <?php if (empty($pendingDeputy)): ?>
        <tr><td colspan="6" class="empty">ไม่มีเรื่องรอให้ความเห็น</td></tr>
      <?php else: foreach ($pendingDeputy as $d): ?>
        <tr>
          <td><span style="font-weight:600;color:var(--p);font-size:12px"><?= e($d['doc_number']) ?></span></td>
          <td><span class="text-el" style="display:block;max-width:200px;font-size:13px"><?= e($d['subject']) ?></span></td>
          <td><?= urgBadge($d['urgency']) ?></td>
          <td><?= secBadge($d['secrecy']) ?></td>
          <td><span style="font-size:12px;color:var(--tx2)"><?= e(mb_substr($d['annotation'] ?? '', 0, 60, 'UTF-8')) ?>…</span></td>
          <td>
            <div class="fx g2x">
              <button class="btn bg bsm" onclick="openDocModal(<?= (int)$d['id'] ?>)">👁</button>
              <button class="btn bp bsm" onclick="openDeputyModal(<?= (int)$d['id'] ?>, '<?= e(addslashes($d['doc_number'])) ?>')">✍️ ให้ความเห็น</button>
            </div>
          </td>
        </tr>
      <?php endforeach; endif ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif ?>

<!-- Pending director -->
<div class="card mb4">
  <div class="ch"><span class="ct">⏳ รอผู้อำนวยการพิจารณา (<?= count($pending) ?> เรื่อง)</span></div>
  <div style="overflow-x:auto">
    <table>
      <thead><tr>
        <th>เลขที่</th><th>เรื่อง</th><th>ความเร่งด่วน</th><th>ความลับ</th><th>เกษียนรองฯ</th><th>จัดการ</th>
      </tr></thead>
      <tbody>
      <?php if (empty($pending)): ?>
        <tr><td colspan="6" class="empty">ไม่มีเรื่องรอพิจารณา</td></tr>
      <?php else: foreach ($pending as $d): ?>
        <tr>
          <td><span style="font-weight:600;color:var(--p);font-size:12px"><?= e($d['doc_number']) ?></span></td>
          <td><span class="text-el" style="display:block;max-width:200px;font-size:13px"><?= e($d['subject']) ?></span></td>
          <td><?= urgBadge($d['urgency']) ?></td>
          <td><?= secBadge($d['secrecy']) ?></td>
          <td><?= $d['deputy_note'] ? '<span style="font-size:12px;color:var(--ok)">✅ มีความเห็น</span>' : '<span style="font-size:12px;color:var(--tx3)">รอความเห็น</span>' ?></td>
          <td>
            <div class="fx g2x">
              <button class="btn bg bsm" onclick="openDocModal(<?= (int)$d['id'] ?>)">👁</button>
              <button class="btn bp bsm" onclick="openAssignModal(<?= (int)$d['id'] ?>, '<?= e(addslashes($d['doc_number'])) ?>')">✅ มอบหมาย</button>
            </div>
          </td>
        </tr>
      <?php endforeach; endif ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Deputy note modal -->
<div class="ov hidden" id="deputy-modal">
  <div class="mdl">
    <div class="mh">
      <span class="mt" id="deputy-title">ให้ความเห็น</span>
      <button class="mx" onclick="closeModal('deputy-modal')">×</button>
    </div>
    <div class="mb">
      <div class="fg">
        <label class="fl">ความเห็นรองผู้อำนวยการ <span class="req">*</span></label>
        <textarea class="fc" id="dep-note" placeholder="เรียน ผู้อำนวยการ — ..." style="min-height:100px"></textarea>
      </div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('deputy-modal')">ยกเลิก</button>
      <button class="btn bp" onclick="doDeputyNote()">📤 บันทึกและส่งผู้อำนวยการ</button>
    </div>
  </div>
</div>

<!-- Assign modal -->
<div class="ov hidden" id="assign-modal">
  <div class="mdl">
    <div class="mh">
      <span class="mt" id="assign-title">มอบหมายงาน</span>
      <button class="mx" onclick="closeModal('assign-modal')">×</button>
    </div>
    <div class="mb">
      <div class="fg">
        <label class="fl">คำสั่ง / หมายเหตุผู้อำนวยการ</label>
        <textarea class="fc" id="dir-note" placeholder="มอบหมายให้..." style="min-height:80px"></textarea>
      </div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('assign-modal')">ยกเลิก</button>
      <button class="btn bok" onclick="doAssign()">✅ ยืนยันมอบหมาย</button>
    </div>
  </div>
</div>

<script>
let deputyDocId = null;
function openDeputyModal(id, num) {
    deputyDocId = id;
    document.getElementById('deputy-title').textContent = 'ให้ความเห็น — ' + num;
    document.getElementById('dep-note').value = '';
    openModal('deputy-modal');
}
async function doDeputyNote() {
    const note = document.getElementById('dep-note').value.trim();
    if (!note) { toast('กรุณาระบุความเห็น','er'); return; }
    try {
        await api('/rvc.rts/api/documents.php?id='+deputyDocId, 'PUT', { deputy_note: note });
        toast('บันทึกและส่งผู้อำนวยการเรียบร้อย ✅','ok');
        closeModal('deputy-modal');
        setTimeout(() => location.reload(), 1000);
    } catch(e) { toast('เกิดข้อผิดพลาด','er'); }
}

let assignDocId = null;
function openAssignModal(id, num) {
    assignDocId = id;
    document.getElementById('assign-title').textContent = 'มอบหมายงาน — ' + num;
    document.getElementById('dir-note').value = '';
    openModal('assign-modal');
}
async function doAssign() {
    const note = document.getElementById('dir-note').value.trim();
    if (!note) { toast('กรุณาระบุคำสั่ง/หมายเหตุ','er'); return; }
    try {
        await api('/rvc.rts/api/documents.php?id='+assignDocId, 'PUT', {
            director_note: note,
            status: 'assigned'
        });
        toast('มอบหมายเรียบร้อย ✅','ok');
        closeModal('assign-modal');
        setTimeout(() => location.reload(), 1000);
    } catch(e) { toast('เกิดข้อผิดพลาด','er'); }
}
</script>
