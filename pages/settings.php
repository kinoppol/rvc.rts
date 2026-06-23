<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Load all settings
$rows = fetchAll('SELECT setting_key, setting_value FROM settings');
$cfg  = array_column($rows, 'setting_value', 'setting_key');

// Handle save
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_section'])) {
    $section = $_POST['save_section'];
    $keys = match($section) {
        'school'   => ['school_name','school_under','school_prov','academic_year'],
        'profile'  => ['profile_name','profile_email'],
        'notif'    => ['notif_new_doc','notif_task','notif_due','notif_assign'],
        default    => []
    };
    foreach ($keys as $k) {
        $v = $_POST[$k] ?? '';
        query('INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?', [$k,$v,$v]);
    }
    $saved = true;
    // Reload
    $rows = fetchAll('SELECT setting_key, setting_value FROM settings');
    $cfg  = array_column($rows, 'setting_value', 'setting_key');
}
?>

<div class="pgh">
  <div class="pgt">⚙️ ตั้งค่าระบบ</div>
  <div class="pgs">กำหนดค่าระบบบริหารงานสถานศึกษา</div>
</div>

<?php if ($saved): ?>
<div class="alrt ai mb4"><span>✅</span><span>บันทึกการตั้งค่าเรียบร้อยแล้ว</span></div>
<?php endif ?>

<div class="g2">
  <!-- School info -->
  <div class="card">
    <div class="ch"><span class="ct">🏫 ข้อมูลสถานศึกษา</span></div>
    <form method="post" class="cb">
      <input type="hidden" name="save_section" value="school">
      <div class="fg"><label class="fl">ชื่อสถานศึกษา</label><input class="fc" name="school_name" value="<?= e($cfg['school_name'] ?? '') ?>"></div>
      <div class="fg"><label class="fl">สังกัด</label><input class="fc" name="school_under" value="<?= e($cfg['school_under'] ?? '') ?>"></div>
      <div class="fg"><label class="fl">จังหวัด</label><input class="fc" name="school_prov" value="<?= e($cfg['school_prov'] ?? '') ?>"></div>
      <div class="fg"><label class="fl">ปีการศึกษา</label><input class="fc" name="academic_year" value="<?= e($cfg['academic_year'] ?? '') ?>"></div>
      <button class="btn bp" type="submit">💾 บันทึก</button>
    </form>
  </div>

  <!-- Notifications -->
  <div class="card">
    <div class="ch"><span class="ct">🔔 การแจ้งเตือน</span></div>
    <form method="post" class="cb">
      <input type="hidden" name="save_section" value="notif">
      <?php
      $notifs = ['notif_new_doc'=>'หนังสือรับใหม่','notif_task'=>'งานที่ได้รับมอบหมาย','notif_due'=>'งานใกล้ครบกำหนด','notif_assign'=>'การอนุมัติ/มอบหมาย'];
      foreach ($notifs as $k => $lbl): $on = ($cfg[$k] ?? '1') === '1'; ?>
        <div class="fxab mb3">
          <span style="font-size:13.5px"><?= e($lbl) ?></span>
          <label style="position:relative;width:40px;height:22px;cursor:pointer">
            <input type="checkbox" name="<?= e($k) ?>" value="1" <?= $on?'checked':'' ?> style="opacity:0;width:0;height:0">
            <span style="position:absolute;inset:0;border-radius:11px;background:<?= $on?'var(--p)':'var(--bd)' ?>;transition:background .2s" id="tog-<?= e($k) ?>">
              <span style="position:absolute;width:18px;height:18px;border-radius:50%;background:#fff;top:2px;<?= $on?'right:2px':'left:2px' ?>;box-shadow:0 1px 3px rgba(0,0,0,.3)"></span>
            </span>
          </label>
        </div>
      <?php endforeach ?>
      <button class="btn bp" type="submit">💾 บันทึก</button>
    </form>
  </div>

  <!-- Theme -->
  <div class="card">
    <div class="ch"><span class="ct">🎨 การแสดงผล</span></div>
    <div class="cb">
      <div class="fl mb3">โหมดการแสดงผล</div>
      <div class="fx g3x" style="flex-wrap:wrap">
        <?php foreach (['light'=>['☀️','สว่าง'],'dark'=>['🌙','มืด'],'system'=>['💻','ตามระบบ']] as $t => [$ic,$lbl]): ?>
          <div style="flex:1;min-width:70px;padding:14px 12px;border:2px solid var(--bd);border-radius:var(--r);text-align:center;cursor:pointer;transition:all .15s"
               class="theme-opt" data-theme="<?= $t ?>" onclick="selectTheme('<?= $t ?>')">
            <div style="font-size:22px;margin-bottom:5px"><?= $ic ?></div>
            <div style="font-size:12.5px"><?= e($lbl) ?></div>
          </div>
        <?php endforeach ?>
      </div>
    </div>
  </div>

  <!-- Account -->
  <div class="card">
    <div class="ch"><span class="ct">👤 บัญชีผู้ใช้</span></div>
    <form method="post" class="cb">
      <input type="hidden" name="save_section" value="profile">
      <div class="fg"><label class="fl">ชื่อ-สกุล</label><input class="fc" name="profile_name" value="<?= e($cfg['profile_name'] ?? '') ?>"></div>
      <div class="fg"><label class="fl">อีเมล</label><input class="fc" type="email" name="profile_email" value="<?= e($cfg['profile_email'] ?? '') ?>"></div>
      <button class="btn bp" type="submit">💾 บันทึก</button>
    </form>
  </div>
</div>

<script>
function selectTheme(t) {
    document.querySelectorAll('.theme-opt').forEach(el => {
        const active = el.dataset.theme === t;
        el.style.borderColor = active ? 'var(--p)' : 'var(--bd)';
        el.style.background  = active ? 'var(--pbg)' : 'transparent';
        el.querySelector('div:last-child').style.fontWeight = active ? '600' : '400';
        el.querySelector('div:last-child').style.color = active ? 'var(--p)' : 'var(--tx2)';
    });
    localStorage.setItem('rts_theme', t);
    applyTheme(t);
    toast('เปลี่ยนธีมเรียบร้อย','ok');
}

document.addEventListener('DOMContentLoaded', () => {
    const cur = localStorage.getItem('rts_theme') || 'light';
    selectTheme(cur);
});
</script>
