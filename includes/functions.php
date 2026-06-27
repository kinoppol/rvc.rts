<?php
function e(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function badge(string $class, string $label): string {
    return '<span class="bdg ' . e($class) . '">' . e($label) . '</span>';
}

const URG = [
    'normal'   => ['l' => 'ปกติ',        'c' => 'bg-g'],
    'urgent'   => ['l' => 'เร่งด่วน',    'c' => 'bg-y'],
    'critical' => ['l' => 'ด่วนที่สุด',  'c' => 'bg-r'],
];
const SEC = [
    'none'       => ['l' => 'ไม่ลับ',       'c' => 'bg-s'],
    'secret'     => ['l' => 'ลับ',           'c' => 'bg-o'],
    'top_secret' => ['l' => 'ลับที่สุด',    'c' => 'bg-r'],
];
const DOC_STATUS = [
    'pending_annotation' => ['l' => 'รอเกษียน',           'c' => 'bg-y'],
    'pending_deputy'     => ['l' => 'รอรองฯ',            'c' => 'bg-o'],
    'pending_director'   => ['l' => 'รอผอ.พิจารณา',     'c' => 'bg-v'],
    'assigned'           => ['l' => 'มอบหมายแล้ว',       'c' => 'bg-b'],
    'in_progress'        => ['l' => 'กำลังดำเนินการ',   'c' => 'bg-p'],
    'done'               => ['l' => 'เสร็จสิ้น',         'c' => 'bg-g'],
    'blocked'            => ['l' => 'ติดขัด',             'c' => 'bg-r'],
];
const TASK_STATUS = [
    'todo'        => ['l' => 'รอดำเนินการ',       'c' => 'bg-y'],
    'in_progress' => ['l' => 'กำลังดำเนินการ',   'c' => 'bg-b'],
    'blocked'     => ['l' => 'ติดขัด',             'c' => 'bg-r'],
    'done'        => ['l' => 'เสร็จสิ้น',          'c' => 'bg-g'],
];
const USER_ROLES = [
    'admin'     => ['l' => 'ผู้ดูแลระบบ',   'c' => 'bg-r'],
    'director'  => ['l' => 'ผู้อำนวยการ',   'c' => 'bg-v'],
    'deputy'    => ['l' => 'รองผู้อำนวยการ', 'c' => 'bg-b'],
    'head'      => ['l' => 'หัวหน้างาน',    'c' => 'bg-p'],
    'dept_head' => ['l' => 'หัวหน้าแผนก',  'c' => 'bg-s'],
    'teacher'   => ['l' => 'ครู',            'c' => 'bg-g'],
    'staff'     => ['l' => 'เจ้าหน้าที่',   'c' => 'bg-s'],
];

function urgBadge(string $k): string {
    $m = URG[$k] ?? URG['normal'];
    return badge($m['c'], $m['l']);
}
function secBadge(string $k): string {
    $m = SEC[$k] ?? SEC['none'];
    return badge($m['c'], $m['l']);
}
function statusBadge(string $k): string {
    $m = DOC_STATUS[$k] ?? ['l' => $k, 'c' => 'bg-s'];
    return badge($m['c'], $m['l']);
}
function taskBadge(string $k): string {
    $m = TASK_STATUS[$k] ?? ['l' => $k, 'c' => 'bg-s'];
    return badge($m['c'], $m['l']);
}
function roleBadge(string $k): string {
    $m = USER_ROLES[$k] ?? ['l' => $k, 'c' => 'bg-s'];
    return badge($m['c'], $m['l']);
}

function thDate(string $d): string {
    if (!$d) return '';
    $ts = strtotime($d);
    if (!$ts) return $d;
    $thMonths = ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
    $m = (int)date('n', $ts);
    $y = (int)date('Y', $ts) + 543;
    return date('j', $ts) . ' ' . $thMonths[$m] . ' ' . ($y - 2500 + 68); // display as 68
}

function nextDocNumber(PDO $db = null): string {
    require_once __DIR__ . '/db.php';
    $count = (int)fetchValue('SELECT COUNT(*)+1 FROM documents_in');
    $year  = (int)date('Y') + 543 - 2500; // Buddhist year short
    return sprintf('รบ.%03d/%d', $count, 2500 + $year);
}

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function avatarChars(string $name): string {
    $chars = mb_substr($name, -2, 2, 'UTF-8');
    return $chars ?: mb_substr($name, 0, 2, 'UTF-8');
}

function avatarHtml(array $user, string $size = '34px', string $fontSize = '12px'): string {
    $nick = trim($user['nickname'] ?? '');
    $av   = $nick ? e(mb_substr($nick, 0, 3, 'UTF-8')) : e(avatarChars($user['name'] ?? ''));
    $name = e($user['name'] ?? '');
    if (!empty($user['avatar'])) {
        $src = '/rvc.rts/uploads/avatars/' . e($user['avatar']);
        return "<img src=\"{$src}\" alt=\"{$name}\" title=\"{$name}\" style=\"width:{$size};height:{$size};border-radius:50%;object-fit:cover;flex-shrink:0\" onerror=\"this.outerHTML='<span style=&quot;width:{$size};height:{$size};border-radius:50%;background:var(--p);color:#fff;display:flex;align-items:center;justify-content:center;font-size:{$fontSize};font-weight:700;flex-shrink:0&quot;>{$av}</span>'\">";
    }
    return "<span style=\"width:{$size};height:{$size};border-radius:50%;background:var(--p);color:#fff;display:flex;align-items:center;justify-content:center;font-size:{$fontSize};font-weight:700;flex-shrink:0\">{$av}</span>";
}
