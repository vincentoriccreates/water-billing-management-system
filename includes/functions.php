<?php
// ─── Core Helpers ─────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'Admin';
}

function currentUser(): array {
    return [
        'id'     => $_SESSION['user_id']   ?? 0,
        'name'   => $_SESSION['user_name'] ?? '',
        'email'  => $_SESSION['user_email'] ?? '',
        'role'   => $_SESSION['user_role'] ?? '',
        'avatar' => $_SESSION['user_avatar'] ?? 'U',
    ];
}

// ── Formatting ────────────────────────────────────────────────────────────────
function fmt(float $n): string {
    return '₱' . number_format($n, 2);
}

function fmtNum(float $n): string {
    return number_format($n, 0);
}

// ── ID Generator ──────────────────────────────────────────────────────────────
function nextId(string $prefix, string $table, string $col = 'id'): string {
    $pdo  = getDB();
    $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
    $cnt  = (int)$stmt->fetchColumn() + 1;
    return $prefix . str_pad($cnt, 3, '0', STR_PAD_LEFT);
}

// ── Flash Messages ────────────────────────────────────────────────────────────
function setFlash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ── Sanitize ──────────────────────────────────────────────────────────────────
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function post(string $key, string $default = ''): string {
    return trim($_POST[$key] ?? $default);
}

function get(string $key, string $default = ''): string {
    return trim($_GET[$key] ?? $default);
}

// ── Billing Calc ──────────────────────────────────────────────────────────────
function calcBill(float $consumption): float {
    return BASE_CHARGE + ($consumption * RATE_PER_CUBIC);
}

// ── Current page ─────────────────────────────────────────────────────────────
function activePage(string $pg): string {
    $current = basename($_SERVER['PHP_SELF'], '.php');
    return $current === $pg ? 'active' : '';
}

// ── Dark mode ─────────────────────────────────────────────────────────────────
function isDark(): bool {
    return ($_SESSION['dark_mode'] ?? false) === true;
}
