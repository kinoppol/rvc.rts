<?php
require_once __DIR__ . '/../includes/functions.php';

$DEPS = [
    ['name'=>'ฝ่ายบริหารทรัพยากร','dep'=>'นางสาวมาลี รักดี',
     'units'=>['งานบริหารงานทั่วไป','งานบริหารและพัฒนาทรัพยากรบุคคล','งานการเงิน','งานการบัญชี','งานพัสดุ','งานอาคารสถานที่','งานทะเบียน']],
    ['name'=>'ฝ่ายยุทธศาสตร์และแผนงาน','dep'=>'นายชาติชาย บุญมี',
     'units'=>['งานพัฒนายุทธศาสตร์ฯ','งานมาตรฐานและประกันคุณภาพ','งานศูนย์ดิจิทัลฯ','งานส่งเสริมการวิจัยฯ','งานส่งเสริมธุรกิจฯ','งานติดตามและประเมินผล']],
    ['name'=>'ฝ่ายกิจการนักเรียน นักศึกษา','dep'=>'นางวิไล สดใส',
     'units'=>['งานกิจกรรมนักเรียนฯ','งานครูที่ปรึกษาและแนะแนว','งานปกครองและความปลอดภัย','งานสวัสดิการนักเรียนฯ','งานโครงการพิเศษ']],
    ['name'=>'ฝ่ายวิชาการ','dep'=>'นายประสิทธิ์ เก่งงาน',
     'depts'=>['แผนกช่างยนต์','แผนกช่างกลโรงงาน','แผนกช่างอิเล็กทรอนิกส์','แผนกช่างไฟฟ้า','แผนกคอมพิวเตอร์ธุรกิจ','แผนกการบัญชี','แผนกการตลาด'],
     'units'=>['งานพัฒนาหลักสูตรฯ','งานวัดผลและประเมินผล','งานทวิภาคีและความร่วมมือ','งานวิทยบริการฯ','งานการศึกษาพิเศษฯ']],
];
?>

<div class="pgh">
  <div class="pgt">🏢 โครงสร้างองค์กร</div>
  <div class="pgs"><?= e(SCHOOL_NAME) ?> — สายบังคับบัญชาและสายงาน</div>
</div>

<!-- Director -->
<div style="display:flex;justify-content:center;margin-bottom:4px">
  <div style="background:var(--p);color:#fff;border-radius:var(--r);padding:12px 24px;text-align:center;min-width:180px;box-shadow:var(--sh2)">
    <div style="font-size:22px;margin-bottom:4px">👤</div>
    <div style="font-weight:700;font-size:14px">ดร.วิจิตร สุขสม</div>
    <div style="font-size:11.5px;opacity:.75;margin-top:2px">ผู้อำนวยการวิทยาลัย</div>
  </div>
</div>
<div style="width:2px;height:24px;background:var(--bd);margin:0 auto"></div>
<div style="height:2px;background:var(--bd);width:82%;margin:0 auto 4px"></div>

<div class="g4 mb5" style="gap:10px">
<?php foreach ($DEPS as $i => $dep): ?>
  <div>
    <div style="background:var(--pbg);border:1.5px solid var(--pl);border-radius:var(--r);padding:11px 12px;text-align:center;cursor:pointer;box-shadow:var(--sh);margin-bottom:6px"
         onclick="toggleDiv('org-<?= $i ?>')">
      <div style="font-size:11.5px;font-weight:700;color:var(--p);margin-bottom:3px"><?= e($dep['name']) ?></div>
      <div style="font-size:11px;color:var(--tx2)"><?= e($dep['dep']) ?></div>
      <div style="font-size:11px;color:var(--p);margin-top:4px" id="org-<?= $i ?>-lbl">▼ ดู <?= count($dep['units']) + count($dep['depts'] ?? []) ?> หน่วยงาน</div>
    </div>
    <div id="org-<?= $i ?>" style="display:none;border:1px solid var(--bd);border-radius:var(--r);overflow:hidden">
      <?php if (!empty($dep['depts'])): ?>
        <div style="padding:6px 12px;background:var(--pbg);font-size:11.5px;font-weight:600;color:var(--p)">📚 แผนกวิชา</div>
        <?php foreach ($dep['depts'] as $d): ?>
          <div style="padding:6px 12px 6px 20px;border-bottom:1px solid var(--bd);font-size:12px;background:var(--sur)"><span style="margin-right:5px">📌</span><?= e($d) ?></div>
        <?php endforeach ?>
      <?php endif ?>
      <div style="padding:6px 12px;background:var(--sur2);font-size:11.5px;font-weight:600;color:var(--tx2)">📁 งาน</div>
      <?php foreach ($dep['units'] as $j => $u): ?>
        <div style="padding:7px 12px;border-bottom:<?= $j < count($dep['units'])-1 ? '1px solid var(--bd)' : 'none' ?>;font-size:12.5px;background:var(--sur);display:flex;align-items:center;gap:6px">
          <span style="width:5px;height:5px;border-radius:50%;background:var(--p);display:inline-block;flex-shrink:0"></span><?= e($u) ?>
        </div>
      <?php endforeach ?>
    </div>
  </div>
<?php endforeach ?>
</div>

<script>
function toggleDiv(id) {
    const el = document.getElementById(id);
    const lbl = document.getElementById(id+'-lbl');
    if (!el) return;
    const open = el.style.display !== 'none';
    el.style.display = open ? 'none' : '';
    if (lbl) lbl.textContent = open ? lbl.textContent.replace('▲ ซ่อน', '▼ ดู').replace('▲','▼') : lbl.textContent.replace('▼','▲').replace('ดู ','ซ่อน ');
}
</script>
