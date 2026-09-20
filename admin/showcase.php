<?php
// ============================================================
// admin/showcase.php — decide what appears in the 3D showcase.
//
// One screen, one save. Ticking a product puts it in the showcase;
// the order number decides where in the rotation it lands. Materials
// and 3D assets are optional — a product with none of them still
// renders, using the built-in bottle and a label generated from its
// own name and category.
//
// Admin only (requirement 7.1).
// ============================================================
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/showcase.php';
require_admin();

$notices = [];
$errors  = [];

/* ---------------- Install the schema ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'install') {
    $result = showcase_install($pdo);

    if ($result['errors']) {
        $errors = array_merge($errors, $result['errors']);
    }
    if ($result['added']) {
        $_SESSION['message'] = 'Showcase fields added: ' . implode(', ', $result['added']) . '.';
    } else {
        $_SESSION['message'] = 'The showcase fields were already in place.';
    }
    header('Location: ' . url('admin/showcase.php'));
    exit;
}

$installed = showcase_installed($pdo);

/**
 * PHP flattens `name="model[12]"` into parallel arrays. Rebuild the
 * ordinary single-file shape the upload helpers expect.
 */
function file_row(string $field, int $id): array {
    if (!isset($_FILES[$field]) || !isset($_FILES[$field]['name'][$id])) {
        return ['error' => UPLOAD_ERR_NO_FILE];
    }
    return [
        'name'     => $_FILES[$field]['name'][$id],
        'type'     => $_FILES[$field]['type'][$id],
        'tmp_name' => $_FILES[$field]['tmp_name'][$id],
        'error'    => $_FILES[$field]['error'][$id],
        'size'     => $_FILES[$field]['size'][$id],
    ];
}

/* ---------------- Save ---------------- */
if ($installed && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'save') {

    $enabled = array_map('intval', array_keys($_POST['enabled'] ?? []));
    $ids     = array_map('intval', array_keys($_POST['order'] ?? []));

    $update = $pdo->prepare(
        'UPDATE pages
            SET showcase_enabled  = ?,
                showcase_order    = ?,
                short_description = ?,
                liquid_color           = ?,
                glass_tint             = ?,
                cap_color              = ?,
                accent_color           = ?,
                override_liquid_color = ?,
                override_glass_tint   = ?,
                override_cap_color    = ?,
                override_label_texture = ?
          WHERE page_id = ?'
    );

    foreach ($ids as $pid) {
        if ($pid < 1) {
            continue;
        }

        $update->execute([
            in_array($pid, $enabled, true) ? 1 : 0,
            (int)($_POST['order'][$pid] ?? 0),
            mb_substr(trim($_POST['short'][$pid] ?? ''), 0, 300),
            showcase_clean_color($_POST['liquid'][$pid] ?? ''),
            showcase_clean_color($_POST['glass'][$pid]  ?? ''),
            showcase_clean_color($_POST['cap'][$pid]    ?? ''),
            showcase_clean_color($_POST['accent'][$pid] ?? ''),
            !empty($_POST['override_liquid'][$pid]) ? 1 : 0,
            !empty($_POST['override_glass'][$pid])  ? 1 : 0,
            !empty($_POST['override_cap'][$pid])    ? 1 : 0,
            !empty($_POST['override_label'][$pid])  ? 1 : 0,
            $pid,
        ]);

        // Removals run before uploads, so replacing in one save works.
        if (!empty($_POST['remove_model'][$pid])) {
            showcase_asset_clear($pdo, $pid, 'model_file');
        }
        if (!empty($_POST['remove_label'][$pid])) {
            showcase_asset_clear($pdo, $pid, 'label_file');
        }

        $modelError = showcase_model_save($pdo, $pid, file_row('model', $pid));
        if ($modelError !== '') {
            $errors[] = 'Model for product #' . $pid . ': ' . $modelError;
        }

        $labelError = showcase_texture_save($pdo, $pid, file_row('label', $pid));
        if ($labelError !== '') {
            $errors[] = 'Label for product #' . $pid . ': ' . $labelError;
        }
    }

    if (!$errors) {
        $_SESSION['message'] = 'Showcase saved.';
        header('Location: ' . url('admin/showcase.php'));
        exit;
    }
}

/* ---------------- Load ---------------- */
$rows = [];
if ($installed) {
    $rows = $pdo->query(
        'SELECT p.*, c.name AS category_name
           FROM pages p
           LEFT JOIN categories c ON p.category_id = c.category_id
          ORDER BY p.showcase_enabled DESC, p.showcase_order, p.title'
    )->fetchAll();
}
$featured = array_values(array_filter($rows, fn($r) => (int)$r['showcase_enabled'] === 1));

$title = 'Manage Showcase';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="head-row">
    <h1>3D Showcase</h1>
    <span>
        <a class="button" href="<?= url('showcase.php') ?>" target="_blank" rel="noopener">View the showcase</a>
    </span>
</div>

<?php if ($errors): ?>
    <div class="error">
        <ul><?php foreach ($errors as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if (!$installed): ?>

    <div class="box">
        <h2 style="margin-top:0">Add the showcase fields</h2>
        <p>The showcase keeps its settings on the product records themselves, so
           a price or a name only ever has to be changed in one place. Those nine
           columns are not in the database yet.</p>
        <p class="hint">
            This adds <code>short_description</code>, <code>showcase_enabled</code>,
            <code>showcase_order</code>, <code>model_file</code>, <code>label_file</code>,
            <code>liquid_color</code>, <code>glass_tint</code>, <code>cap_color</code> and
            <code>accent_color</code> to <code>pages</code>. Existing products, prices,
            orders and comments are untouched, and every new column is optional.
        </p>
        <form method="post">
            <input type="hidden" name="do" value="install">
            <button type="submit">Add the showcase fields</button>
        </form>
    </div>

<?php else: ?>

    <p class="hint" style="max-width:70ch">
        Tick a product to put it in the showcase. The order number sets where it
        appears in the rotation — lower numbers come first, so leaving gaps
        (10, 20, 30) makes it easy to slot something in later. Everything below
        the tick is optional: a product with no model and no label still renders,
        using the built-in bottle and a label generated from its own name.
    </p>

    <?php if ($featured): ?>
        <p class="meta">
            In the showcase now:
            <?php
            $names = array_map(fn($r) => $r['title'], $featured);
            echo e(implode(' → ', $names));
            ?>
        </p>
    <?php else: ?>
        <p class="meta">Nothing is in the showcase yet, so the page will send visitors straight to the catalog.</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="sc-admin">
        <input type="hidden" name="do" value="save">

        <?php foreach ($rows as $r):
            $pid      = (int)$r['page_id'];
            $on       = (int)$r['showcase_enabled'] === 1;
            $defaults = showcase_default_colors($r['category_name'] ?? null);
        ?>
            <details class="box sc-row" <?= $on ? 'open' : '' ?>>
                <summary>
                    <label class="sc-row-head" onclick="event.stopPropagation()">
                        <input type="checkbox" name="enabled[<?= $pid ?>]" value="1" <?= $on ? 'checked' : '' ?>>
                        <span class="sc-row-title"><?= e($r['title']) ?></span>
                    </label>
                    <span class="meta"><?= e($r['category_name'] ?: 'Uncategorised') ?></span>
                    <span class="sc-row-order" onclick="event.stopPropagation()">
                        <label class="meta">Order
                            <input type="number" name="order[<?= $pid ?>]"
                                   value="<?= (int)$r['showcase_order'] ?>" step="10" min="0">
                        </label>
                    </span>
                </summary>

                <div class="sc-row-body">
                    <label>Showcase teaser
                        <input type="text" name="short[<?= $pid ?>]" maxlength="300"
                               value="<?= e($r['short_description'] ?? '') ?>"
                               placeholder="One line beside the bottle. Blank uses the start of the full description.">
                    </label>

                    <div class="sc-colors">
                        <div class="sc-color">
                            <label>
                                <input type="checkbox"
                                       name="override_liquid[<?= $pid ?>]"
                                       value="1"
                                       <?= !empty($r['override_liquid_color']) ? 'checked' : '' ?>>
                                Override liquid color
                            </label>
                            <input type="color" name="liquid[<?= $pid ?>]"
                                   value="<?= e(showcase_clean_color($r['liquid_color'] ?? '') ?? $defaults['liquid']) ?>">
                        </div>

                        <div class="sc-color">
                            <label>
                                <input type="checkbox"
                                       name="override_glass[<?= $pid ?>]"
                                       value="1"
                                       <?= !empty($r['override_glass_tint']) ? 'checked' : '' ?>>
                                Override glass color
                            </label>
                            <input type="color" name="glass[<?= $pid ?>]"
                                   value="<?= e(showcase_clean_color($r['glass_tint'] ?? '') ?? $defaults['glass']) ?>">
                        </div>

                        <div class="sc-color">
                            <label>
                                <input type="checkbox"
                                       name="override_cap[<?= $pid ?>]"
                                       value="1"
                                       <?= !empty($r['override_cap_color']) ? 'checked' : '' ?>>
                                Override cap color
                            </label>
                            <input type="color" name="cap[<?= $pid ?>]"
                                   value="<?= e(showcase_clean_color($r['cap_color'] ?? '') ?? $defaults['cap']) ?>">
                        </div>

                        <div class="sc-color">
                            <label>
                                <input type="checkbox"
                                       name="override_label[<?= $pid ?>]"
                                       value="1"
                                       <?= !empty($r['override_label_texture']) ? 'checked' : '' ?>>
                                Override label texture
                            </label>
                            <span class="meta">Uses the uploaded label below.</span>
                        </div>

                        <div class="sc-color">
                            <label>Page accent</label>
                            <input type="color" name="accent[<?= $pid ?>]"
                                   value="<?= e(showcase_clean_color($r['accent_color'] ?? '') ?? $defaults['accent']) ?>">
                        </div>
                    </div>
                    <p class="hint">
                        The page accent colours the category rail, the dial and the buttons
                        while this product is on screen.
                    </p>

                    <div class="sc-assets">
                        <div>
                            <label>3D model (.glb)
                                <input type="file" name="model[<?= $pid ?>]" accept=".glb,.gltf,model/gltf-binary">
                            </label>
                            <?php if (!empty($r['model_file'])): ?>
                                <p class="meta">
                                    Using <code><?= e($r['model_file']) ?></code>
                                    <label class="checkline">
                                        <input type="checkbox" name="remove_model[<?= $pid ?>]" value="1">
                                        Remove and use the built-in bottle
                                    </label>
                                </p>
                            <?php else: ?>
                                <p class="meta">No model — using the built-in bottle.</p>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label>Label artwork
                                <input type="file" name="label[<?= $pid ?>]" accept="image/*">
                            </label>
                            <?php if (!empty($r['label_file'])): ?>
                                <p class="meta">
                                    Using <code><?= e($r['label_file']) ?></code>
                                    <label class="checkline">
                                        <input type="checkbox" name="remove_label[<?= $pid ?>]" value="1">
                                        Remove and generate one
                                    </label>
                                </p>
                            <?php else: ?>
                                <p class="meta">No artwork — a label is generated from the product name.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="hint">
                        Export from Blender as a single <strong>.glb</strong> with textures embedded,
                        up to 24&nbsp;MB. Name the objects <code>glass</code>, <code>liquid</code>,
                        <code>label</code> and <code>cap</code>. The override checkboxes decide
                        whether the showcase changes those materials. Leave an override unchecked
                        to keep the material/texture already inside the GLB.
                    </p>

                    <p class="meta">
                        <a href="<?= url('admin/page-form.php?id=' . $pid) ?>">Edit name, price and full description →</a>
                    </p>
                </div>
            </details>
        <?php endforeach; ?>

        <div class="sc-save">
            <button type="submit">Save showcase</button>
            <span class="hint">
                Uploading several models at once can exceed PHP's
                <code>post_max_size</code>. If a save appears to do nothing, upload
                them a few at a time.
            </span>
        </div>
    </form>

    <style>
        /* Small screen-specific layout; the sitewide sheet keeps the styling. */
        .sc-admin .sc-row { padding: 0; }
        .sc-admin summary {
            display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
            padding: .85rem 1.1rem; cursor: pointer; list-style: none;
        }
        .sc-admin summary::-webkit-details-marker { display: none; }
        .sc-admin summary::before {
            content: "▸"; color: var(--ink-soft); font-size: .9rem; transition: transform .2s;
        }
        .sc-admin details[open] summary::before { transform: rotate(90deg); }
        .sc-row-head { display: flex; align-items: center; gap: .6rem; margin: 0; cursor: pointer; }
        .sc-row-title { font-family: 'Space Grotesk', sans-serif; font-weight: 600; }
        .sc-row-order { margin-left: auto; }
        .sc-row-order input { width: 78px; }
        .sc-row-body { padding: 0 1.1rem 1.1rem; border-top: 1px solid var(--line); }
        .sc-colors { display: flex; gap: 1.2rem; flex-wrap: wrap; margin: 1rem 0 .3rem; }
        .sc-color { display: flex; flex-direction: column; gap: .3rem; font-size: .85rem; font-weight: 600; }
        .sc-color > label { display: flex; align-items: center; gap: .4rem; }
        .sc-color input[type="color"] { width: 64px; height: 34px; padding: 2px; border: 1px solid var(--line); }
        .sc-color input[type="checkbox"] { width: auto; height: auto; }
        .sc-assets { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.2rem; margin: 1rem 0; }
        .sc-save { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin: 1.5rem 0 3rem; }
        .checkline { display: inline-flex; align-items: center; gap: .35rem; margin-left: .5rem; font-weight: 400; }
    </style>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>