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
    if (!$user || !password_verify($password, $user['password'])) return false;
    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    return $user;
}

function logout(): void {
    session_start_safe();
    session_destroy();
}

function can(string $role, array $user): bool {
    $hierarchy = ['admin' => 99, 'director' => 5, 'deputy' => 4, 'head' => 3, 'dept_head' => 2, 'teacher' => 1, 'staff' => 0];
    return ($hierarchy[$user['role']] ?? -1) >= ($hierarchy[$role] ?? 0);
}
