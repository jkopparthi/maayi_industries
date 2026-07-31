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
 * All staff role names (is_staff = 1), e.g. ['admin','accountant'].
 * Cached per request so repeated calls don't re-query.
 */
function staff_role_names(PDO $pdo): array {
    static $cache = null;
    if ($cache === null) {
        try {
            $cache = $pdo->query(
                'SELECT name FROM roles WHERE is_staff = 1'
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            // roles table not created yet -> fall back to built-in staff.
            $cache = ['admin', 'accountant'];
        }
    }
    return $cache;
}

/**
 * Is the logged-in user company staff (any role with is_staff = 1)?
 * Admin is always staff even if the roles table is missing.
 */
function is_staff(PDO $pdo): bool {
    if (!logged_in()) {
        return false;
    }
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') {
        return true;
    }
    return in_array($role, staff_role_names($pdo), true);
}

/**
 * Guard for staff pages (distributor approvals, pricing, etc.).
 * Admin + accountant + any other staff role may pass.
 */
function require_staff(PDO $pdo): void {
    require_login();

    if (!is_staff($pdo)) {
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

/* ---------------- Cart (session-based) ---------------- */
// Stored as $_SESSION['cart'] = [page_id => quantity]. Cleared on logout.

function cart_items(): array {
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int {
    return array_sum(cart_items());
}

function cart_add(int $page_id, int $qty = 1): void {
    if ($qty < 1) $qty = 1;
    $_SESSION['cart'][$page_id] = (cart_items()[$page_id] ?? 0) + $qty;
}

function cart_set(int $page_id, int $qty): void {
    if ($qty < 1) {
        cart_remove($page_id);
    } else {
        $_SESSION['cart'][$page_id] = $qty;
    }
}

function cart_remove(int $page_id): void {
    unset($_SESSION['cart'][$page_id]);
}

function cart_clear(): void {
    unset($_SESSION['cart']);
}

/**
 * Expand the cart into full line rows priced for this distributor.
 * @return array{lines:array<int,array>, total:float}
 */
function cart_detailed(PDO $pdo, int $distributor_id): array {
    $items = cart_items();
    $lines = [];
    $total = 0.0;
    if (!$items) {
        return ['lines' => [], 'total' => 0.0];
    }

    $ids = array_map('intval', array_keys($items));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT page_id, title, price FROM pages WHERE page_id IN ($in)");
    $stmt->execute($ids);
    $products = [];
    foreach ($stmt->fetchAll() as $p) {
        $products[(int)$p['page_id']] = $p;
    }

    foreach ($items as $pid => $qty) {
        $pid = (int)$pid;
        if (!isset($products[$pid])) {
            continue;   // product removed since it was added
        }
        $base    = (float)$products[$pid]['price'];
        $pricing = price_for_distributor($pdo, $distributor_id, $pid, $base);
        $lineTotal = $pricing['price'] * $qty;
        $total    += $lineTotal;
        $lines[] = [
            'page_id'    => $pid,
            'title'      => $products[$pid]['title'],
            'quantity'   => $qty,
            'unit_price' => $pricing['price'],
            'line_total' => $lineTotal,
        ];
    }
    return ['lines' => $lines, 'total' => round($total, 2)];
}

/* ---------------- Distributor balance ---------------- */
/**
 * Amount owed = sum of CONFIRMED orders minus sum of payments recorded.
 * Derived on read; never stored.
 */
function distributor_balance(PDO $pdo, int $distributor_id): float {
    $c = $pdo->prepare(
        "SELECT COALESCE(SUM(total_amount),0)
           FROM orders
          WHERE distributor_id = ? AND order_status = 'confirmed'"
    );
    $c->execute([$distributor_id]);
    $confirmed = (float)$c->fetchColumn();

    $p = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0) FROM payments WHERE distributor_id = ?'
    );
    $p->execute([$distributor_id]);
    $paid = (float)$p->fetchColumn();

    return round($confirmed - $paid, 2);
}

/**
 * Full balance breakdown for a distributor: the confirmed-orders total,
 * the payments total, and the resulting amount owed. Handy where the UI
 * wants to show the components, not just the net figure.
 *
 * @return array{confirmed:float, paid:float, owed:float}
 */
function distributor_balance_breakdown(PDO $pdo, int $distributor_id): array {
    $c = $pdo->prepare(
        "SELECT COALESCE(SUM(total_amount),0)
           FROM orders
          WHERE distributor_id = ? AND order_status = 'confirmed'"
    );
    $c->execute([$distributor_id]);
    $confirmed = (float)$c->fetchColumn();

    $p = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0) FROM payments WHERE distributor_id = ?'
    );
    $p->execute([$distributor_id]);
    $paid = (float)$p->fetchColumn();

    return [
        'confirmed' => round($confirmed, 2),
        'paid'      => round($paid, 2),
        'owed'      => round($confirmed - $paid, 2),
    ];
}

/**
 * Context-aware search target for the global header box.
 * Looks at the current script and returns where the search should go
 * and a matching placeholder, so the same box searches distributors on
 * the distributors page, orders on the orders page, etc., and products
 * everywhere else.
 *
 * @return array{action:string, placeholder:string}
 */
function search_context(): array {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $map = [
        'admin/distributors.php' => ['admin/distributors.php', 'Search distributors…'],
        'admin/orders.php'       => ['admin/orders.php',        'Search orders…'],
        'admin/users.php'        => ['admin/users.php',         'Search staff…'],
        'admin/payments.php'     => ['admin/payments.php',      'Search distributors…'],
    ];
    foreach ($map as $needle => $cfg) {
        if (str_ends_with($script, $needle)) {
            return ['action' => url($cfg[0]), 'placeholder' => $cfg[1]];
        }
    }
    return ['action' => url('search.php'), 'placeholder' => 'Search products…'];
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
