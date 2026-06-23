<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$pending = fetchAll(
    "SELECT t.*, ab.name as by_name, at2.name as to_name
     FROM tasks t
     LEFT JOIN users ab  ON ab.id = t.assigned_by_id
     LEFT JOIN users at2 ON at2.id= t.assigned_to_id
     WHERE t.status != 'done'
     ORDER BY FIELD(t.urgency,'critical','urgent','normal'), t.due_date"
);
?>

<div class="pgh">
  <div class="pgt">↩️ เกษียนตอบ / รายงานผล</div>
  <div class="pgs">รายงานผลการดำเนินงาน หรือแจ้งติดขัดต่อผู้บังคับบัญชา</div>
</div>

<?php if (empty($pending)): ?>
  <div class="card"><div class="empty">✅ ไม่มีงานที่รอรายงานผลในขณะนี้</div></div>
<?php else: ?>
  <div class="fxc g4x">
  <?php foreach ($pending as $t): ?>
    <div class="card">
      <div class="ch">
        <span class="ct"><?= e($t['title']) ?></span>
        <?= taskBadge($t['status']) ?>
      </div>
      <div class="cb">
        <div class="g2 mb3">
          <div>
            <div style="font-size:11.5px;color:var(--tx2);margin-bottom:2px">มอบหมายโดย</div>
            <div><?= e($t['by_name'] ?? '') ?></div>
          </div>
          <div>
            <div style="font-size:11.5px;color:var(--tx2);margin-bottom:2px">ครบกำหนด</div>
            <div style="color:var(--er)"><?= $t['due_date'] ? e(thDate($t['due_date'])) : '-' ?></div>
          </div>
        </div>
        <?php if ($t['note']): ?>
          <div class="an mb3"><div class="an-lbl">คำสั่ง</div><?= e($t['note']) ?></div>
        <?php endif ?>
        <div class="fg">
          <label class="fl">เกษียนตอบ / รายงานผลการดำเนินงาน</label>
          <textarea class="fc" id="rep-<?= (int)$t['id'] ?>" placeholder="ระบุผลการดำเนินงาน..."><?= e($t['response'] ?? '') ?></textarea>
        </div>
        <div class="fx g3x" style="justify-content:flex-end">
          <button class="btn bwn bsm" onclick="replyTask(<?= (int)$t['id'] ?>,'blocked')">⚠️ แจ้งติดขัด</button>
          <button class="btn bok bsm" onclick="replyTask(<?= (int)$t['id'] ?>,'done')">✅ รายงานเสร็จสิ้น</button>
        </div>
      </div>
    </div>
  <?php endforeach ?>
  </div>
<?php endif ?>

<script>
async function replyTask(id, status) {
    const txt = document.getElementById('rep-'+id)?.value || '';
    try {
        await api('/rvc.rts/api/tasks.php?id='+id, 'PUT', { status, response: txt });
        toast(status==='done' ? 'รายงานผลเรียบร้อย ✅' : 'แจ้งติดขัดแล้ว ⚠️', status==='done'?'ok':'');
        setTimeout(() => location.reload(), 900);
    } catch(e) { toast('เกิดข้อผิดพลาด','er'); }
}
</script>
