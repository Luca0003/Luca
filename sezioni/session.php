<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../config/db.php';
function flash(string $key, ?string $message = null) {
    if ($message !== null) { $_SESSION['flash'][$key] = $message; return; }
    if (!empty($_SESSION['flash'][$key])) { $m = $_SESSION['flash'][$key]; unset($_SESSION['flash'][$key]); return $m; }
    return null;
}
function csrf_token() : string {
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf_token'];
}
function check_csrf() : void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) { http_response_code(400); exit('CSRF token non valido.'); }
    }
}
function current_user() : ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $cache = $stmt->fetch() ?: null;
    return $cache;
}
function require_login() : void {
    if (!current_user()) {
        flash('error','Devi accedere per continuare.');
        header('Location: login.php?ref=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
        exit;
    }
}
function login_user(int $user_id) : void { $_SESSION['user_id'] = $user_id; }
function logout_user() : void { unset($_SESSION['user_id']); session_destroy(); }
