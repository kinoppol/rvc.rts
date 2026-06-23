<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$tasks = fetchAll(
    "SELECT t.*, ab.name as by_name, at2.name as to_name, d.doc_number
     FROM tasks t
     LEFT JOIN users ab  ON ab.id  = t.assigned_by_id
     LEFT JOIN users at2 ON at2.id = t.assigned_to_id
     LEFT JOIN documents_in d ON d.id = t.doc_id
     ORDER BY FIELD(t.urgency,'critical','urgent','normal'), t.due_date"
);

$cols = [
    'todo'        => ['l'=>'รอดำเนินการ',       'col'=>'#ca8a04','bg'=>'#fef9c3'],
    'in_progress' => ['l'=>'กำลังดำเนินการ',    'col'=>'#1e40af','bg'=>'#dbeafe'],
    'blocked'     => ['l'=>'ติดขัด',              'col'=>'#991b1b','bg'=>'#fee2e2'],
    'done'        => ['l'=>'เสร็จสิ้น',           'col'=>'#166534','bg'=>'#dcfce7'],
];
$grp = array_fill_keys(array_keys($cols), []);
foreach ($tasks as $t) { if (isset($grp[$t['status']])) $grp[$t['status']][] = $t; }
?>

<div class="pgh fxab">
  <div>
    <div class="pgt">📊 ติดตามงาน</div>
    <div class="pgs">สถานะการดำเนินงานตามที่ได้รับมอบหมาย</div>
  </div>
</div>

<div class="kb mb4">
<?php foreach ($cols as $key => $col): ?>
  <div class="kc">
    <div class="kch">
      <span style="color:<?= $col['col'] ?>"><?= e($col['l']) ?></span>
      <span class="bdg" style="background:<?= $col['bg'] ?>;color:<?= $col['col'] ?>"><?= count($grp[$key]) ?></span>
    </div>
    <div class="kcb">
      <?php if (empty($grp[$key])): ?>
        <div style="text-align:center;padding:18px 8px;color:var(--tx3);font-size:12px">ไม่มีงาน</div>
      <?php else: foreach ($grp[$key] as $t): ?>
        <div class="kcrd" onclick="openTaskModal(<?= (int)$t['id'] ?>)">
          <div class="kcrt"><?= e($t['title']) ?></div>
          <div class="kcrm mb2"><?= e($t['to_name'] ?? '') ?></div>
          <div class="fx g1" style="flex-wrap:wrap;align-items:center">
            <?= urgBadge($t['urgency']) ?>
            <?php if ($t['due_date']): ?>
              <span style="font-size:11px;color:var(--tx3)">ครบ <?= e(thDate($t['due_date'])) ?></span>
            <?php endif ?>
          </div>
          <?php if ($t['response']): ?>
            <div style="margin-top:5px;font-size:11.5px;color:var(--tx2);background:var(--sur2);padding:4px 7px;border-radius:var(--r2)">↩ <?= e(mb_substr($t['response'],0,45,'UTF-8')) ?>…</div>
          <?php endif ?>
        </div>
      <?php endforeach; endif ?>
    </div>
  </div>
<?php endforeach ?>
</div>

<!-- Task detail modal -->
<div class="ov hidden" id="task-modal">
  <div class="mdl">
    <div class="mh">
      <span class="mt" id="tm-title">งาน</span>
      <button class="mx" onclick="closeModal('task-modal')">×</button>
    </div>
    <div class="mb">
      <div class="g2 mb4" id="tm-meta"></div>
      <div class="an mb4" id="tm-note-wrap" style="display:none">
        <div class="an-lbl">คำสั่ง / หมายเหตุ</div>
        <div id="tm-note"></div>
      </div>
      <div id="tm-resp-wrap">
        <div class="fg">
          <label class="fl">เกษียนตอบ / รายงานผล</label>
          <textarea class="fc" id="tm-resp" placeholder="ระบุผลการดำเนินงาน หรือเหตุผลที่ไม่สามารถดำเนินการได้..." style="min-height:90px"></textarea>
        </div>
      </div>
      <div class="alrt ai" id="tm-done-wrap" style="display:none">
        <span>↩ เกษียนตอบ:</span><span id="tm-done-txt"></span>
      </div>
    </div>
    <div class="mf">
      <button class="btn bg" onclick="closeModal('task-modal')">ปิด</button>
      <button class="btn bwn" id="tm-btn-block" onclick="updateTask('blocked')">⚠️ ติดขัด</button>
      <button class="btn bok" id="tm-btn-done"  onclick="updateTask('done')">✅ เสร็จสิ้น</button>
    </div>
  </div>
</div>

<script>
let currentTaskId = null;

const allTasks = <?= json_encode(array_values($tasks), JSON_UNESCAPED_UNICODE) ?>;

function openTaskModal(id) {
    const t = allTasks.find(x => x.id == id);
    if (!t) return;
    currentTaskId = id;
    document.getElementById('tm-title').textContent = 'งาน: ' + t.title;
    document.getElementById('tm-meta').innerHTML =
        `<div><div style="font-size:11.5px;color:var(--tx2)">มอบหมายโดย</div><div style="font-weight:600;font-size:13px">${t.by_name||''}</div></div>
         <div><div style="font-size:11.5px;color:var(--tx2)">ฝ่าย/งาน</div><div>${t.dept||''}</div></div>
         <div><div style="font-size:11.5px;color:var(--tx2)">กำหนดเสร็จ</div><div style="color:var(--er)">${t.due_date||'-'}</div></div>
         <div><div style="font-size:11.5px;color:var(--tx2)">สถานะ</div>${taskBadge(t.status)}</div>`;

    const noteWrap = document.getElementById('tm-note-wrap');
    if (t.note) { noteWrap.style.display=''; document.getElementById('tm-note').textContent=t.note; }
    else noteWrap.style.display='none';

    const isDone = t.status === 'done';
    document.getElementById('tm-resp-wrap').style.display = isDone ? 'none' : '';
    document.getElementById('tm-done-wrap').style.display = isDone ? '' : 'none';
    if (isDone) document.getElementById('tm-done-txt').textContent = ' ' + (t.response||'');
    else document.getElementById('tm-resp').value = t.response || '';

    document.getElementById('tm-btn-block').style.display = isDone ? 'none' : '';
    document.getElementById('tm-btn-done').style.display  = isDone ? 'none' : '';

    openModal('task-modal');
}

function taskBadge(k) {
    const m={todo:['bg-y','รอดำเนินการ'],in_progress:['bg-b','กำลังดำเนินการ'],blocked:['bg-r','ติดขัด'],done:['bg-g','เสร็จสิ้น']};
    const [c,l]=m[k]||['bg-s',k];
    return `<span class="bdg ${c}">${l}</span>`;
}

async function updateTask(status) {
    const resp = document.getElementById('tm-resp')?.value || '';
    try {
        await api('/rvc.rts/api/tasks.php?id='+currentTaskId, 'PUT', { status, response: resp });
        toast('อัพเดทเรียบร้อย ✅','ok');
        closeModal('task-modal');
        setTimeout(() => location.reload(), 800);
    } catch(e) { toast('เกิดข้อผิดพลาด','er'); }
}
</script>
