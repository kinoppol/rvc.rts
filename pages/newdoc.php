<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Generate next doc number
$lastNo = fetchValue("SELECT doc_number FROM documents_in ORDER BY id DESC LIMIT 1");
$nextNum = 1;
if ($lastNo && preg_match('/(\d+)/', $lastNo, $m)) $nextNum = (int)$m[1] + 1;
$nextDocNo = sprintf('รบ.%03d/2568', $nextNum);

$DEPTS = ['ฝ่ายบริหารทรัพยากร','ฝ่ายยุทธศาสตร์และแผนงาน','ฝ่ายกิจการนักเรียน นักศึกษา','ฝ่ายวิชาการ',
          'งานบริหารงานทั่วไป','งานการเงิน','งานพัสดุ','งานทะเบียน',
          'แผนกช่างยนต์','แผนกช่างอิเล็กทรอนิกส์','แผนกคอมพิวเตอร์ธุรกิจ'];
?>

<div class="pgh">
  <div class="pgt">📥 รับเอกสารใหม่</div>
  <div class="pgs">นำเอกสารเข้าระบบสารบรรณ</div>
</div>

<div class="card" id="wizard-card">
  <div class="cb">

    <!-- Steps -->
    <div class="stps mb5" id="wizard-steps">
      <?php
      $steps = ['อัปโหลดเอกสาร','เกษียนหนังสือ','กำหนดผู้เกี่ยวข้อง','ยืนยันและส่ง'];
      foreach ($steps as $i => $s):
        $cls = $i === 0 ? 'spn-a' : 'spn-p';
        $lbl = $i === 0 ? 'spl-a' : '';
      ?>
        <div class="stp">
          <div class="spn <?= $cls ?>" data-step-num="<?= $i ?>"><?= $i+1 ?></div>
          <div class="spl <?= $lbl ?>" data-step-lbl="<?= $i ?>"><?= e($s) ?></div>
        </div>
        <?php if ($i < count($steps)-1): ?>
          <div class="spline" data-step-line="<?= $i ?>"></div>
        <?php endif ?>
      <?php endforeach ?>
    </div>

    <!-- Hidden file input for upload -->
    <input type="file" id="file-input" accept="application/pdf" style="display:none">

    <!-- Step 0: Basic info + upload -->
    <div id="step-0">
      <div class="g2 mb3">
        <div class="fg">
          <label class="fl">เลขที่รับ <span class="req">*</span></label>
          <input class="fc" id="f-docno" value="<?= e($nextDocNo) ?>" readonly style="background:var(--sur2)">
        </div>
        <div class="fg">
          <label class="fl">วันที่รับ <span class="req">*</span></label>
          <input class="fc" type="date" id="f-date" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="fg">
        <label class="fl">จาก (หน่วยงาน/บุคคล) <span class="req">*</span></label>
        <div class="org-wrap" id="org-wrap">
          <input class="fc" id="f-from" autocomplete="off" placeholder="พิมพ์เพื่อค้นหาหน่วยงาน..." oninput="orgSearch(this.value)" onfocus="orgSearch(this.value)">
          <div class="org-dd hidden" id="org-dd"></div>
        </div>
        <div id="org-add-row" class="hidden mt2" style="display:none">
          <div class="alrt aw" style="padding:8px 12px;display:flex;align-items:center;gap:10px;font-size:13px">
            <span>ไม่พบในรายการ —</span>
            <button type="button" class="btn bp bsm" onclick="orgAddNew()">➕ เพิ่มหน่วยงานใหม่</button>
          </div>
        </div>
      </div>
      <div class="fg">
        <label class="fl">ชื่อย่อหน่วยงาน</label>
        <input class="fc" id="f-short" placeholder="เช่น สอศ." readonly style="background:var(--sur2)">
      </div>
      <div class="fg">
        <label class="fl">เรื่อง <span class="req">*</span></label>
        <input class="fc" id="f-subject" placeholder="ระบุเรื่องของหนังสือ">
      </div>
      <div class="g2 mb3">
        <div class="fg">
          <label class="fl">ประเภทหนังสือ</label>
          <select class="fc" id="f-doctype">
            <option value="ราชการ">หนังสือราชการ</option>
            <option value="เอกชน">หนังสือเอกชน</option>
            <option value="บุคคล">จากบุคคลทั่วไป</option>
          </select>
        </div>
        <div class="fg">
          <label class="fl">จำนวนหน้า</label>
          <input class="fc" type="number" id="f-pages" placeholder="0" min="0">
        </div>
      </div>
      <div class="fg">
        <label class="fl">ไฟล์เอกสาร PDF</label>
        <div class="upz" id="upload-zone">
          <div style="font-size:32px;margin-bottom:6px">📄</div>
          <div style="color:var(--tx2)">คลิกเพื่ออัปโหลด PDF <em style="color:var(--tx3);font-size:12px">(ขนาดไม่เกิน 20MB)</em></div>
        </div>
        <div id="upload-display" style="display:none"></div>
      </div>
    </div>

    <!-- Step 1: Annotation -->
    <div id="step-1" style="display:none">
      <div class="alrt ai mb4"><span>ℹ️</span><span>เกษียนหนังสือ คือการใส่ความเห็นสรุปเพื่อเสนอผู้บังคับบัญชา พร้อมกำหนดความเร่งด่วนและระดับความลับ</span></div>
      <div class="fg">
        <label class="fl">ความเห็น / สรุปเรื่อง (เกษียน)</label>
        <textarea class="fc" id="f-annot" placeholder="เรียน รองฯ ฝ่ายบริหารฯ — ..." style="min-height:100px"></textarea>
      </div>
      <div class="g2">
        <div class="fg">
          <label class="fl">ความเร่งด่วน <span class="req">*</span></label>
          <select class="fc" id="f-urgency">
            <option value="normal">ปกติ (ไม่เร่งด่วน)</option>
            <option value="urgent">เร่งด่วน (ภายใน 3 วันทำการ)</option>
            <option value="critical">ด่วนที่สุด (ไม่เกิน 2 วัน)</option>
          </select>
        </div>
        <div class="fg">
          <label class="fl">ระดับความลับ <span class="req">*</span></label>
          <select class="fc" id="f-secrecy">
            <option value="none">ไม่ลับ (เปิดเผยได้ทั่วไป)</option>
            <option value="secret">ลับ (เฉพาะผู้ที่เกี่ยวข้อง)</option>
            <option value="top_secret">ลับที่สุด (เฉพาะผู้ที่ระบุ)</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Step 2: Departments -->
    <div id="step-2" style="display:none">
      <div class="fg">
        <label class="fl">กำหนดฝ่าย/งาน/แผนกที่เกี่ยวข้อง</label>
        <select class="fc" id="dept-select">
          <option value="">-- เลือกฝ่าย/งาน --</option>
          <?php foreach ($DEPTS as $d): ?>
            <option value="<?= e($d) ?>"><?= e($d) ?></option>
          <?php endforeach ?>
        </select>
        <div class="chips mt3" id="dept-chips"></div>
        <input type="hidden" id="f-depts">
      </div>
      <div class="alrt ai mt3"><span>ℹ️</span><span>ระดับลับที่สุด: รายชื่อผู้เข้าถึงจะถูกกำหนดโดยผู้อำนวยการเท่านั้น</span></div>
    </div>

    <!-- Step 3: Summary -->
    <div id="step-3" style="display:none">
      <div class="alrt ai mb4"><span>✅</span><span>กรุณาตรวจสอบข้อมูลก่อนส่งเข้าระบบ</span></div>
      <div class="card">
        <div class="cb">
          <div class="g2 mb3">
            <div><div style="font-size:11.5px;color:var(--tx2);margin-bottom:3px">เลขที่รับ</div><div style="font-weight:600" id="sum-docno"></div></div>
            <div><div style="font-size:11.5px;color:var(--tx2);margin-bottom:3px">วันที่</div><div id="sum-date"></div></div>
            <div><div style="font-size:11.5px;color:var(--tx2);margin-bottom:3px">จาก</div><div id="sum-from"></div></div>
            <div><div style="font-size:11.5px;color:var(--tx2);margin-bottom:3px">เรื่อง</div><div id="sum-subj"></div></div>
          </div>
          <div class="dv"></div>
          <div class="fx g3x mb3" id="sum-badges"></div>
          <div class="an" id="sum-annot-wrap" style="display:none">
            <div class="an-lbl">เกษียนหนังสือ</div>
            <div id="sum-annot"></div>
          </div>
          <div class="chips mt3" id="sum-depts"></div>
        </div>
      </div>
    </div>

    <div class="dv"></div>
    <div class="fx g3x" style="justify-content:flex-end">
      <button class="btn bg" id="btn-prev" style="display:none" onclick="wizardStep(-1)">← ย้อนกลับ</button>
      <button class="btn bp" id="btn-next" onclick="wizardStep(1)">ถัดไป →</button>
      <button class="btn bok" id="btn-save" style="display:none" onclick="wizardSave()">✅ บันทึกเข้าระบบ</button>
    </div>
  </div>
</div>

<script>
let wStep = 0;
const STEP_COUNT = 4;
const urgLabels = {normal:'ปกติ',urgent:'เร่งด่วน',critical:'ด่วนที่สุด'};
const urgClass  = {normal:'bg-g',urgent:'bg-y',critical:'bg-r'};
const secLabels = {none:'ไม่ลับ',secret:'ลับ',top_secret:'ลับที่สุด'};
const secClass  = {none:'bg-s',secret:'bg-o',top_secret:'bg-r'};

function wizardStep(dir) {
    if (dir > 0 && !validateStep(wStep)) return;
    wStep = Math.max(0, Math.min(STEP_COUNT - 1, wStep + dir));
    renderWizard();
}

function validateStep(s) {
    if (s === 0) {
        if (!document.getElementById('f-from').value.trim()) { toast('กรุณาระบุหน่วยงาน/บุคคลที่ส่ง','er'); return false; }
        if (!document.getElementById('f-subject').value.trim()) { toast('กรุณาระบุเรื่องของหนังสือ','er'); return false; }
    }
    return true;
}

function renderWizard() {
    for (let i = 0; i < STEP_COUNT; i++) {
        document.getElementById('step-'+i).style.display = i === wStep ? '' : 'none';
    }
    // Steps indicator
    document.querySelectorAll('[data-step-num]').forEach(el => {
        const i = +el.dataset.stepNum;
        el.className = 'spn ' + (i < wStep ? 'spn-d' : i === wStep ? 'spn-a' : 'spn-p');
        el.textContent = i < wStep ? '✓' : i + 1;
    });
    document.querySelectorAll('[data-step-lbl]').forEach(el => {
        el.className = 'spl' + (+el.dataset.stepLbl === wStep ? ' spl-a' : '');
    });
    document.querySelectorAll('[data-step-line]').forEach(el => {
        el.className = 'spline' + (+el.dataset.stepLine < wStep ? ' spline-d' : '');
    });

    document.getElementById('btn-prev').style.display = wStep > 0 ? '' : 'none';
    document.getElementById('btn-next').style.display = wStep < STEP_COUNT - 1 ? '' : 'none';
    document.getElementById('btn-save').style.display = wStep === STEP_COUNT - 1 ? '' : 'none';

    if (wStep === STEP_COUNT - 1) fillSummary();
}

function fillSummary() {
    const docno   = document.getElementById('f-docno').value;
    const date    = document.getElementById('f-date').value;
    const from    = document.getElementById('f-from').value;
    const subj    = document.getElementById('f-subject').value;
    const urgency = document.getElementById('f-urgency').value;
    const secrecy = document.getElementById('f-secrecy').value;
    const annot   = document.getElementById('f-annot').value;
    const depts   = document.getElementById('f-depts').value;

    document.getElementById('sum-docno').textContent = docno;
    document.getElementById('sum-date').textContent  = date;
    document.getElementById('sum-from').textContent  = from;
    document.getElementById('sum-subj').textContent  = subj;
    document.getElementById('sum-badges').innerHTML  =
        `<span class="bdg ${urgClass[urgency]}">${urgLabels[urgency]}</span>` +
        `<span class="bdg ${secClass[secrecy]}">${secLabels[secrecy]}</span>`;
    const annotWrap = document.getElementById('sum-annot-wrap');
    if (annot) { annotWrap.style.display = ''; document.getElementById('sum-annot').textContent = annot; }
    else annotWrap.style.display = 'none';

    const deptsEl = document.getElementById('sum-depts');
    deptsEl.innerHTML = depts ? depts.split(',').filter(Boolean).map(d => `<span class="chip">${d}</span>`).join('') : '';
}

async function wizardSave() {
    const btn = document.getElementById('btn-save');
    btn.disabled = true; btn.textContent = 'กำลังบันทึก...';
    try {
        // Upload file if selected
        let filename = null;
        const fi = document.getElementById('file-input');
        if (fi.files[0]) {
            const fd = new FormData();
            fd.append('file', fi.files[0]);
            const r = await api('/rvc.rts/api/upload.php', 'POST', fd);
            filename = r.filename;
        }

        const payload = {
            doc_number:   document.getElementById('f-docno').value,
            received_date:document.getElementById('f-date').value,
            from_org:     document.getElementById('f-from').value,
            from_short:   document.getElementById('f-short').value,
            subject:      document.getElementById('f-subject').value,
            doc_type:     document.getElementById('f-doctype').value,
            pages:        document.getElementById('f-pages').value,
            urgency:      document.getElementById('f-urgency').value,
            secrecy:      document.getElementById('f-secrecy').value,
            annotation:   document.getElementById('f-annot').value,
            depts:        document.getElementById('f-depts').value,
            file_path:    filename,
        };
        await api('/rvc.rts/api/documents.php', 'POST', payload);
        toast('บันทึกเรียบร้อย ✅', 'ok');
        setTimeout(() => { window.location = '/rvc.rts/?page=register'; }, 1000);
    } catch (e) {
        toast('เกิดข้อผิดพลาด: ' + e.message, 'er');
        btn.disabled = false; btn.textContent = '✅ บันทึกเข้าระบบ';
    }
}

// ── Organization autocomplete ──────────────────────────────────────────
let orgTimer = null;
let orgSelected = false;

async function orgSearch(q) {
    clearTimeout(orgTimer);
    orgSelected = false;
    const dd = document.getElementById('org-dd');
    const addRow = document.getElementById('org-add-row');
    if (q.length < 1) { dd.classList.add('hidden'); addRow.style.display='none'; return; }
    orgTimer = setTimeout(async () => {
        try {
            const res = await api('/rvc.rts/api/orgs.php?q=' + encodeURIComponent(q));
            const items = res.data || [];
            if (items.length === 0) {
                dd.classList.add('hidden');
                addRow.style.display = '';
                return;
            }
            addRow.style.display = 'none';
            dd.innerHTML = items.map(o =>
                `<div class="org-item" onmousedown="orgSelect(${JSON.stringify(o.name)},${JSON.stringify(o.short_name||'')})" >
                    <span class="org-name">${esc(o.name)}</span>
                    ${o.short_name ? `<span class="org-short">${esc(o.short_name)}</span>` : ''}
                </div>`
            ).join('') +
            `<div class="org-item org-add" onmousedown="orgAddNew()">➕ เพิ่ม "${esc(q)}" เป็นหน่วยงานใหม่</div>`;
            dd.classList.remove('hidden');
        } catch(e) {}
    }, 200);
}

function orgSelect(name, short) {
    document.getElementById('f-from').value  = name;
    document.getElementById('f-short').value = short;
    document.getElementById('org-dd').classList.add('hidden');
    document.getElementById('org-add-row').style.display = 'none';
    orgSelected = true;
}

async function orgAddNew() {
    const name  = document.getElementById('f-from').value.trim();
    const short = document.getElementById('f-short').value.trim();
    if (!name) { toast('กรุณาพิมพ์ชื่อหน่วยงานก่อน','er'); return; }
    try {
        const res = await api('/rvc.rts/api/orgs.php','POST',{name,short_name:short,type:'government'});
        toast('เพิ่ม "' + name + '" เรียบร้อย','ok');
        document.getElementById('org-dd').classList.add('hidden');
        document.getElementById('org-add-row').style.display='none';
        orgSelected = true;
    } catch(e) {
        if (e.message && e.message.includes('409')) { toast('มีหน่วยงานนี้อยู่แล้ว','aw'); orgSelected=true; }
        else toast('เพิ่มไม่สำเร็จ','er');
    }
}

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// Close dropdown on outside click
document.addEventListener('click', e => {
    if (!document.getElementById('org-wrap')?.contains(e.target)) {
        document.getElementById('org-dd')?.classList.add('hidden');
    }
});

// Init upload zone
document.addEventListener('DOMContentLoaded', () => {
    initUploadZone('upload-zone', 'file-input', 'upload-display');
    initChips('dept-select', 'dept-chips', 'f-depts');
});
</script>
