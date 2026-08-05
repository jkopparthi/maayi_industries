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

/* ---------------- Product images ---------------- */
// One image per product. Files live in /uploads with generated names;
// the images table maps them to pages.

define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');
define('MAX_IMAGE_SIDE', 800);            // longest side after resize (px)
define('MAX_IMAGE_BYTES', 5 * 1024 * 1024);

/**
 * The image row for a product, or null if it has none.
 */
function page_image(PDO $pdo, int $page_id): ?array {
    $stmt = $pdo->prepare(
        'SELECT * FROM images WHERE page_id = ? ORDER BY image_id DESC LIMIT 1'
    );
    $stmt->execute([$page_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Image rows for MANY products in one query (for list/gallery pages,
 * so we do not run one query per tile).
 *
 * @return array<int,string>  page_id => filename
 */
function page_images(PDO $pdo, array $page_ids): array {
    $ids = array_values(array_filter(array_map('intval', $page_ids), fn($i) => $i > 0));
    if (!$ids) {
        return [];
    }
    $in   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT page_id, filename FROM images WHERE page_id IN ($in)
          ORDER BY image_id"
    );
    $stmt->execute($ids);
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $map[(int)$r['page_id']] = $r['filename'];   // later rows win = newest
    }
    return $map;
}

/**
 * The "image-ness" test. getimagesize() parses the actual file header,
 * so a script renamed to .jpg fails here no matter what the extension
 * or browser-sent MIME type claims.
 * Returns [ok(bool), error(string), type(int)].
 */
function image_upload_check(array $file): array {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [false, '', 0];                 // nothing chosen — that is fine
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'The file failed to upload. Please try again.', 0];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return [false, 'Invalid upload.', 0];
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        return [false, 'Image too large (5 MB maximum).', 0];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return [false, 'That file is not a valid image.', 0];
    }
    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
    if (!in_array($info[2], $allowed, true)) {
        return [false, 'Only JPEG, PNG, GIF or WEBP images are allowed.', 0];
    }
    return [true, '', $info[2]];
}

/**
 * Resize with GD so the longest side is at most MAX_IMAGE_SIDE and
 * write to $dest. Re-encodes, so the file size genuinely changes.
 */
function image_resize_save(string $src, string $dest, int $type): bool {
    [$w, $h] = getimagesize($src);
    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src);  break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($src);  break;
        case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($src); break;
        default: return false;
    }
    if (!$img) {
        return false;
    }

    $longest = max($w, $h);
    if ($longest <= MAX_IMAGE_SIDE) {
        $nw = $w; $nh = $h;
    } else {
        $scale = MAX_IMAGE_SIDE / $longest;
        $nw = (int)round($w * $scale);
        $nh = (int)round($h * $scale);
    }

    $canvas = imagecreatetruecolor($nw, $nh);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $nw, $nh,
            imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    }
    imagecopyresampled($canvas, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $ok = imagejpeg($canvas, $dest, 85); break;
        case IMAGETYPE_PNG:  $ok = imagepng($canvas, $dest, 6);   break;
        case IMAGETYPE_GIF:  $ok = imagegif($canvas, $dest);      break;
        case IMAGETYPE_WEBP: $ok = imagewebp($canvas, $dest, 85); break;
    }
    imagedestroy($img);
    imagedestroy($canvas);
    return $ok;
}

/**
 * Accept an upload for a product: validate, resize, store, record.
 * Replaces any previous image (one per product). Returns '' on success
 * or when no file was chosen; otherwise an error message. A rejected
 * file never reaches the disk or the database.
 */
function page_image_save(PDO $pdo, int $page_id, array $file): string {
    [$ok, $error, $type] = image_upload_check($file);
    if (!$ok) {
        return $error;                        // '' when no file chosen
    }

    $ext      = image_type_to_extension($type, false);
    $filename = 'p' . $page_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    if (!image_resize_save($file['tmp_name'], UPLOAD_DIR . $filename, $type)) {
        return 'The image could not be processed.';
    }

    page_image_delete($pdo, $page_id);        // replace, never accumulate

    $stmt = $pdo->prepare(
        'INSERT INTO images (page_id, filename, original_name) VALUES (?, ?, ?)'
    );
    $stmt->execute([$page_id, $filename, mb_substr($file['name'] ?? '', 0, 255)]);
    return '';
}

/**
 * Remove a product image from the database AND the file system.
 */
function page_image_delete(PDO $pdo, int $page_id): void {
    $stmt = $pdo->prepare('SELECT filename FROM images WHERE page_id = ?');
    $stmt->execute([$page_id]);
    foreach ($stmt->fetchAll() as $row) {
        $path = UPLOAD_DIR . $row['filename'];
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $del = $pdo->prepare('DELETE FROM images WHERE page_id = ?');
    $del->execute([$page_id]);
}
