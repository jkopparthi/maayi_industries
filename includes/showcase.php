<?php
// ============================================================
// includes/showcase.php
//
// Everything the 3D showcase needs on the PHP side. Kept in its own
// file so the existing functions.php is untouched.
//
// The showcase reads from the SAME `pages` table as the catalog. It
// adds columns rather than a parallel product system, so a price
// change or a rename in Manage Pages shows up in the 3D page too.
// ============================================================

require_once __DIR__ . '/functions.php';

define('SHOWCASE_DIR',              dirname(__DIR__) . '/uploads/showcase/');
define('SHOWCASE_MAX_MODEL_BYTES',  24 * 1024 * 1024);   // 24 MB
define('SHOWCASE_MAX_TEXTURE_SIDE', 1024);               // label textures

/**
 * The columns the showcase adds to `pages`, with their definitions.
 * Used both to detect whether the migration has run and to run it.
 */
function showcase_columns(): array {
    return [
        'short_description' => "VARCHAR(300) NOT NULL DEFAULT ''",
        'showcase_enabled'  => 'TINYINT(1) NOT NULL DEFAULT 0',
        'showcase_order'    => 'INT NOT NULL DEFAULT 0',
        'model_file'        => 'VARCHAR(255) NULL',
        'label_file'        => 'VARCHAR(255) NULL',
        'liquid_color'      => 'VARCHAR(7) NULL',
        'glass_tint'        => 'VARCHAR(7) NULL',
        'cap_color'         => 'VARCHAR(7) NULL',
        'accent_color'          => 'VARCHAR(7) NULL',

        // Per-product switches: when false, the original GLB material/texture
        // is left untouched. Existing products default to 0 (off).
        'override_liquid_color' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'override_glass_tint'   => 'TINYINT(1) NOT NULL DEFAULT 0',
        'override_cap_color'    => 'TINYINT(1) NOT NULL DEFAULT 0',
        'override_label_texture'=> 'TINYINT(1) NOT NULL DEFAULT 0',
    ];
}

/**
 * Has the showcase migration been applied? Cached per request.
 */
function showcase_installed(PDO $pdo): bool {
    static $ready = null;
    if ($ready === null) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM pages LIKE 'showcase_enabled'");
            $ready = (bool)$stmt->fetch();
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}

/**
 * Add any missing showcase columns. Safe to run more than once — each
 * column is checked first, so a half-finished migration completes
 * rather than erroring.
 *
 * @return array{added:string[], errors:string[]}
 */
function showcase_install(PDO $pdo): array {
    $added = [];
    $errors = [];

    foreach (showcase_columns() as $name => $definition) {
        try {
            $exists = $pdo->query("SHOW COLUMNS FROM pages LIKE " . $pdo->quote($name))->fetch();
            if ($exists) {
                continue;
            }
            $pdo->exec("ALTER TABLE pages ADD COLUMN `$name` $definition");
            $added[] = $name;
        } catch (Throwable $e) {
            $errors[] = $name . ': ' . $e->getMessage();
        }
    }

    // Index for "enabled products, in order". Ignore if already there.
    try {
        $has = $pdo->query("SHOW INDEX FROM pages WHERE Key_name = 'idx_pages_showcase'")->fetch();
        if (!$has) {
            $pdo->exec('CREATE INDEX idx_pages_showcase ON pages (showcase_enabled, showcase_order)');
        }
    } catch (Throwable $e) {
        // An index we could not create is a performance detail, not a failure.
    }

    if (!is_dir(SHOWCASE_DIR)) {
        @mkdir(SHOWCASE_DIR, 0775, true);
    }

    return ['added' => $added, 'errors' => $errors];
}

/**
 * Sensible starting materials for a product that has none set yet,
 * picked from its category so a freshly featured product already looks
 * like the drink it is instead of grey glass.
 *
 * @return array{liquid:string, glass:string, cap:string, accent:string}
 */
function showcase_default_colors(?string $category): array {
    $c = strtolower($category ?? '');

    $presets = [
        'water'     => ['#dceef6', '#bcd7e2', '#3f6b82', '#3f6b82'],
        'kombucha'  => ['#c9762c', '#b08048', '#5a4f45', '#e8a13a'],
        'wine'      => ['#8c2f4d', '#4a2130', '#2a1520', '#6d2a4a'],
        'whisky'    => ['#7a3f12', '#3d2a18', '#1c1a17', '#b5561f'],
        'vodka'     => ['#eef4f7', '#c9dae2', '#6f8fa3', '#3f6b82'],
        'cane'      => ['#f0f5f0', '#c6d8cf', '#0f5c4d', '#0f5c4d'],
        'flavoured' => ['#e0b23c', '#c8a86a', '#5a4f45', '#e8a13a'],
    ];

    foreach ($presets as $needle => $set) {
        if (str_contains($c, $needle)) {
            return ['liquid' => $set[0], 'glass' => $set[1], 'cap' => $set[2], 'accent' => $set[3]];
        }
    }
    return ['liquid' => '#b5561f', 'glass' => '#4a3524', 'cap' => '#1c1a17', 'accent' => '#b5561f'];
}

/**
 * Validate a colour that came from a form. Returns a #rrggbb string,
 * or null if it was blank or malformed (so the default is used).
 */
function showcase_clean_color($value): ?string {
    $v = trim((string)$value);
    if ($v === '') {
        return null;
    }
    return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtolower($v) : null;
}

/**
 * Every product flagged for the showcase, in the admin's order.
 * Falls back to title order for products that share an order number.
 */
function showcase_products(PDO $pdo): array {
    if (!showcase_installed($pdo)) {
        return [];
    }
    return $pdo->query(
        'SELECT p.*, c.name AS category_name
           FROM pages p
           LEFT JOIN categories c ON p.category_id = c.category_id
          WHERE p.showcase_enabled = 1
          ORDER BY p.showcase_order, p.title'
    )->fetchAll();
}

/**
 * Trim the full description down to a teaser when the admin has not
 * written a short one. Strips the WYSIWYG tags first.
 */
function showcase_teaser(array $row, int $limit = 170): string {
    $short = trim($row['short_description'] ?? '');
    if ($short !== '') {
        return $short;
    }
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($row['body'] ?? '')));
    if (mb_strlen($plain) <= $limit) {
        return $plain;
    }
    $cut = mb_substr($plain, 0, $limit);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space ? mb_substr($cut, 0, $space) : $cut, ' ,.;:') . '…';
}

/**
 * Public URL for an uploaded showcase asset.
 */
function showcase_asset_url(?string $filename): ?string {
    if (!$filename) {
        return null;
    }
    return url('uploads/showcase/' . rawurlencode($filename));
}

/**
 * Build the object the JavaScript consumes.
 *
 * This is the ONLY place that decides what a given visitor may see.
 * Prices and the buy button are attached for approved distributors and
 * are simply absent from the payload for everyone else — a guest cannot
 * read a price out of the page source, because it was never sent.
 */
function showcase_payload(PDO $pdo): array {
    $dist_id  = approved_distributor_id($pdo);
    $rows     = showcase_products($pdo);
    $images   = page_images($pdo, array_column($rows, 'page_id'));
    $products = [];

    foreach ($rows as $row) {
        $pid      = (int)$row['page_id'];
        $defaults = showcase_default_colors($row['category_name'] ?? null);

        $item = [
            'id'          => $pid,
            'name'        => $row['title'],
            'category'    => $row['category_name'] ?: 'Uncategorised',
            'teaser'      => showcase_teaser($row),
            'body'        => display_body($row['body'] ?? ''),   // sanitised on save
            'permalink'   => permalink($pid, $row['slug']),
            'photo'       => isset($images[$pid])
                                ? url('uploads/' . rawurlencode($images[$pid]))
                                : null,
            'model'       => showcase_asset_url($row['model_file'] ?? null),
            'label'       => showcase_asset_url($row['label_file'] ?? null),

            // Colors are still sent so the admin-selected values are available
            // to the renderer, but each one is applied only when its matching
            // override flag is enabled.
            'liquidColor' => showcase_clean_color($row['liquid_color'] ?? '') ?? $defaults['liquid'],
            'glassTint'   => showcase_clean_color($row['glass_tint']   ?? '') ?? $defaults['glass'],
            'capColor'    => showcase_clean_color($row['cap_color']    ?? '') ?? $defaults['cap'],
            'accent'      => showcase_clean_color($row['accent_color'] ?? '') ?? $defaults['accent'],

            'overrideLiquid' => (bool)($row['override_liquid_color'] ?? 0),
            'overrideGlass'  => (bool)($row['override_glass_tint'] ?? 0),
            'overrideCap'    => (bool)($row['override_cap_color'] ?? 0),
            'overrideLabel'  => (bool)($row['override_label_texture'] ?? 0),
        ];

        // ---- Pricing: distributors only (Business Rule 1) ----
        if ($dist_id !== null) {
            $pricing          = price_for_distributor($pdo, $dist_id, $pid, (float)$row['price']);
            $item['price']    = 'K' . number_format($pricing['price'], 2);
            $item['wasPrice'] = $pricing['special'] ? 'K' . number_format($pricing['base'], 2) : null;
            $item['canBuy']   = true;
        } else {
            $item['canBuy'] = false;
        }

        $products[] = $item;
    }

    // Categories present in the showcase, in showcase order, deduplicated.
    $categories = [];
    foreach ($products as $i => $p) {
        if (!isset($categories[$p['category']])) {
            $categories[$p['category']] = ['name' => $p['category'], 'firstIndex' => $i];
        }
    }

    return [
        'products'    => $products,
        'categories'  => array_values($categories),
        'isGuest'     => !logged_in(),
        'isDistributor' => $dist_id !== null,
        'urls'        => [
            'catalog'  => url('index.php'),
            'cart'     => url('cart.php'),
            'register' => url('register.php'),
            'login'    => url('login.php'),
            'apply'    => url('apply.php'),
        ],
    ];
}

/* ------------------------------------------------------------
   Asset uploads
   ------------------------------------------------------------ */

/**
 * Accept a .glb or .gltf upload for a product.
 *
 * The extension is not trusted. A .glb is verified by its magic number
 * ("glTF"), a .gltf by parsing it as JSON — so a renamed script is
 * rejected before it reaches the disk.
 *
 * @return string '' on success or when no file was chosen, else an error.
 */
function showcase_model_save(PDO $pdo, int $page_id, array $file): string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return 'The model is larger than PHP accepts. Raise upload_max_filesize and post_max_size in php.ini.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return 'The model failed to upload. Please try again.';
    }
    if ($file['size'] > SHOWCASE_MAX_MODEL_BYTES) {
        return 'Model too large (24 MB maximum). Compress it with Draco or gltf-pipeline first.';
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if (!in_array($ext, ['glb', 'gltf'], true)) {
        return 'Only .glb or .gltf files are accepted.';
    }

    $handle = fopen($file['tmp_name'], 'rb');
    if (!$handle) {
        return 'The model could not be read.';
    }
    $head = fread($handle, 4);
    fclose($handle);

    if ($ext === 'glb') {
        if ($head !== 'glTF') {
            return 'That file is not a valid .glb (the glTF header is missing).';
        }
    } else {
        $json = json_decode(file_get_contents($file['tmp_name']), true);
        if (!is_array($json) || !isset($json['asset'])) {
            return 'That file is not valid glTF JSON.';
        }
        // A .gltf usually references external .bin and texture files that
        // are not part of this upload, so warn rather than silently break.
        if (!empty($json['buffers'][0]['uri']) && !str_starts_with($json['buffers'][0]['uri'], 'data:')) {
            return 'This .gltf points at separate .bin/texture files. Re-export from Blender as a single .glb instead.';
        }
    }

    if (!is_dir(SHOWCASE_DIR) && !mkdir(SHOWCASE_DIR, 0775, true)) {
        return 'The uploads/showcase folder could not be created.';
    }

    $filename = 'm' . $page_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], SHOWCASE_DIR . $filename)) {
        return 'The model could not be saved.';
    }

    showcase_asset_clear($pdo, $page_id, 'model_file');   // replace, never accumulate

    $pdo->prepare('UPDATE pages SET model_file = ? WHERE page_id = ?')
        ->execute([$filename, $page_id]);

    return '';
}

/**
 * Accept label artwork for a product: validate as a real image, resize
 * so the longest side is at most 1024px, and keep transparency (PNG and
 * WEBP labels usually have a cut-out shape).
 */
function showcase_texture_save(PDO $pdo, int $page_id, array $file): string {
    [$ok, $error, $type] = image_upload_check($file);
    if (!$ok) {
        return $error;
    }
    if (!function_exists('imagecreatefromjpeg')) {
        return 'Image processing is unavailable: enable the "gd" extension in php.ini and restart Apache.';
    }

    if (!is_dir(SHOWCASE_DIR) && !mkdir(SHOWCASE_DIR, 0775, true)) {
        return 'The uploads/showcase folder could not be created.';
    }

    $ext      = image_type_to_extension($type, false);
    $filename = 'l' . $page_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest     = SHOWCASE_DIR . $filename;

    if (!showcase_texture_resize($file['tmp_name'], $dest, $type)) {
        if (is_file($dest)) {
            @unlink($dest);
        }
        return 'The label image could not be processed.';
    }

    showcase_asset_clear($pdo, $page_id, 'label_file');

    $pdo->prepare('UPDATE pages SET label_file = ? WHERE page_id = ?')
        ->execute([$filename, $page_id]);

    return '';
}

/**
 * Like image_resize_save() but sized for textures rather than thumbnails,
 * and it never flattens alpha.
 */
function showcase_texture_resize(string $src, string $dest, int $type): bool {
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
    if ($longest <= SHOWCASE_MAX_TEXTURE_SIDE) {
        $nw = $w; $nh = $h;
    } else {
        $scale = SHOWCASE_MAX_TEXTURE_SIDE / $longest;
        $nw = (int)round($w * $scale);
        $nh = (int)round($h * $scale);
    }

    $canvas = imagecreatetruecolor($nw, $nh);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefilledrectangle($canvas, 0, 0, $nw, $nh,
        imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    imagecopyresampled($canvas, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

    $ok = false;
    switch ($type) {
        case IMAGETYPE_JPEG: $ok = imagejpeg($canvas, $dest, 90); break;
        case IMAGETYPE_PNG:  $ok = imagepng($canvas, $dest, 6);   break;
        case IMAGETYPE_GIF:  $ok = imagegif($canvas, $dest);      break;
        case IMAGETYPE_WEBP: $ok = imagewebp($canvas, $dest, 90); break;
    }
    imagedestroy($img);
    imagedestroy($canvas);
    return $ok;
}

/**
 * Delete the file behind model_file or label_file and blank the column.
 */
function showcase_asset_clear(PDO $pdo, int $page_id, string $column): void {
    if (!in_array($column, ['model_file', 'label_file'], true)) {
        return;   // never let a caller name an arbitrary column
    }
    $stmt = $pdo->prepare("SELECT `$column` FROM pages WHERE page_id = ?");
    $stmt->execute([$page_id]);
    $current = $stmt->fetchColumn();

    if ($current) {
        $path = SHOWCASE_DIR . basename($current);
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $pdo->prepare("UPDATE pages SET `$column` = NULL WHERE page_id = ?")->execute([$page_id]);
}