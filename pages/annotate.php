<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$selId = (int)($_GET['id'] ?? 0);
$pending = fetchAll("SELECT * FROM documents_in WHERE status='pending_annotation' ORDER BY received_date DESC, id DESC");
$sel = $selId ? fetchOne('SELECT * FROM documents_in WHERE id=?', [$selId]) : null;
if ($sel && $sel['status'] !== 'pending_annotation') $sel = null;

$DEPTS = ['ฝ่ายบริหารทรัพยากร','ฝ่ายยุทธศาสตร์และแผนงาน','ฝ่ายกิจการนักเรียน นักศึกษา','ฝ่ายวิชาการ',
          'งานบริหารงานทั่วไป','งานการเงิน','งานพัสดุ','งานทะเบียน'];
?>

<div class="pgh">
  <div class="pgt">✏️ เกษียนหนังสือ</div>
  <div class="pgs">ใส่ความเห็นสรุป กำหนดความเร่งด่วน ระดับลับ และผู้เกี่ยวข้อง ก่อนนำเสนอสายบังคับบัญชา</div>
</div>

<div class="g2">
  <!-- Left: pending list -->
  <div class="fxc g4x">
    <div class="card">
      <div class="ch"><span class="ct">เลือกหนังสือที่รอเกษียน</span></div>
      <div class="cb">
        <?php if (empty($pending)): ?>
          <div class="empty">✅ ไม่มีหนังสือรอเกษียน</div>
        <?php else: foreach ($pending as $d): ?>
          <a href="/rvc.rts/?page=annotate&id=<?= (int)$d['id'] ?>"
             style="display:block;padding:10px 12px;border-radius:var(--r2);border:1.5px solid <?= $sel && $sel['id']==$d['id'] ? 'var(--p)':'var(--bd)' ?>;background:<?= $sel && $sel['id']==$d['id'] ? 'var(--pbg)':'transparent' ?>;cursor:pointer;margin-bottom:8px;text-decoration:none;">
            <div style="font-weight:600;font-size:12.5px;color:var(--p)"><?= e($d['doc_number']) ?></div>
            <div style="font-size:12.5px;margin-top:3px;color:var(--tx)"><?= e(mb_substr($d['subject'],0,55,'UTF-8')) . (mb_strlen($d['subject'],'UTF-8')>55?'…':'') ?></div>
            <div style="font-size:11.5px;color:var(--tx2);margin-top:2px">จาก <?= e($d['from_short']?:$d['from_org']) ?> • <?= e($d['received_date']) ?></div>
          </a>
        <?php endforeach; endif ?>
      </div>
    </div>

    <?php if ($sel): ?>
    <div class="card">
      <div class="ch"><span class="ct">📄 ข้อมูลเอกสาร</span></div>
      <div class="cb">
        <div style="font-size:11.5px;color:var(--tx2);margin-bottom:3px">จาก</div>
        <div style="font-size:13.5px;margin-bottom:10px"><?= e($sel['from_org']) ?></div>
        <?php if ($sel['file_path']): ?>
          <a href="/rvc.rts/uploads/documents/<?= e($sel['file_path']) ?>" target="_blank" class="btn bo bsm">📎 ดูไฟล์ PDF</a>
        <?php else: ?>
          <div style="padding:24px;background:var(--sur2);border-radius:var(--r);text-align:center;color:var(--tx3);border:1px solid var(--bd)">
            <div style="font-size:28px;margin-bottom:6px">📄</div>
            <div><?= e($sel['doc_number']) ?>.pdf</div>
            <div style="font-size:11px;margin-top:4px">ไม่มีไฟล์แนบ</div>
          </div>
        <?php endif ?>
      </div>
    </div>
    <?php endif ?>
  </div>

  <!-- Right: annotation form -->
  <?php if ($sel): ?>
  <div class="card">
    <div class="ch"><span class="ct">เกษียนหนังสือ — <?= e($sel['doc_number']) ?></span></div>
    <div class="cb">
      <div id="ann-ok" class="alrt ai mb3" style="display:none">✅ บันทึกเกษียนเรียบร้อย และส่งรองฯ ฝ่ายบริหารฯ แล้ว</div>

      <div class="fg">
        <label class="fl">ความเห็น / สรุปเรื่อง (เกษียน) <span class="req">*</span></label>
        <textarea class="fc" id="ann-txt" style="min-height:110px" placeholder="เรียน รองฯ ฝ่ายบริหารฯ — ..."></textarea>
      </div>
      <div class="g2">
        <div class="fg">
          <label class="fl">ความเร่งด่วน</label>
          <select class="fc" id="ann-urg">
            <option value="normal">ปกติ</option>
            <option value="urgent">เร่งด่วน (3 วัน)</option>
            <option value="critical">ด่วนที่สุด (2 วัน)</option>
          </select>
        </div>
        <div class="fg">
          <label class="fl">ระดับความลับ</label>
          <select class="fc" id="ann-sec">
            <option value="none">ไม่ลับ</option>
            <option value="secret">ลับ</option>
            <option value="top_secret">ลับที่สุด</option>
          </select>
        </div>
      </div>
      <div class="fg">
        <label class="fl">กำหนดผู้เกี่ยวข้อง (ฝ่าย/งาน)</label>
        <select class="fc" id="dept-select2">
          <option value="">-- เลือกฝ่าย/งาน --</option>
          <?php foreach ($DEPTS as $d): ?>
            <option value="<?= e($d) ?>"><?= e($d) ?></option>
          <?php endforeach ?>
        </select>
        <div class="chips mt3" id="dept-chips2"></div>
        <input type="hidden" id="f-depts2">
      </div>
      <div class="dv"></div>
      <div class="fx g3x" style="justify-content:flex-end">
        <a class="btn bg" href="/rvc.rts/?page=annotate">ยกเลิก</a>
        <button class="btn bp" onclick="saveAnnotation(<?= (int)$sel['id'] ?>)">📤 บันทึกและส่งรองฯ</button>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="card" style="display:flex;align-items:center;justify-content:center;min-height:300px">
    <div class="empty">← เลือกหนังสือที่ต้องการเกษียนจากรายการ</div>
  </div>
  <?php endif ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initChips('dept-select2', 'dept-chips2', 'f-depts2');
});

async function saveAnnotation(docId) {
    const txt = document.getElementById('ann-txt').value.trim();
    if (!txt) { toast('กรุณาใส่ความเห็นเกษียน','er'); return; }
    try {
        await api('/rvc.rts/api/documents.php?id='+docId, 'PUT', {
            annotation: txt,
            urgency:    document.getElementById('ann-urg').value,
            secrecy:    document.getElementById('ann-sec').value,
            depts:      document.getElementById('f-depts2').value,
            status:     'pending_director',
        });
        document.getElementById('ann-ok').style.display = '';
        toast('บันทึกเกษียนเรียบร้อย ✅','ok');
        setTimeout(() => window.location = '/rvc.rts/?page=annotate', 1500);
    } catch(e) { toast('เกิดข้อผิดพลาด','er'); }
}
</script>
