<?php
require_once __DIR__ . '/../config/db.php';

// On shared hosting, PHP's default session storage location (usually a
// system-wide /tmp) is often outside what this specific account is allowed
// to write to (open_basedir restrictions) — sessions then silently fail to
// persist: login appears to succeed for one request, then evaporates on
// the very next page load. Storing sessions inside the app's own folder
// sidesteps that entirely, since this path is always writable by the
// account that owns these files.
$sessionPath = __DIR__ . '/../sessions';
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

function require_login(): void {
    // Every authenticated page shows data scoped to one specific logged-in
    // person (their own quotes, their own client's data). Browsers — and
    // especially the back-forward cache when navigating with the back
    // button — can otherwise show a stale snapshot of this page after the
    // underlying data has changed (e.g. a quote status update), or after a
    // different account has logged in on the same browser. Disabling
    // caching entirely on every authenticated page closes that off.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    if (!is_logged_in()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

/**
 * Restrict a page to specific roles. Pass one or more of: 'admin','staff','client'.
 * Clients are bounced to the portal, staff/admin to the main dashboard.
 */
function require_role(string ...$roles): void {
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        if ($user['role'] === 'client') {
            header('Location: ' . APP_URL . '/portal/dashboard.php');
        } else {
            header('Location: ' . APP_URL . '/index.php');
        }
        exit;
    }
}

function login_user(array $userRow): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'        => $userRow['id'],
        'name'      => $userRow['name'],
        'email'     => $userRow['email'],
        'role'      => $userRow['role'],
        'client_id' => $userRow['client_id'],
    ];
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    // If the request body was larger than the server's post_max_size, PHP
    // silently empties $_POST before the script even runs — which would
    // otherwise surface here as a confusing "session expired" message.
    // Catch that specific case and say what actually happened.
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413);
        die('That upload was too large for the server to accept. Try a smaller file (max 8MB).');
    }
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die('Session expired or invalid request. Please go back and try again.');
    }
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
