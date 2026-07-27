<?php
// ============================================================
// Session handling, the admin guard, and small helpers.
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Is an admin currently logged in?
 */
function logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Put this at the top of every admin-only page.
 * If nobody is logged in it redirects to the login form and stops.
 */
function require_login(): void {
    if (!logged_in()) {
        header('Location: ' . url('login.php'));
        exit;
    }
}

/**
 * REQUIREMENT 7.1 — is the logged-in user an administrator?
 *
 * Being logged in is not enough for the highest-level tasks (like
 * managing other users). Those check this instead of logged_in().
 */
function is_admin(): bool {
    return logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * REQUIREMENT 7.1 — guard for admin-only pages.
 * A logged-in member who is not an admin is sent away with a message.
 */
function require_admin(): void {
    require_login();   // not logged in at all -> login form

    if (!is_admin()) {
        $_SESSION['message'] = 'You do not have permission to view that page.';
        header('Location: ' . url('index.php'));
        exit;
    }
}

/**
 * Build a link relative to the project root, so the app works
 * whether it sits at / or in a subfolder like /cms.
 */
function url(string $path = ''): string {
    $root = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    // If we are inside /admin, step back up one level.
    $root = preg_replace('#/admin$#', '', $root);
    return $root . '/' . ltrim($path, '/');
}

/**
 * Escape text before printing it. Prevents HTML injection.
 */
function e(?string $text): string {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Validate an id that came from the URL or a form.
 * Returns a positive integer, or 0 if it was missing or not a number.
 */
function clean_id($value): int {
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return ($id === false || $id < 1) ? 0 : $id;
}
