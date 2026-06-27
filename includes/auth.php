<?php
require_once __DIR__ . '/db.php';

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function current_user(): array|false {
    session_start_safe();
    if (empty($_SESSION['user_id'])) return false;
    return fetchOne('SELECT * FROM users WHERE id=? AND active=1', [$_SESSION['user_id']]);
}

function require_auth(): array {
    $user = current_user();
    if (!$user) {
        header('Location: /rvc.rts/login.php');
        exit;
    }
    return $user;
}

function login(string $username, string $password): array|false {
    $user = fetchOne('SELECT * FROM users WHERE username=? AND active=1', [$username]);
    if (!$user) return false;

    $stored = $user['password'];
    $ok = false;

    if (password_verify($password, $stored)) {
        // bcrypt — ปกติ
        $ok = true;
    } elseif (strlen($stored) === 32 && ctype_xdigit($stored) && hash_equals($stored, md5($password))) {
        // MD5 จาก RMS — auto-upgrade เป็น bcrypt
        $ok = true;
        update('users', ['password' => password_hash($password, PASSWORD_BCRYPT)], 'id=?', [$user['id']]);
    }

    if (!$ok) return false;
    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    return $user;
}

function logout(): void {
    session_start_safe();
    // If impersonating, restore admin instead of full logout
    if (!empty($_SESSION['impersonate_admin_id'])) {
        $adminId = $_SESSION['impersonate_admin_id'];
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['user_id'] = $adminId;
        return;
    }
    session_destroy();
}

function impersonate(int $targetId): bool {
    session_start_safe();
    $me = current_user();
    if (!$me || $me['role'] !== 'admin') return false;
    if ($targetId === (int)$me['id']) return false;
    $target = fetchOne('SELECT id, role FROM users WHERE id=? AND active=1', [$targetId]);
    if (!$target || $target['role'] === 'admin') return false;
    session_regenerate_id(true);
    $_SESSION['user_id']             = $targetId;
    $_SESSION['impersonate_admin_id'] = (int)$me['id'];
    return true;
}

function is_impersonating(): bool {
    session_start_safe();
    return !empty($_SESSION['impersonate_admin_id']);
}

function can(string $role, array $user): bool {
    $hierarchy = ['admin' => 99, 'director' => 5, 'deputy' => 4, 'head' => 3, 'dept_head' => 2, 'teacher' => 1, 'staff' => 0];
    return ($hierarchy[$user['role']] ?? -1) >= ($hierarchy[$role] ?? 0);
}
