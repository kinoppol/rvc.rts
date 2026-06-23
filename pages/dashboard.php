<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$stats = [
    'total_in'    => (int)fetchValue('SELECT COUNT(*) FROM documents_in'),
    'pending'     => (int)fetchValue("SELECT COUNT(*) FROM documents_in WHERE status NOT IN ('done')"),
    'urgent'      => (int)fetchValue("SELECT COUNT(*) FROM documents_in WHERE urgency != 'normal'"),
    'done'        => (int)fetchValue("SELECT COUNT(*) FROM documents_in WHERE status='done'"),
    'ann_pending' => (int)fetchValue("SELECT COUNT(*) FROM documents_in WHERE status='pending_annotation'"),
];

$recentDocs = fetchAll(
    "SELECT id,doc_number,subject,status,urgency FROM documents_in ORDER BY received_date DESC,id DESC LIMIT 5"
);
$pendingTasks = fetchAll(
    "SELECT t.*,u.name as to_name FROM tasks t LEFT JOIN users u ON u.id=t.assigned_to_id WHERE t.status!='done' ORDER BY FIELD(t.urgency,'critical','urgent','normal'), t.due_date LIMIT 8"
);

$statCards = [
    ['หนังสือรับทั้งหมด', $stats['total_in'],   '#ede9fe', '#7c3aed', '📥'],
    ['รอดำเนินการ',       $stats['pending'],     '#fef9c3', '#ca8a04', '⏳'],
    ['เร่งด่วน/ด่วนที่สุด', $stats['urgent'],  '#fee2e2', '#dc2626', '🚨'],
    ['เสร็จสิ้น',         $stats['done'],        '#dcfce7', '#16a34a', '✅'],
];
?>

<div class="pgh fxab">
  <div>
    <div class="pgt">📊 ภาพรวมระบบ</div>
    <div class="pgs"><?= e(SCHOOL_NAME) ?> — ข้อมูล ณ <?= date('j') ?> <?php
        $m = ['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
        echo $m[(int)date('n')] . ' ' . (date('Y')+543);
    ?></div>
  </div>
  <a class="btn bp" href="/rvc.rts/?page=newdoc">+ รับเอกสารใหม่</a>
</div>

<div class="stg">
<?php foreach ($statCards as [$label, $val, $bg, $col, $ic]): ?>
  <div class="stc">
    <div class="sti" style="background:<?= e($bg) ?>"><?= $ic ?></div>
    <div class="fxc">
      <div class="stv" style="color:<?= e($col) ?>"><?= $val ?></div>
      <div class="stl"><?= e($label) ?></div>
    </div>
  </div>
<?php endforeach ?>
</div>

<div class="g2 mb4">
  <div class="card">
    <div class="ch">
      <span class="ct">📋 หนังสือรับล่าสุด</span>
      <a class="btn bo bsm" href="/rvc.rts/?page=register">ดูทั้งหมด</a>
    </div>
    <div style="overflow-x:auto">
      <table>
        <thead><tr><th>เลขที่</th><th>เรื่อง</th><th>สถานะ</th><th>ความเร่งด่วน</th></tr></thead>
        <tbody>
        <?php foreach ($recentDocs as $d): ?>
          <tr style="cursor:pointer" onclick="openDocModal(<?= (int)$d['id'] ?>)">
            <td><span style="font-weight:600;color:var(--p);font-size:12px"><?= e($d['doc_number']) ?></span></td>
            <td><span class="text-el" style="display:block;max-width:200px"><?= e($d['subject']) ?></span></td>
            <td><?= statusBadge($d['status']) ?></td>
            <td><?= urgBadge($d['urgency']) ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="ch">
      <span class="ct">📌 งานรอดำเนินการ</span>
      <a class="btn bo bsm" href="/rvc.rts/?page=tasks">ดูทั้งหมด</a>
    </div>
    <div class="cb" style="padding:8px 16px">
      <?php if (empty($pendingTasks)): ?>
        <div class="empty">✅ ไม่มีงานที่รอดำเนินการ</div>
      <?php else: foreach ($pendingTasks as $t): ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--bd)">
          <div class="fxab mb1">
            <span class="text-el" style="font-size:13px;font-weight:500;flex:1;margin-right:8px"><?= e($t['title']) ?></span>
            <?= taskBadge($t['status']) ?>
          </div>
          <div style="font-size:11.5px;color:var(--tx2)"><?= e($t['to_name'] ?? '') ?><?= $t['due_date'] ? ' • ครบกำหนด ' . thDate($t['due_date']) : '' ?></div>
        </div>
      <?php endforeach; endif ?>
    </div>
  </div>
</div>

<?php if ($stats['ann_pending'] > 0): ?>
<div class="alrt aw mb4">
  <span>⚠️</span>
  <span>มีหนังสือรอเกษียน <?= $stats['ann_pending'] ?> ฉบับ — <a href="/rvc.rts/?page=annotate" style="color:inherit;font-weight:600">ไปเกษียนหนังสือ</a></span>
</div>
<?php endif ?>

<div class="card">
  <div class="ch"><span class="ct">⚡ การดำเนินการด่วน</span></div>
  <div class="cb">
    <div class="fxw g3x">
      <a class="btn bp" href="/rvc.rts/?page=newdoc">+ รับเอกสารใหม่</a>
      <a class="btn bo" href="/rvc.rts/?page=annotate">✏️ เกษียนหนังสือที่รอ</a>
      <a class="btn bg" href="/rvc.rts/?page=route">📤 เสนอ/มอบหมาย</a>
      <a class="btn bg" href="/rvc.rts/?page=tasks">📊 ติดตามงาน</a>
    </div>
  </div>
</div>
