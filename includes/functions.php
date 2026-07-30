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
 * Distributor application status for the logged-in user.
 * Returns null if they are not logged in OR have never applied —
 * the header uses that to show nothing at all in those cases.
 *
 * @return array{status:string,label:string,css:string,business_name:string}|null
 */
function distributor_status(PDO $pdo): ?array {
    if (!logged_in()) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT approval_status, business_name FROM distributors WHERE user_id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;   // has not applied
    }

    // DB value -> friendly label + CSS state class.
    $map = [
        'pending'  => ['Pending',     'is-pending'],
        'approved' => ['Active',      'is-active'],
        'rejected' => ['Deactivated', 'is-deactivated'],
    ];
    [$label, $css] = $map[$row['approval_status']]
                     ?? [ucfirst($row['approval_status']), 'is-pending'];

    return [
        'status'        => $row['approval_status'],
        'label'         => $label,
        'css'           => $css,
        'business_name' => $row['business_name'],
    ];
}

/**
 * The distributor_id of the logged-in user IF their application is
 * approved; otherwise null. This is the single gate that decides who
 * may see prices at all: guests, members, and pending/rejected
 * distributors all get null.
 */
function approved_distributor_id(PDO $pdo): ?int {
    if (!logged_in()) {
        return null;
    }
    $stmt = $pdo->prepare(
        "SELECT distributor_id FROM distributors
          WHERE user_id = ? AND approval_status = 'approved'"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

/**
 * The price an APPROVED distributor should see for one product.
 * Applies their special deal if one exists (fixed price, or a
 * percentage off the regular price), otherwise returns the regular
 * price. `base` is always the regular price for reference.
 *
 * @return array{price:float, base:float, special:bool}
 */
function price_for_distributor(PDO $pdo, int $distributor_id, int $page_id, float $base): array {
    $stmt = $pdo->prepare(
        'SELECT adjustment_percent, fixed_price
           FROM distributor_pricing
          WHERE distributor_id = ? AND page_id = ?'
    );
    $stmt->execute([$distributor_id, $page_id]);
    $deal = $stmt->fetch();

    if (!$deal) {
        return ['price' => $base, 'base' => $base, 'special' => false];
    }

    if ($deal['fixed_price'] !== null) {
        $price = (float)$deal['fixed_price'];
    } elseif ($deal['adjustment_percent'] !== null) {
        $price = round($base * (1 - (float)$deal['adjustment_percent'] / 100), 2);
    } else {
        $price = $base;   // row with neither value set: treat as no deal
    }

    return ['price' => $price, 'base' => $base, 'special' => true];
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
