<?php
// ============================================================
// KHETBAZAAR - Database & App Configuration
// File: php/config.php
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');         // XAMPP default has no password
define('DB_NAME', 'khetbazaar');
define('SITE_URL', 'http://localhost/agri_marketplace');
define('UPLOAD_PATH', __DIR__ . '/../uploads/crops/');
define('UPLOAD_URL',  SITE_URL . '/uploads/crops/');

// ── Database connection (singleton) ──────────────────────────
function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            http_response_code(500);
            die(json_encode(['success'=>false,'message'=>'DB Error: '.$conn->connect_error]));
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// ── Session helpers ───────────────────────────────────────────
function startSession() {
    if (session_status() === PHP_SESSION_NONE) session_start();
}
function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']);
}
function currentUser() {
    startSession();
    return [
        'id'    => $_SESSION['user_id']   ?? null,
        'name'  => $_SESSION['user_name'] ?? null,
        'role'  => $_SESSION['user_role'] ?? null,
        'email' => $_SESSION['user_email']?? null,
    ];
}
function requireLogin() {
    if (!isLoggedIn()) jsonOut(['success'=>false,'message'=>'Please login first','redirect'=>'../login.html']);
}
function requireRole($role) {
    requireLogin();
    $u = currentUser();
    if ($u['role'] !== $role) jsonOut(['success'=>false,'message'=>'Access denied — '.$role.' only']);
}
function anyRole(...$roles) {
    requireLogin();
    $u = currentUser();
    if (!in_array($u['role'], $roles)) jsonOut(['success'=>false,'message'=>'Access denied']);
}

// ── Output helper ─────────────────────────────────────────────
function jsonOut($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ── Security helpers ──────────────────────────────────────────
function clean($v) { return htmlspecialchars(strip_tags(trim((string)$v))); }
function hashPw($p) { return password_hash($p, PASSWORD_BCRYPT); }
function checkPw($p,$h) { return password_verify($p,$h); }
function fakeTxn() { return 'TXN'.strtoupper(substr(bin2hex(random_bytes(10)),0,16)); }

// ── CORS for local dev ────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET,POST');
?>
