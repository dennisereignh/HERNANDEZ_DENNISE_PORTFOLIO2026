<?php
// Session + CSRF helpers used by everything inside /admin.
const SESSION_IDLE_SECONDS = 7200; // auto log-out after 2 hours of no activity

function start_admin_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if ($path === '') { $path = '/'; }
    session_name('dh_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $path,
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}

function csrf_ok($token): bool {
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function admin_logged_in(): bool {
    if (empty($_SESSION['admin_id'])) { return false; }
    if (time() - (int)($_SESSION['last'] ?? 0) > SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        return false;
    }
    $_SESSION['last'] = time();
    return true;
}

function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
