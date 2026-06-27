<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$tab = $_GET['tab'] ?? 'in';
$q   = trim($_GET['q'] ?? '');
$like = '%' . $q . '%';

if ($tab === 'out') {
    $docs = fetchAll(
        "SELECT * FROM documents_out WHERE subject LIKE ? OR to_org LIKE ? OR doc_number LIKE ? ORDER BY sent_date DESC",
        [$like, $like, $like]
    );
} else {
    $docs = fetchAll(
        "SELECT d.*, GROUP_CONCAT(dd.dept_name ORDER BY dd.dept_name SEPARATOR ',') as dept_list
         FROM documents_in d
         LEFT JOIN document_departments dd ON dd.doc_id=d.id
         WHERE d.subject LIKE ? OR d.from_org LIKE ? OR d.doc_number LIKE ?
         GROUP BY d.id ORDER BY d.received_date DESC, d.id DESC",
        [$like, $like, $like]
    );
}

$countIn  = (int)fetchValue('SELECT COUNT(*) FROM documents_in');
$countOut = (int)fetchValue('SELECT COUNT(*) FROM documents_out');
?>

<div class="pgh fxab">
  <div>
    <div class="pgt">📚 ทะเบียนหนังสือรับ-ส่ง</div>
    <div class="pgs">รับ <?= $countIn ?> ฉบับ / ส่ง <?= $countOut ?> ฉบับ</div>
  </div>
  <a class="btn bp" href="/rvc.rts/?page=newdoc">+ รับเอกสารใหม่</a>
</div>

<div class="card">
  <div class="cb-sm">
    <div class="fxw g3x" style="align-items:center">
      <div class="tabs" style="margin-bottom:0;border-bottom:none">
        <a class="tab <?= $tab==='in'?'act':'' ?>" href="?page=register&tab=in&q=<?= urlencode($q) ?>">📥 หนังสือรับ (<?= $countIn ?>)</a>
        <a class="tab <?= $tab==='out'?'act':'' ?>" href="?page=register&tab=out&q=<?= urlencode($q) ?>">📤 หนังสือส่ง (<?= $countOut ?>)</a>
      </div>
      <form method="get" action="/rvc.rts/" class="sr" style="flex:1;min-width:200px;max-width:320px">
        <input type="hidden" name="page" value="register">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <span class="sr-i">🔍</span>
        <input class="fc" name="q" placeholder="ค้นหา..." value="<?= e($q) ?>">
      </form>
    </div>
  </div>

  <div style="overflow-x:auto">
  <?php if ($tab === 'in'): ?>
    <table>
      <thead><tr>
        <th>เลขที่รับ</th><th>วันที่</th><th>จาก</th><th>เรื่อง</th>
        <th>ความเร่งด่วน</th><th>ระดับลับ</th><th>สถานะ</th><th>จัดการ</th>
      </tr></thead>
      <tbody>
      <?php foreach ($docs as $d): ?>
        <tr>
          <td><span style="font-weight:600;color:var(--p);font-size:12px"><?= e($d['doc_number']) ?></span></td>
          <td><span style="font-size:12.5px;white-space:nowrap"><?= e($d['received_date']) ?></span></td>
          <td><span style="font-size:12.5px"><?= e($d['from_short'] ?: $d['from_org']) ?></span></td>
          <td><span class="text-el" style="display:block;max-width:220px;font-size:13px"><?= e($d['subject']) ?></span></td>
          <td><?= urgBadge($d['urgency']) ?></td>
          <td><?= secBadge($d['secrecy']) ?></td>
          <td><?= statusBadge($d['status']) ?></td>
          <td>
            <div class="fx g2x">
              <button class="btn bg bsm" onclick="openDocModal(<?= (int)$d['id'] ?>)">👁</button>
              <?php $canEdit = in_array($d['status'], ['pending_annotation','pending_deputy','pending_director']); ?>
              <?php if ($canEdit): ?>
                <button class="btn bs bsm" onclick="openEditDocById(<?= (int)$d['id'] ?>)" title="แก้ไขข้อมูล">✏️</button>
              <?php endif ?>
              <?php if ($d['status'] === 'pending_annotation'): ?>
                <a class="btn bo bsm" href="/rvc.rts/?page=annotate&id=<?= (int)$d['id'] ?>">📝 เกษียน</a>
              <?php endif ?>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      <?php if (empty($docs)): ?>
        <tr><td colspan="8" class="empty">ไม่พบรายการ</td></tr>
      <?php endif ?>
      </tbody>
    </table>

  <?php else: ?>
    <table>
      <thead><tr>
        <th>เลขที่ส่ง</th><th>วันที่</th><th>ถึง</th><th>เรื่อง</th><th>ความเร่งด่วน</th><th>สถานะ</th>
      </tr></thead>
      <tbody>
      <?php foreach ($docs as $d): ?>
        <tr>
          <td><span style="font-weight:600;color:var(--p);font-size:12px"><?= e($d['doc_number']) ?></span></td>
          <td><span style="font-size:12.5px;white-space:nowrap"><?= e($d['sent_date']) ?></span></td>
          <td><?= e($d['to_org']) ?></td>
          <td><span class="text-el" style="display:block;max-width:240px;font-size:13px"><?= e($d['subject']) ?></span></td>
          <td><?= urgBadge($d['urgency']) ?></td>
          <td><?= badge('bg-g', 'ส่งแล้ว') ?></td>
        </tr>
      <?php endforeach ?>
      <?php if (empty($docs)): ?>
        <tr><td colspan="6" class="empty">ไม่พบรายการ</td></tr>
      <?php endif ?>
      </tbody>
    </table>
  <?php endif ?>
  </div>
</div>
