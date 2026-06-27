<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$me = current_user();
if (!$me || $me['role'] !== 'admin') {
    echo '<div class="alrt ae"><span>🚫</span><span>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</span></div>';
    return;
}

$rmsBaseUrl = fetchValue("SELECT setting_value FROM settings WHERE setting_key='rms_base_url'") ?: '';
$rmsFullUrl = $rmsBaseUrl ? rtrim($rmsBaseUrl, '/') . '/api_connection.php?app_name=nutty&data=people' : '';

// Stats
$totalUsers    = (int)fetchValue('SELECT COUNT(*) FROM users WHERE active=1');
$lastImportRaw = fetchValue("SELECT setting_value FROM settings WHERE setting_key='rms_last_import'") ?: null;
$lastStats     = json_decode(fetchValue("SELECT setting_value FROM settings WHERE setting_key='rms_last_stats'") ?: '{}', true);

function relativeTime(?string $datetime): string {
    if (!$datetime) return 'ยังไม่เคยโอนข้อมูล';
    $diff = time() - strtotime($datetime);
    if ($diff < 60)         return 'เมื่อกี้';
    if ($diff < 3600)       return 'เมื่อ ' . floor($diff/60) . ' นาทีที่แล้ว';
    if ($diff < 86400)      return 'เมื่อ ' . floor($diff/3600) . ' ชั่วโมงที่แล้ว';
    if ($diff < 2592000)    return 'เมื่อ ' . floor($diff/86400) . ' วันที่แล้ว';
    if ($diff < 31536000)   return 'เมื่อ ' . floor($diff/2592000) . ' เดือนที่แล้ว';
    return 'เมื่อ ' . floor($diff/31536000) . ' ปีที่แล้ว';
}

$lastImportRelative = relativeTime($lastImportRaw);
?>

<div class="pgh fxab">
  <div>
    <div class="pgt">🔄 โอนข้อมูลบุคลากร</div>
    <div class="pgs">นำเข้าข้อมูลผู้ใช้งานจากระบบ RMS (<?= e($rmsBaseUrl ?: 'ยังไม่ได้ตั้งค่า URL') ?>)</div>
  </div>
  <?php if (!$rmsBaseUrl): ?>
    <a href="/rvc.rts/?page=settings" class="btn bwn bsm">⚙️ ไปตั้งค่า URL</a>
  <?php endif ?>
</div>

<!-- Status cards -->
<div class="stg mb4" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
  <div class="stc">
    <div class="sti" style="background:#ede9fe">👥</div>
    <div class="fxc"><div class="stv" style="color:#7c3aed"><?= $totalUsers ?></div><div class="stl">ผู้ใช้งานในระบบ</div></div>
  </div>
  <div class="stc">
    <div class="sti" style="background:<?= $rmsBaseUrl ? '#dcfce7' : '#fee2e2' ?>"><?= $rmsBaseUrl ? '🟢' : '🔴' ?></div>
    <div class="fxc">
      <div class="stv" style="font-size:13px;color:<?= $rmsBaseUrl ? 'var(--ok)' : 'var(--er)' ?>"><?= $rmsBaseUrl ? 'พร้อมใช้งาน' : 'ไม่ได้ตั้งค่า' ?></div>
      <div class="stl">สถานะการเชื่อมต่อ</div>
    </div>
  </div>
  <div class="stc" title="<?= e($lastImportRaw ?? '') ?>">
    <div class="sti" style="background:#fef9c3">🕐</div>
    <div class="fxc">
      <div class="stv" style="font-size:13px;color:var(--tx)" id="rel-time"><?= e($lastImportRelative) ?></div>
      <div class="stl"><?= $lastImportRaw ? e(date('d/m/Y H:i', strtotime($lastImportRaw))) : 'โอนข้อมูลล่าสุด' ?></div>
    </div>
  </div>
</div>

<div class="g2">

  <!-- Left: connection info + actions -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <div class="card">
      <div class="ch"><span class="ct">🔗 แหล่งข้อมูล</span></div>
      <div class="cb">
        <div class="fg mb3">
          <label class="fl">Endpoint ที่ใช้งาน</label>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:4px">
            <?php if ($rmsFullUrl): ?>
              <code style="flex:1;font-size:12px;background:var(--sur2);padding:8px 12px;border-radius:var(--r2);border:1px solid var(--bd);word-break:break-all;display:block"><?= e($rmsFullUrl) ?></code>
            <?php else: ?>
              <div class="alrt ae" style="flex:1"><span>⚠️</span><span>ยังไม่ได้ตั้งค่า Base URL — <a href="/rvc.rts/?page=settings" style="color:inherit;font-weight:600">ไปตั้งค่า</a></span></div>
            <?php endif ?>
          </div>
        </div>
        <div class="dv"></div>
        <div style="font-size:12.5px;color:var(--tx2);line-height:1.9">
          <div><strong>Path (hardcoded):</strong> <code>/api_connection.php?app_name=nutty&data=people</code></div>
          <div><strong>Base URL:</strong> ตั้งค่าได้ที่ <a href="/rvc.rts/?page=settings">ตั้งค่าระบบ → การเชื่อมต่อข้อมูลภายนอก</a></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="ch"><span class="ct">📋 เงื่อนไขการโอนข้อมูล</span></div>
      <div class="cb">
        <table style="font-size:12.5px">
          <tbody>
            <tr><td style="color:var(--tx2);padding:5px 12px 5px 0;white-space:nowrap">กรองจาก</td><td><code>people_exit = 0</code> (ยังไม่พ้นสภาพ)</td></tr>
            <tr><td style="color:var(--tx2);padding:5px 12px 5px 0">username</td><td><code>people_id</code></td></tr>
            <tr><td style="color:var(--tx2);padding:5px 12px 5px 0">ชื่อ-สกุล</td><td><code>people_name</code> + <code>people_surname</code></td></tr>
            <tr><td style="color:var(--tx2);padding:5px 12px 5px 0">รหัสผ่าน</td><td><code>ath_pass</code> → bcrypt hash</td></tr>
            <tr><td style="color:var(--tx2);padding:5px 12px 5px 0">อีเมล</td><td><code>people_email</code></td></tr>
            <tr><td style="color:var(--tx2);padding:5px 12px 5px 0">ผู้ใช้ซ้ำ</td><td>อัปเดตชื่อ/อีเมล — <strong>ไม่รีเซ็ต</strong> <code>created_at</code></td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>

  <!-- Right: action panel -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <div class="card">
      <div class="ch"><span class="ct">⚡ ดำเนินการ</span></div>
      <div class="cb">
        <div class="fx g3x mb4" style="flex-direction:column">
          <button class="btn bo" id="btn-test" onclick="rmsTest()" <?= !$rmsBaseUrl?'disabled':'' ?> style="justify-content:center;padding:12px">
            🔍 ทดสอบการเชื่อมต่อ
          </button>
          <button class="btn bp" id="btn-import" onclick="rmsImport()" <?= !$rmsBaseUrl?'disabled':'' ?> style="justify-content:center;padding:14px;font-size:14px">
            🔄 โอนข้อมูลบุคลากร
          </button>
          <button class="btn bo" id="btn-dep" onclick="rmsImportDep()" <?= !$rmsBaseUrl?'disabled':'' ?> style="justify-content:center;padding:12px">
            🏢 โอนข้อมูลฝ่าย/สังกัด
          </button>
        </div>
        <!-- Loading overlay -->
        <div id="rms-loading" style="display:none;margin-top:16px">
          <div class="rms-loader-wrap">
            <div class="rms-circles">
              <div class="rms-c c1"></div>
              <div class="rms-c c2"></div>
              <div class="rms-c c3"></div>
            </div>
            <div class="rms-loader-title" id="rms-loader-title">กำลังเชื่อมต่อ RMS...</div>
            <div class="rms-loader-steps" id="rms-loader-steps">
              <div class="rms-step" id="ls-1">⏳ เชื่อมต่อแหล่งข้อมูล</div>
              <div class="rms-step" id="ls-2">⏳ ดึงข้อมูลบุคลากร</div>
              <div class="rms-step" id="ls-3">⏳ ประมวลผลและบันทึก</div>
            </div>
          </div>
        </div>
        <div id="rms-result"></div>
      </div>
    </div>

    <div class="card">
      <div class="ch"><span class="ct">📊 ผลการโอนข้อมูลล่าสุด</span></div>
      <div class="cb" id="import-log">
        <?php if ($lastImportRaw): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
            <div style="font-size:26px">🕐</div>
            <div>
              <div style="font-weight:600;font-size:14px" id="log-rel"><?= e($lastImportRelative) ?></div>
              <div style="font-size:12px;color:var(--tx2)"><?= e(date('วันที่ d/m/Y เวลา H:i:s', strtotime($lastImportRaw))) ?></div>
            </div>
          </div>
          <?php if (!empty($lastStats)): ?>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;text-align:center">
            <div style="background:#f0fdf4;padding:8px;border-radius:var(--r2)">
              <div style="font-size:18px;font-weight:700;color:var(--ok)"><?= (int)($lastStats['added']??0) ?></div>
              <div style="font-size:11px;color:var(--tx2)">เพิ่มใหม่</div>
            </div>
            <div style="background:var(--pbg);padding:8px;border-radius:var(--r2)">
              <div style="font-size:18px;font-weight:700;color:var(--p)"><?= (int)($lastStats['updated']??0) ?></div>
              <div style="font-size:11px;color:var(--tx2)">อัปเดต</div>
            </div>
            <div style="background:var(--sur2);padding:8px;border-radius:var(--r2)">
              <div style="font-size:18px;font-weight:700;color:var(--tx2)"><?= (int)($lastStats['skipped']??0) ?></div>
              <div style="font-size:11px;color:var(--tx2)">ข้าม</div>
            </div>
          </div>
          <?php endif ?>
        <?php else: ?>
          <div class="empty">ยังไม่มีประวัติการโอนข้อมูล</div>
        <?php endif ?>
      </div>
    </div>

  </div><!-- /right column -->
</div><!-- /.g2 -->

<!-- Confirm import modal -->
<div class="ov hidden" id="confirm-import-modal">
  <div class="mdl" style="max-width:420px;text-align:center">
    <div class="mb" style="padding:32px 28px 20px">
      <div style="width:64px;height:64px;border-radius:50%;background:var(--pbg2);display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 16px">🔄</div>
      <div style="font-size:17px;font-weight:700;color:var(--tx);margin-bottom:10px">ยืนยันการโอนข้อมูล</div>
      <div style="font-size:13.5px;color:var(--tx2);line-height:1.7">
        ระบบจะดึงข้อมูลบุคลากรจาก RMS<br>
        และนำเข้าสู่ระบบสารบรรณทันที<br>
        <span style="color:var(--er);font-size:12.5px;margin-top:8px;display:block">⚠️ ผู้ใช้ที่มีอยู่แล้วจะถูกอัปเดตข้อมูล</span>
      </div>
    </div>
    <div class="mf" style="justify-content:center;gap:12px;padding:16px 24px 24px">
      <button class="btn bg" style="min-width:100px" onclick="closeModal('confirm-import-modal')">ยกเลิก</button>
      <button class="btn bp" style="min-width:140px" onclick="closeModal('confirm-import-modal');doImport()">🔄 โอนข้อมูลเลย</button>
    </div>
  </div>
</div>

<script>
function showLoader(title) {
    document.getElementById('rms-loading').style.display = '';
    document.getElementById('rms-result').innerHTML = '';
    document.getElementById('rms-loader-title').textContent = title;
    ['ls-1','ls-2','ls-3'].forEach(id => {
        const el = document.getElementById(id);
        el.className = 'rms-step';
        el.querySelector ? null : null;
    });
    document.getElementById('ls-1').textContent = '⏳ เชื่อมต่อแหล่งข้อมูล';
    document.getElementById('ls-2').textContent = '⏳ ดึงข้อมูลบุคลากร';
    document.getElementById('ls-3').textContent = '⏳ ประมวลผลและบันทึก';
}
function hideLoader() {
    document.getElementById('rms-loading').style.display = 'none';
}
function stepDone(id, text) {
    const el = document.getElementById(id);
    el.textContent = '✅ ' + text;
    el.classList.add('done');
}

// ── Relative time ─────────────────────────────────────────────────────
let importedAt = <?= $lastImportRaw ? 'new Date("' . str_replace(' ','T',$lastImportRaw) . '")' : 'null' ?>;

function relTime(date) {
    if (!date) return 'ยังไม่เคยโอนข้อมูล';
    const s = Math.floor((Date.now() - date) / 1000);
    if (s < 60)                    return 'เมื่อกี้';
    if (s < 3600)                  return `เมื่อ ${Math.floor(s/60)} นาทีที่แล้ว`;
    if (s < 86400)                 return `เมื่อ ${Math.floor(s/3600)} ชั่วโมงที่แล้ว`;
    if (s < 2592000)               return `เมื่อ ${Math.floor(s/86400)} วันที่แล้ว`;
    if (s < 31536000)              return `เมื่อ ${Math.floor(s/2592000)} เดือนที่แล้ว`;
    return `เมื่อ ${Math.floor(s/31536000)} ปีที่แล้ว`;
}

function updateRelTime() {
    const t = relTime(importedAt);
    ['rel-time','log-rel'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = t;
    });
}

// Tick every 30s
updateRelTime();
setInterval(updateRelTime, 30000);

async function rmsTest() {
    const btn = document.getElementById('btn-test');
    const box = document.getElementById('rms-result');
    btn.disabled = true; btn.textContent = '⏳ กำลังเชื่อมต่อ...';
    showLoader('กำลังทดสอบการเชื่อมต่อ...');
    setTimeout(() => stepDone('ls-1','เชื่อมต่อแหล่งข้อมูล'), 300);
    setTimeout(() => stepDone('ls-2','ดึงข้อมูลบุคลากร'), 900);
    try {
        const res = await api('/rvc.rts/api/import_rms.php?preview=1');
        stepDone('ls-3','วิเคราะห์ข้อมูลเรียบร้อย');
        await new Promise(r => setTimeout(r, 400));
        hideLoader();
        box.innerHTML = `<div class="alrt ai mt3">
            <div>
                <div style="font-weight:600;margin-bottom:8px">✅ เชื่อมต่อสำเร็จ</div>
                <table style="font-size:12.5px;width:100%">
                    <tr><td style="color:var(--tx2);padding:3px 12px 3px 0">ข้อมูลทั้งหมด</td><td><strong>${res.total}</strong> รายการ</td></tr>
                    <tr><td style="color:var(--tx2);padding:3px 12px 3px 0">ยังไม่พ้นสภาพ</td><td><strong style="color:var(--ok)">${res.active_count}</strong> คน</td></tr>
                    <tr><td style="color:var(--tx2);padding:3px 12px 3px 0">มีในระบบแล้ว</td><td><strong style="color:var(--p)">${res.existing}</strong> คน (จะอัปเดต)</td></tr>
                    <tr><td style="color:var(--tx2);padding:3px 12px 3px 0">ผู้ใช้ใหม่</td><td><strong style="color:var(--ok)">${res.new_count}</strong> คน (จะเพิ่ม)</td></tr>
                </table>
            </div>
        </div>`;
    } catch(e) {
        hideLoader();
        box.innerHTML = `<div class="alrt ae mt3"><span>❌</span><span>${e.message}</span></div>`;
    }
    btn.disabled = false; btn.textContent = '🔍 ทดสอบการเชื่อมต่อ';
}

function rmsImport() {
    openModal('confirm-import-modal');
}

async function doImport() {
    const btn = document.getElementById('btn-import');
    const box = document.getElementById('rms-result');
    btn.disabled = true; btn.textContent = '⏳ กำลังโอนข้อมูล...';
    showLoader('กำลังโอนข้อมูลบุคลากร...');
    setTimeout(() => stepDone('ls-1','เชื่อมต่อแหล่งข้อมูลสำเร็จ'), 400);
    setTimeout(() => stepDone('ls-2','ดึงข้อมูลบุคลากรสำเร็จ'), 1100);
    setTimeout(() => { document.getElementById('ls-3').textContent = '⚙️ กำลังประมวลผลและบันทึก...'; }, 1600);
    try {
        const res = await api('/rvc.rts/api/import_rms.php');
        stepDone('ls-3','ประมวลผลและบันทึกเสร็จสิ้น');
        await new Promise(r => setTimeout(r, 500));
        hideLoader();
        let errHtml = '';
        if (res.errors?.length) {
            errHtml = `<div style="margin-top:8px;font-size:12px;color:var(--er);border-top:1px solid var(--bd);padding-top:8px">
                ${res.errors.slice(0,10).map(e=>'⚠ '+e).join('<br>')}
                ${res.errors.length>10?`<br>...และอีก ${res.errors.length-10} รายการ`:''}
            </div>`;
        }
        box.innerHTML = `<div class="alrt ai mt3">
            <div style="width:100%">
                <div style="font-weight:600;margin-bottom:10px;font-size:14px">✅ โอนข้อมูลเสร็จสิ้น</div>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;text-align:center;margin-bottom:8px">
                    <div style="background:#f0fdf4;padding:10px;border-radius:var(--r2)">
                        <div style="font-size:22px;font-weight:700;color:var(--ok)">${res.added}</div>
                        <div style="font-size:11.5px;color:var(--tx2)">เพิ่มใหม่</div>
                    </div>
                    <div style="background:var(--pbg2);padding:10px;border-radius:var(--r2)">
                        <div style="font-size:22px;font-weight:700;color:var(--p)">${res.updated}</div>
                        <div style="font-size:11.5px;color:var(--tx2)">อัปเดต</div>
                    </div>
                    <div style="background:#fff7ed;padding:10px;border-radius:var(--r2)">
                        <div style="font-size:22px;font-weight:700;color:#c2410c">${res.deactivated}</div>
                        <div style="font-size:11.5px;color:var(--tx2)">ปิดใช้งาน</div>
                    </div>
                    <div style="background:var(--sur2);padding:10px;border-radius:var(--r2)">
                        <div style="font-size:22px;font-weight:700;color:var(--tx2)">${res.skipped}</div>
                        <div style="font-size:11.5px;color:var(--tx2)">ข้าม</div>
                    </div>
                </div>
                ${errHtml}
            </div>
        </div>`;

        // Update log panel
        importedAt = new Date();
        updateRelTime();
        const d = importedAt;
        const fmt = `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()+543} เวลา ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}:${String(d.getSeconds()).padStart(2,'0')}`;
        document.getElementById('import-log').innerHTML =
            `<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
               <div style="font-size:26px">🕐</div>
               <div>
                 <div style="font-weight:600;font-size:14px" id="log-rel">เมื่อกี้</div>
                 <div style="font-size:12px;color:var(--tx2)">วันที่ ${fmt}</div>
               </div>
             </div>
             <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;text-align:center">
               <div style="background:#f0fdf4;padding:8px;border-radius:var(--r2)"><div style="font-size:18px;font-weight:700;color:var(--ok)">${res.added}</div><div style="font-size:11px;color:var(--tx2)">เพิ่มใหม่</div></div>
               <div style="background:var(--pbg);padding:8px;border-radius:var(--r2)"><div style="font-size:18px;font-weight:700;color:var(--p)">${res.updated}</div><div style="font-size:11px;color:var(--tx2)">อัปเดต</div></div>
               <div style="background:#fff7ed;padding:8px;border-radius:var(--r2)"><div style="font-size:18px;font-weight:700;color:#c2410c">${res.deactivated}</div><div style="font-size:11px;color:var(--tx2)">ปิดใช้งาน</div></div>
               <div style="background:var(--sur2);padding:8px;border-radius:var(--r2)"><div style="font-size:18px;font-weight:700;color:var(--tx2)">${res.skipped}</div><div style="font-size:11px;color:var(--tx2)">ข้าม</div></div>
             </div>`;

        toast(`โอนข้อมูลสำเร็จ: เพิ่ม ${res.added} | อัปเดต ${res.updated} | ปิดใช้งาน ${res.deactivated}`, 'ok');
    } catch(e) {
        hideLoader();
        box.innerHTML = `<div class="alrt ae mt3"><span>❌</span><span>${e.message}</span></div>`;
    }
    btn.disabled = false; btn.textContent = '🔄 โอนข้อมูลบุคลากร';
}

async function rmsImportDep() {
    const btn = document.getElementById('btn-dep');
    const box = document.getElementById('rms-result');
    btn.disabled = true; btn.textContent = '⏳ กำลังโอน...';
    showLoader('กำลังโอนข้อมูลฝ่าย/สังกัด...');
    setTimeout(() => stepDone('ls-1','เชื่อมต่อแหล่งข้อมูลสำเร็จ'), 300);
    setTimeout(() => stepDone('ls-2','ดึงข้อมูลฝ่าย/สังกัดสำเร็จ'), 800);
    setTimeout(() => { document.getElementById('ls-3').textContent = '⚙️ กำลังบันทึก...'; }, 1200);
    try {
        const res = await api('/rvc.rts/api/import_rms.php?action=dep');
        stepDone('ls-3','บันทึกเสร็จสิ้น');
        await new Promise(r => setTimeout(r, 400));
        hideLoader();
        box.innerHTML = `<div class="alrt ai mt3">
            <div>
                <div style="font-weight:600;margin-bottom:10px">✅ โอนข้อมูลฝ่าย/สังกัดเสร็จสิ้น</div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;text-align:center">
                    <div style="background:#f0fdf4;padding:10px;border-radius:var(--r2)">
                        <div style="font-size:22px;font-weight:700;color:var(--ok)">${res.added}</div>
                        <div style="font-size:11.5px;color:var(--tx2)">เพิ่มใหม่</div>
                    </div>
                    <div style="background:var(--pbg2);padding:10px;border-radius:var(--r2)">
                        <div style="font-size:22px;font-weight:700;color:var(--p)">${res.updated}</div>
                        <div style="font-size:11.5px;color:var(--tx2)">อัปเดต</div>
                    </div>
                    <div style="background:var(--sur2);padding:10px;border-radius:var(--r2)">
                        <div style="font-size:22px;font-weight:700;color:var(--tx2)">${res.total}</div>
                        <div style="font-size:11.5px;color:var(--tx2)">ทั้งหมด</div>
                    </div>
                </div>
            </div>
        </div>`;
        toast(`โอนฝ่าย/สังกัดสำเร็จ: เพิ่ม ${res.added} | อัปเดต ${res.updated}`, 'ok');
    } catch(e) {
        hideLoader();
        box.innerHTML = `<div class="alrt ae mt3"><span>❌</span><span>${e.message}</span></div>`;
    }
    btn.disabled = false; btn.textContent = '🏢 โอนข้อมูลฝ่าย/สังกัด';
}
</script>
